<?php
/**
 * ملف AJAX لإدارة قائمة المتابعة
 */
require_once '../includes/init.php';

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموح بها']);
    exit;
}

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول لإدارة قائمة المتابعة']);
    exit;
}

// التحقق من وجود معرف الأنمي
if (!isset($_POST['anime_id']) || !is_numeric($_POST['anime_id'])) {
    echo json_encode(['success' => false, 'message' => 'معرف الأنمي غير صالح']);
    exit;
}

$animeId = (int)$_POST['anime_id'];
$userId = $_SESSION['user_id'];

try {
    // التحقق من وجود الأنمي في قائمة المتابعة
    $stmt = $conn->prepare("SELECT id FROM watchlist WHERE user_id = :user_id AND anime_id = :anime_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':anime_id', $animeId);
    $stmt->execute();
    
    $exists = $stmt->rowCount() > 0;
    
    if ($exists) {
        // إزالة الأنمي من قائمة المتابعة
        $stmt = $conn->prepare("DELETE FROM watchlist WHERE user_id = :user_id AND anime_id = :anime_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':anime_id', $animeId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'action' => 'remove', 'message' => 'تم إزالة الأنمي من قائمة المتابعة']);
        } else {
            echo json_encode(['success' => false, 'message' => 'فشل إزالة الأنمي من قائمة المتابعة']);
        }
    } else {
        // إضافة الأنمي إلى قائمة المتابعة
        $stmt = $conn->prepare("INSERT INTO watchlist (user_id, anime_id) VALUES (:user_id, :anime_id)");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':anime_id', $animeId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'action' => 'add', 'message' => 'تم إضافة الأنمي إلى قائمة المتابعة']);
        } else {
            echo json_encode(['success' => false, 'message' => 'فشل إضافة الأنمي إلى قائمة المتابعة']);
        }
    }
} catch (PDOException $e) {
    error_log("خطأ في إدارة قائمة المتابعة: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.']);
}
