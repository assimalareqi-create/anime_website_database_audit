<?php
/**
 * ملف الوظائف المساعدة
 * يحتوي على الدوال المساعدة المستخدمة في جميع أنحاء الموقع
 */

/**
 * دالة لتنظيف وتحقق من المدخلات
 * 
 * @param string $data البيانات المراد تنظيفها
 * @return string البيانات بعد التنظيف
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * دالة للتحقق من صحة البريد الإلكتروني
 * 
 * @param string $email البريد الإلكتروني المراد التحقق منه
 * @return bool صحة البريد الإلكتروني
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * دالة لتشفير كلمة المرور
 * 
 * @param string $password كلمة المرور المراد تشفيرها
 * @return string كلمة المرور المشفرة
 */
function hashPassword($password) {
    // إضافة الـ pepper إلى كلمة المرور قبل التشفير
    $pepperedPassword = $password . PASSWORD_PEPPER;
    return password_hash($pepperedPassword, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * دالة للتحقق من صحة كلمة المرور
 * 
 * @param string $password كلمة المرور المدخلة
 * @param string $hashedPassword كلمة المرور المشفرة المخزنة
 * @return bool صحة كلمة المرور
 */
function verifyPassword($password, $hashedPassword) {
    // إضافة الـ pepper إلى كلمة المرور قبل التحقق
    $pepperedPassword = $password . PASSWORD_PEPPER;
    return password_verify($pepperedPassword, $hashedPassword);
}

/**
 * دالة لإنشاء رمز عشوائي
 * 
 * @param int $length طول الرمز
 * @return string الرمز العشوائي
 */
function generateRandomToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * دالة للتحقق من صلاحيات المستخدم
 * 
 * @param int $requiredRole الدور المطلوب
 * @return bool هل المستخدم يملك الصلاحية
 */
function checkUserRole($requiredRole) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        return false;
    }
    
    return $_SESSION['user_role'] >= $requiredRole;
}

/**
 * دالة لإعادة توجيه المستخدم
 * 
 * @param string $url الرابط المراد التوجيه إليه
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * دالة لعرض رسالة خطأ
 * 
 * @param string $message رسالة الخطأ
 * @return void
 */
function displayError($message) {
    $_SESSION['error_message'] = $message;
}

/**
 * دالة لعرض رسالة نجاح
 * 
 * @param string $message رسالة النجاح
 * @return void
 */
function displaySuccess($message) {
    $_SESSION['success_message'] = $message;
}

/**
 * دالة للتحقق من نوع الملف
 * 
 * @param string $file الملف المراد التحقق منه
 * @param array $allowedTypes الأنواع المسموح بها
 * @return bool هل نوع الملف مسموح
 */
function isAllowedFileType($file, $allowedTypes) {
    $fileType = $file['type'];
    return in_array($fileType, $allowedTypes);
}

/**
 * دالة للتحقق من حجم الملف
 * 
 * @param string $file الملف المراد التحقق منه
 * @param int $maxSize الحجم الأقصى المسموح به
 * @return bool هل حجم الملف مسموح
 */
function isAllowedFileSize($file, $maxSize) {
    $fileSize = $file['size'];
    return $fileSize <= $maxSize;
}

/**
 * دالة لرفع ملف
 * 
 * @param array $file الملف المراد رفعه
 * @param string $destination مسار الوجهة
 * @param string $newFileName اسم الملف الجديد (اختياري)
 * @return string|bool مسار الملف بعد الرفع أو false في حالة الفشل
 */
function uploadFile($file, $destination, $newFileName = null) {
    // إنشاء اسم فريد للملف إذا لم يتم تحديده
    if ($newFileName === null) {
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = uniqid() . '.' . $fileExtension;
    }
    
    $targetPath = $destination . '/' . $newFileName;
    
    // التأكد من وجود المجلد
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // رفع الملف
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $newFileName;
    }
    
    return false;
}

/**
 * دالة لتنسيق التاريخ
 * 
 * @param string $date التاريخ المراد تنسيقه
 * @param string $format صيغة التنسيق
 * @return string التاريخ المنسق
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

/**
 * دالة لاختصار النص
 * 
 * @param string $text النص المراد اختصاره
 * @param int $length الطول المطلوب
 * @param string $append النص المضاف في نهاية النص المختصر
 * @return string النص المختصر
 */
function truncateText($text, $length = 100, $append = '...') {
    if (strlen($text) > $length) {
        $text = substr($text, 0, $length) . $append;
    }
    return $text;
}

/**
 * دالة للتحقق من وجود المستخدم في قاعدة البيانات
 * 
 * @param PDO $conn اتصال قاعدة البيانات
 * @param string $email البريد الإلكتروني
 * @return bool|array معلومات المستخدم أو false في حالة عدم وجوده
 */
function getUserByEmail($conn, $email) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    $user = $stmt->fetch();
    if ($user) {
        return $user;
    }
    
    return false;
}

/**
 * دالة للتحقق من وجود المستخدم في قاعدة البيانات باستخدام المعرف
 * 
 * @param PDO $conn اتصال قاعدة البيانات
 * @param int $id معرف المستخدم
 * @return bool|array معلومات المستخدم أو false في حالة عدم وجوده
 */
function getUserById($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $user = $stmt->fetch();
    if ($user) {
        return $user;
    }
    
    return false;
}

// دالة التحقق من تسجيل الدخول
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}


/**
 * دالة لبدء الجلسة
 * 
 * @return void
 */
function startSession() {
    if (session_status() == PHP_SESSION_NONE) {
        // تعيين اسم الجلسة
        session_name(SESSION_NAME);
        
        // تعيين خيارات الجلسة للأمان
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        session_start();
    }
}

/**
 * دالة لإنهاء الجلسة
 * 
 * @return void
 */
function endSession() {
    // حذف متغيرات الجلسة
    $_SESSION = [];
    
    // حذف ملف تعريف الارتباط الخاص بالجلسة
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // إنهاء الجلسة
    session_destroy();
}
