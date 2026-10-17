<?php
/**
 * صفحة تسجيل المستخدمين الجدد
 */
require_once '../includes/init.php';

// التحقق مما إذا كان المستخدم مسجل دخوله بالفعل
if (isset($_SESSION['user_id'])) {
    redirect(SITE_URL);
}

$errors = [];
$username = '';
$email = '';

// معالجة نموذج التسجيل
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // الحصول على البيانات وتنظيفها
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
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
    } elseif (!isValidEmail($email)) {
        $errors[] = 'البريد الإلكتروني غير صالح';
    }
    
    if (empty($password)) {
        $errors[] = 'كلمة المرور مطلوبة';
    } elseif (strlen($password) < 8) {
        $errors[] = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'كلمات المرور غير متطابقة';
    }
    
    // التحقق من عدم وجود أخطاء
    if (empty($errors)) {
        try {
            // التحقق من عدم وجود مستخدم بنفس اسم المستخدم أو البريد الإلكتروني
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $errors[] = 'اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل';
            } else {
                // تشفير كلمة المرور
                $hashedPassword = hashPassword($password);
                
                // إدراج المستخدم الجديد
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashedPassword);
                $role = USER_ROLE_USER; // مستخدم عادي
                $stmt->bindParam(':role', $role);
                
                if ($stmt->execute()) {
                    // الحصول على معرف المستخدم الجديد
                    $userId = $conn->lastInsertId();
                    
                    // تسجيل دخول المستخدم تلقائياً
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $username;
                    $_SESSION['user_role'] = $role;
                    
                    // إعادة التوجيه إلى الصفحة الرئيسية
                    redirect(SITE_URL);
                } else {
                    $errors[] = 'حدث خطأ أثناء إنشاء الحساب. يرجى المحاولة مرة أخرى.';
                }
            }
        } catch (PDOException $e) {
            error_log("خطأ في تسجيل المستخدم: " . $e->getMessage());
            $errors[] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل حساب جديد - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap RTL CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/style.css">
</head>
<body>
    <?php include_once '../templates/header.php'; ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">تسجيل حساب جديد</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">اسم المستخدم</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo $username; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">كلمة المرور</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="text-muted">يجب أن تكون كلمة المرور 8 أحرف على الأقل</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">تسجيل</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once '../templates/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo JS_URL; ?>/script.js"></script>
</body>
</html>
