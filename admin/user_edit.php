<?php
/**
 * صفحة تعديل المستخدم
 */
require_once '../includes/init.php';
require_once 'auth_check.php';

// التحقق من صلاحيات المستخدم (يجب أن يكون مدير)
if ($_SESSION['user_role'] < USER_ROLE_ADMIN) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية تعديل المستخدمين';
    redirect('users_list.php');
}

// التحقق من وجود معرف المستخدم
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف المستخدم غير صالح';
    redirect('users_list.php');
}

$userId = (int)$_GET['id'];
$errors = [];

// الحصول على بيانات المستخدم
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error_message'] = 'المستخدم غير موجود';
        redirect('users_list.php');
    }
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على بيانات المستخدم: " . $e->getMessage());
    $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    redirect('users_list.php');
}

// معالجة نموذج تعديل المستخدم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // الحصول على البيانات وتنظيفها
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $role = isset($_POST['role']) && is_numeric($_POST['role']) ? (int)$_POST['role'] : USER_ROLE_USER;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // التحقق من صحة البيانات
    if (empty($username)) {
        $errors[] = 'اسم المستخدم مطلوب';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = 'اسم المستخدم يجب أن يكون بين 3 و 50 حرفاً';
    }
    
    if (empty($email)) {
        $errors[] = 'البريد الإلكتروني مطلوب';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صالح';
    }
    
    if ($role < USER_ROLE_USER || $role > USER_ROLE_ADMIN) {
        $errors[] = 'دور المستخدم غير صالح';
    }
    
    // التحقق من كلمة المرور إذا تم تغييرها
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = 'كلمة المرور يجب أن تكون على الأقل 6 أحرف';
        } elseif ($password !== $confirm_password) {
            $errors[] = 'كلمة المرور وتأكيدها غير متطابقين';
        }
    }
    
    // التحقق من الصورة الشخصية
    $profile_picture = $user['profile_picture'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        if (!isAllowedFileType($_FILES['profile_picture'], ALLOWED_IMAGE_TYPES)) {
            $errors[] = 'نوع ملف الصورة الشخصية غير مسموح به. الأنواع المسموح بها: JPG, PNG, WebP';
        } elseif (!isAllowedFileSize($_FILES['profile_picture'], MAX_IMAGE_SIZE)) {
            $errors[] = 'حجم الصورة الشخصية كبير جداً. الحد الأقصى: ' . (MAX_IMAGE_SIZE / (1024 * 1024)) . ' ميجابايت';
        }
    }
    
    // التحقق من عدم وجود مستخدم آخر بنفس اسم المستخدم أو البريد الإلكتروني
    if ($username !== $user['username'] || $email !== $user['email']) {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $errors[] = 'اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل';
            }
        } catch (PDOException $e) {
            error_log("خطأ في التحقق من وجود المستخدم: " . $e->getMessage());
            $errors[] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
        }
    }
    
    // التحقق من عدم وجود أخطاء
    if (empty($errors)) {
        try {
            // بدء المعاملة
            $conn->beginTransaction();
            
            // رفع الصورة الشخصية إذا وجدت
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $newProfilePicture = uploadFile($_FILES['profile_picture'], USER_PROFILES_PATH);
                if (!$newProfilePicture) {
                    throw new Exception('فشل رفع الصورة الشخصية');
                }
                
                // حذف الصورة القديمة إذا وجدت
                if (!empty($profile_picture) && file_exists(USER_PROFILES_PATH . '/' . $profile_picture)) {
                    unlink(USER_PROFILES_PATH . '/' . $profile_picture);
                }
                
                $profile_picture = $newProfilePicture;
            }
            
            // تحديث بيانات المستخدم
            if (!empty($password)) {
                // تحديث مع كلمة المرور الجديدة
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE users SET username = :username, email = :email, password = :password, role = :role, profile_picture = :profile_picture, updated_at = NOW() WHERE id = :id");
                $stmt->bindParam(':password', $hashed_password);
            } else {
                // تحديث بدون تغيير كلمة المرور
                $stmt = $conn->prepare("UPDATE users SET username = :username, email = :email, role = :role, profile_picture = :profile_picture, updated_at = NOW() WHERE id = :id");
            }
            
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':profile_picture', $profile_picture);
            $stmt->bindParam(':id', $userId);
            
            if ($stmt->execute()) {
                // تأكيد المعاملة
                $conn->commit();
                
                $_SESSION['success_message'] = 'تم تحديث المستخدم بنجاح';
                redirect('users_list.php');
            } else {
                throw new Exception('فشل تحديث المستخدم');
            }
        } catch (Exception $e) {
            // التراجع عن المعاملة في حالة حدوث خطأ
            $conn->rollBack();
            
            error_log("خطأ في تحديث المستخدم: " . $e->getMessage());
            $errors[] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
        }
    }
} else {
    // ملء النموذج ببيانات المستخدم الحالية
    $username = $user['username'];
    $email = $user['email'];
    $role = $user['role'];
}

// دالة لعرض اسم الدور
function getRoleName($role) {
    switch ($role) {
        case USER_ROLE_VISITOR:
            return 'زائر';
        case USER_ROLE_USER:
            return 'مستخدم';
        case USER_ROLE_MODERATOR:
            return 'مشرف';
        case USER_ROLE_ADMIN:
            return 'مدير';
        default:
            return 'غير معروف';
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل المستخدم - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap RTL CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/admin.css">
</head>
<body>
    <?php include_once 'templates/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once 'templates/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">تعديل المستخدم</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="users_list.php" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-right me-1"></i> العودة إلى قائمة المستخدمين
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">معلومات المستخدم</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo $username; ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="password" class="form-label">كلمة المرور</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <small class="text-muted">اتركها فارغة إذا لم ترغب في تغييرها</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="role" class="form-label">الدور <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role" name="role" required <?php echo $userId === 1 ? 'disabled' : ''; ?>>
                                        <option value="<?php echo USER_ROLE_USER; ?>" <?php echo $role === USER_ROLE_USER ? 'selected' : ''; ?>>مستخدم</option>
                                        <option value="<?php echo USER_ROLE_MODERATOR; ?>" <?php echo $role === USER_ROLE_MODERATOR ? 'selected' : ''; ?>>مشرف</option>
                                        <option value="<?php echo USER_ROLE_ADMIN; ?>" <?php echo $role === USER_ROLE_ADMIN ? 'selected' : ''; ?>>مدير</option>
                                    </select>
                                    <?php if ($userId === 1): ?>
                                        <small class="text-muted">لا يمكن تغيير دور المدير الرئيسي</small>
                                        <input type="hidden" name="role" value="<?php echo USER_ROLE_ADMIN; ?>">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="profile_picture" class="form-label">الصورة الشخصية</label>
                                    <?php if (!empty($user['profile_picture'])): ?>
                                        <div class="mb-2">
                                            <img src="<?php echo USER_PROFILES_URL . '/' . $user['profile_picture']; ?>" alt="<?php echo $user['username']; ?>" class="img-thumbnail" style="max-height: 150px;">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/webp">
                                    <small class="text-muted">الحد الأقصى للحجم: <?php echo MAX_IMAGE_SIZE / (1024 * 1024); ?> ميجابايت. الأنواع المسموح بها: JPG, PNG, WebP</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <p>تاريخ التسجيل: <?php echo formatDate($user['created_at'], 'Y-m-d H:i'); ?></p>
                                <p>آخر تحديث: <?php echo formatDate($user['updated_at'], 'Y-m-d H:i'); ?></p>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="users_list.php" class="btn btn-secondary">إلغاء</a>
                                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include_once 'templates/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo JS_URL; ?>/admin.js"></script>
</body>
</html>
