<?php
/**
 * صفحة تسجيل الدخول
 */
require_once '../includes/init.php';

// التحقق مما إذا كان المستخدم مسجل دخوله بالفعل
if (isset($_SESSION['user_id'])) {
    redirect(SITE_URL);
}

$errors = [];
$email = '';

// معالجة نموذج تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // الحصول على البيانات وتنظيفها
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // التحقق من صحة البيانات
    if (empty($email)) {
        $errors[] = 'البريد الإلكتروني مطلوب';
    }
    
    if (empty($password)) {
        $errors[] = 'كلمة المرور مطلوبة';
    }
    
    // التحقق من عدم وجود أخطاء
    if (empty($errors)) {
        try {
            // البحث عن المستخدم بواسطة البريد الإلكتروني
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password'])) {
                // تسجيل دخول المستخدم
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                
                // تحديث وقت آخر تسجيل دخول (اختياري)
                $stmt = $conn->prepare("UPDATE users SET updated_at = NOW() WHERE id = :id");
                $stmt->bindParam(':id', $user['id']);
                $stmt->execute();
                
                // إعادة التوجيه إلى الصفحة الرئيسية أو الصفحة السابقة
                $redirect_to = $_SESSION['redirect_after_login'] ?? SITE_URL;
                unset($_SESSION['redirect_after_login']);
                redirect($redirect_to);
            } else {
                $errors[] = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
            }
        } catch (PDOException $e) {
            error_log("خطأ في تسجيل الدخول: " . $e->getMessage());
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
    <title>تسجيل الدخول - <?php echo SITE_NAME; ?></title>
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
                        <h3 class="mb-0">تسجيل الدخول</h3>
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
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">كلمة المرور</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">تذكرني</label>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">تسجيل الدخول</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        ليس لديك حساب؟ <a href="register.php">تسجيل حساب جديد</a><br>
                        <a href="forgot_password.php">نسيت كلمة المرور؟</a>
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
