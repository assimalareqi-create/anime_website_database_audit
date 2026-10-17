<?php
/**
 * ملف AJAX لإدارة التقييمات
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
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول لتقييم الأنمي']);
    exit;
}

// التحقق من وجود معرف الأنمي والتقييم
if (!isset($_POST['anime_id']) || !is_numeric($_POST['anime_id']) || !isset($_POST['rating']) || !is_numeric($_POST['rating'])) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
    exit;
}

$animeId = (int)$_POST['anime_id'];
$rating = (int)$_POST['rating'];
$userId = $_SESSION['user_id'];

// التحقق من صحة التقييم
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'التقييم يجب أن يكون بين 1 و 5']);
    exit;
}

try {
    // التحقق من وجود تقييم سابق
    $stmt = $conn->prepare("SELECT id FROM ratings WHERE user_id = :user_id AND anime_id = :anime_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':anime_id', $animeId);
    $stmt->execute();
    
    $exists = $stmt->rowCount() > 0;
    
    if ($exists) {
        // تحديث التقييم الحالي
        $stmt = $conn->prepare("UPDATE ratings SET rating = :rating, updated_at = NOW() WHERE user_id = :user_id AND anime_id = :anime_id");
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':anime_id', $animeId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'تم تحديث التقييم بنجاح']);
        } else {
            echo json_encode(['success' => false, 'message' => 'فشل تحديث التقييم']);
        }
    } else {
        // إضافة تقييم جديد
        $stmt = $conn->prepare("INSERT INTO ratings (user_id, anime_id, rating) VALUES (:user_id, :anime_id, :rating)");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':anime_id', $animeId);
        $stmt->bindParam(':rating', $rating);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'تم إضافة التقييم بنجاح']);
        } else {
            echo json_encode(['success' => false, 'message' => 'فشل إضافة التقييم']);
        }
    }
} catch (PDOException $e) {
    error_log("خطأ في إدارة التقييمات: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.']);
}
