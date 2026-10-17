<?php
/**
 * صفحة قائمة المتابعة للمستخدم
 */
require_once 'includes/init.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    $_SESSION['error_message'] = 'يجب تسجيل الدخول للوصول إلى قائمة المتابعة';
    redirect('login.php');
}

// معالجة إزالة أنمي من قائمة المتابعة
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $animeId = (int)$_GET['remove'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM watchlist WHERE user_id = :user_id AND anime_id = :anime_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':anime_id', $animeId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'تم إزالة الأنمي من قائمة المتابعة بنجاح';
        } else {
            $_SESSION['error_message'] = 'فشل إزالة الأنمي من قائمة المتابعة';
        }
    } catch (PDOException $e) {
        error_log("خطأ في إزالة الأنمي من قائمة المتابعة: " . $e->getMessage());
        $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    }
    
    redirect('watchlist.php');
}

// الحصول على قائمة المتابعة للمستخدم
try {
    $stmt = $conn->prepare("
        SELECT a.*, w.created_at as added_at,
        COUNT(DISTINCT e.id) as episodes_count, 
        AVG(r.rating) as avg_rating, COUNT(DISTINCT r.id) as ratings_count,
        MAX(e.created_at) as latest_episode_date
        FROM watchlist w
        JOIN anime a ON w.anime_id = a.id
        LEFT JOIN episodes e ON a.id = e.anime_id
        LEFT JOIN ratings r ON a.id = r.anime_id
        WHERE w.user_id = :user_id
        GROUP BY a.id, w.created_at
        ORDER BY w.created_at DESC
    ");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $watchlist = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على قائمة المتابعة: " . $e->getMessage());
    $watchlist = [];
}

// تنسيق التقييم إلى نجوم
function formatRating($rating) {
    $rating = round($rating * 2) / 2; // تقريب إلى أقرب 0.5
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    
    $html = '';
    
    // النجوم الكاملة
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<i class="fas fa-star text-warning"></i>';
    }
    
    // نصف نجمة إذا وجدت
    if ($halfStar) {
        $html .= '<i class="fas fa-star-half-alt text-warning"></i>';
    }
    
    // النجوم الفارغة
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<i class="far fa-star text-warning"></i>';
    }
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قائمة المتابعة - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap RTL CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/style.css">
</head>
<body>
    <?php include_once 'templates/header.php'; ?>
    
    <!-- قسم قائمة المتابعة -->
    <section class="section-padding">
        <div class="container">
            <div class="section-header mb-4">
                <h1 class="h2">قائمة المتابعة</h1>
            </div>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($watchlist)): ?>
                <div class="row">
                    <?php foreach ($watchlist as $anime): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="watchlist-card">
                                <div class="row g-0">
                                    <div class="col-4">
                                        <a href="anime.php?id=<?php echo $anime['id']; ?>">
                                            <img src="<?php echo ANIME_COVERS_URL . '/' . $anime['cover_image']; ?>" alt="<?php echo $anime['title']; ?>" class="img-fluid rounded">
                                        </a>
                                    </div>
                                    <div class="col-8">
                                        <div class="watchlist-content">
                                            <h2 class="watchlist-title">
                                                <a href="anime.php?id=<?php echo $anime['id']; ?>">
                                                    <?php echo $anime['title']; ?>
                                                </a>
                                            </h2>
                                            
                                            <div class="watchlist-meta">
                                                <div class="d-flex flex-wrap">
                                                    <span class="me-3">
                                                        <i class="fas fa-film me-1"></i> <?php echo $anime['episodes_count']; ?> حلقة
                                                    </span>
                                                    <span class="me-3">
                                                        <i class="fas fa-calendar-alt me-1"></i> <?php echo $anime['release_year']; ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="mt-1">
                                                    <span class="badge bg-primary me-2"><?php echo $anime['status']; ?></span>
                                                    <?php if (!empty($anime['avg_rating'])): ?>
                                                        <span class="rating-stars">
                                                            <?php echo formatRating($anime['avg_rating']); ?>
                                                            <span class="ms-1">(<?php echo number_format($anime['avg_rating'], 1); ?>)</span>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="watchlist-actions mt-2">
                                                <?php if (!empty($anime['episodes_count'])): ?>
                                                    <a href="watch.php?anime_id=<?php echo $anime['id']; ?>" class="btn btn-sm btn-primary me-2">
                                                        <i class="fas fa-play me-1"></i> مشاهدة
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="watchlist.php?remove=<?php echo $anime['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('هل أنت متأكد من إزالة هذا الأنمي من قائمة المتابعة؟')">
                                                    <i class="fas fa-trash-alt me-1"></i> إزالة
                                                </a>
                                            </div>
                                            
                                            <?php
                                            // التحقق من وجود حلقات جديدة
                                            if (!empty($anime['latest_episode_date']) && !empty($anime['added_at'])) {
                                                $latestEpisodeDate = new DateTime($anime['latest_episode_date']);
                                                $addedDate = new DateTime($anime['added_at']);
                                                
                                                if ($latestEpisodeDate > $addedDate) {
                                                    echo '<div class="new-episodes-badge mt-2">
                                                        <span class="badge bg-danger">حلقات جديدة</span>
                                                    </div>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> قائمة المتابعة فارغة. يمكنك إضافة أنمي إلى قائمة المتابعة من صفحة تفاصيل الأنمي.
                </div>
                
                <div class="text-center mt-4">
                    <a href="anime_list.php" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> تصفح الأنمي
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <?php include_once 'templates/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo JS_URL; ?>/script.js"></script>
</body>
</html>
