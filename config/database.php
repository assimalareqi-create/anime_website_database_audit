<?php
/**
 * ملف إعدادات قاعدة البيانات
 * يحتوي على معلومات الاتصال بقاعدة البيانات وإعدادات الاتصال
 */

// معلومات الاتصال بقاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'anime_website');

// إنشاء اتصال PDO مع قاعدة البيانات
function getDbConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // تسجيل الخطأ بدلاً من عرضه للمستخدم
        error_log("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
        return null;
    }
}

/**
 * دالة للتحقق من اتصال قاعدة البيانات
 * تستخدم للتأكد من صحة الاتصال بقاعدة البيانات
 * 
 * @return bool نجاح أو فشل الاتصال
 */
function testDbConnection() {
    try {
        $conn = getDbConnection();
        if ($conn) {
            return true;
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}
