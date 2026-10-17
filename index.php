<?php
/**
 * الصفحة الرئيسية للموقع
 */
require_once 'includes/init.php';

// الحصول على أحدث الأنمي المضافة
try {
    $stmt = $conn->prepare("
        SELECT a.*, COUNT(DISTINCT e.id) as episodes_count, 
        AVG(r.rating) as avg_rating, COUNT(DISTINCT r.id) as ratings_count
        FROM anime a
        LEFT JOIN episodes e ON a.id = e.anime_id
        LEFT JOIN ratings r ON a.id = r.anime_id
        GROUP BY a.id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $latestAnime = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("خطأ في الحصول على أحدث الأنمي: " . $e->getMessage());
    $latestAnime = [];
}

// الحصول على الأنمي الأكثر مشاهدة
try {
    $stmt = $conn->prepare("
        SELECT a.*, COUNT(DISTINCT e.id) as episodes_count, 
        SUM(e.views) as total_views,
        AVG(r.rating) as avg_rating, COUNT(DISTINCT r.id) as ratings_count
        FROM anime a
        LEFT JOIN episodes e ON a.id = e.anime_id
        LEFT JOIN ratings r ON a.id = r.anime_id
        GROUP BY a.id
        ORDER BY total_views DESC
        LIMIT 10
    ");
    $stmt->execute();
    $mostViewedAnime = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("خطأ في الحصول على الأنمي الأكثر مشاهدة: " . $e->getMessage());
    $mostViewedAnime = [];
}

// الحصول على الأنمي الأعلى تقييماً
try {
    $stmt = $conn->prepare("
        SELECT a.*, COUNT(DISTINCT e.id) as episodes_count, 
        AVG(r.rating) as avg_rating, COUNT(DISTINCT r.id) as ratings_count
        FROM anime a
        LEFT JOIN episodes e ON a.id = e.anime_id
        LEFT JOIN ratings r ON a.id = r.anime_id
        GROUP BY a.id
        HAVING COUNT(DISTINCT r.id) > 0
        ORDER BY avg_rating DESC, ratings_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $topRatedAnime = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("خطأ في الحصول على الأنمي الأعلى تقييماً: " . $e->getMessage());
    $topRatedAnime = [];
}

// الحصول على أحدث الحلقات المضافة
try {
    $stmt = $conn->prepare("
        SELECT e.*, a.title as anime_title, a.cover_image as anime_cover
        FROM episodes e
        JOIN anime a ON e.anime_id = a.id
        ORDER BY e.created_at DESC
        LIMIT 12
    ");
    $stmt->execute();
    $latestEpisodes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("خطأ في الحصول على أحدث الحلقات: " . $e->getMessage());
    $latestEpisodes = [];
}

// الحصول على التصنيفات
try {
    $stmt = $conn->prepare("
        SELECT g.*, COUNT(ag.anime_id) as anime_count
        FROM genres g
        LEFT JOIN anime_genres ag ON g.id = ag.genre_id
        GROUP BY g.id
        ORDER BY anime_count DESC
        LIMIT 20
    ");
    $stmt->execute();
    $genres = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("خطأ في الحصول على التصنيفات: " . $e->getMessage());
    $genres = [];
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
    <title><?php echo SITE_NAME; ?> - موقع مشاهدة الأنمي</title>
    <!-- Bootstrap RTL CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/style.css">
</head>
<body>
    <?php include_once 'templates/header.php'; ?>
    
    <!-- قسم البانر الرئيسي -->
    <section class="hero-section">
        <div class="swiper hero-swiper">
            <div class="swiper-wrapper">
                <?php foreach (array_slice($latestAnime, 0, 5) as $anime): ?>
                    <div class="swiper-slide">
                        <div class="hero-slide" style="background-image: url('<?php echo !empty($anime['banner_image']) ? ANIME_COVERS_URL . '/' . $anime['banner_image'] : ANIME_COVERS_URL . '/' . $anime['cover_image']; ?>');">
                            <div class="hero-content">
                                <div class="container">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <h1 class="hero-title"><?php echo $anime['title']; ?></h1>
                                            <div class="hero-meta">
                                                <span class="badge bg-primary me-2"><?php echo $anime['status']; ?></span>
                                                <span class="me-3"><i class="fas fa-calendar-alt me-1"></i> <?php echo $anime['release_year']; ?></span>
                                                <span class="me-3"><i class="fas fa-film me-1"></i> <?php echo $anime['episodes_count']; ?> حلقة</span>
                                                <?php if (!empty($anime['avg_rating'])): ?>
                                                    <span class="me-3">
                                                        <?php echo formatRating($anime['avg_rating']); ?>
                                                        <span class="ms-1">(<?php echo number_format($anime['avg_rating'], 1); ?>)</span>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="hero-description">
                                                <?php echo mb_substr($anime['description'], 0, 200) . (mb_strlen($anime['description']) > 200 ? '...' : ''); ?>
                                            </p>
                                            <div class="hero-buttons">
                                                <a href="anime.php?id=<?php echo $anime['id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-info-circle me-1"></i> التفاصيل
                                                </a>
                                                <a href="watch.php?anime_id=<?php echo $anime['id']; ?>" class="btn btn-outline-light ms-2">
                                                    <i class="fas fa-play me-1"></i> مشاهدة
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination"></div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
    </section>
    
    <!-- قسم أحدث الحلقات -->
    <section class="section-padding">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">أحدث الحلقات</h2>
                <a href="episodes.php" class="section-link">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            
            <div class="row">
                <?php foreach ($latestEpisodes as $episode): ?>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-4">
                        <div class="episode-card">
                            <div class="episode-thumbnail">
                                <a href="watch.php?id=<?php echo $episode['id']; ?>">
                                    <img src="<?php echo !empty($episode['thumbnail']) ? EPISODE_THUMBNAILS_URL . '/' . $episode['thumbnail'] : ANIME_COVERS_URL . '/' . $episode['anime_cover']; ?>" alt="<?php echo $episode['title'] ?: 'الحلقة ' . $episode['episode_number']; ?>" class="img-fluid">
                                    <div class="episode-overlay">
                                        <span class="play-icon"><i class="fas fa-play"></i></span>
                                        <?php if ($episode['duration'] > 0): ?>
                                            <span class="duration">
                                                <?php 
                                                $minutes = floor($episode['duration'] / 60);
                                                $seconds = $episode['duration'] % 60;
                                                echo sprintf('%02d:%02d', $minutes, $seconds);
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </div>
                            <div class="episode-info">
                                <h3 class="episode-title">
                                    <a href="watch.php?id=<?php echo $episode['id']; ?>">
                                        <?php echo $episode['title'] ?: 'الحلقة ' . $episode['episode_number']; ?>
                                    </a>
                                </h3>
                                <div class="anime-title">
                                    <a href="anime.php?id=<?php echo $episode['anime_id']; ?>">
                                        <?php echo $episode['anime_title']; ?>
                                    </a>
                                </div>
                                <div class="episode-meta">
                                    <span class="episode-number">الحلقة <?php echo $episode['episode_number']; ?></span>
                                    <span class="episode-views"><i class="fas fa-eye"></i> <?php echo number_format($episode['views']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- قسم أحدث الأنمي -->
    <section class="section-padding bg-light">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">أحدث الأنمي</h2>
                <a href="anime_list.php?sort=created_at" class="section-link">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            
            <div class="row">
                <?php foreach ($latestAnime as $anime): ?>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-4">
                        <div class="anime-card">
                            <div class="anime-thumbnail">
                                <a href="anime.php?id=<?php echo $anime['id']; ?>">
                                    <img src="<?php echo ANIME_COVERS_URL . '/' . $anime['cover_image']; ?>" alt="<?php echo $anime['title']; ?>" class="img-fluid">
                                    <div class="anime-overlay">
                                        <span class="anime-status"><?php echo $anime['status']; ?></span>
                                        <span class="anime-episodes"><?php echo $anime['episodes_count']; ?> حلقة</span>
                                    </div>
                                </a>
                            </div>
                            <div class="anime-info">
                                <h3 class="anime-title">
                                    <a href="anime.php?id=<?php echo $anime['id']; ?>">
                                        <?php echo $anime['title']; ?>
                                    </a>
                                </h3>
                                <div class="anime-meta">
                                    <span class="anime-year"><?php echo $anime['release_year']; ?></span>
                                    <?php if (!empty($anime['avg_rating'])): ?>
                                        <span class="anime-rating">
                                            <i class="fas fa-star text-warning"></i>
                                            <?php echo number_format($anime['avg_rating'], 1); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- قسم الأنمي الأكثر مشاهدة -->
    <section class="section-padding">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">الأكثر مشاهدة</h2>
                <a href="anime_list.php?sort=views" class="section-link">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            
            <div class="row">
                <?php foreach ($mostViewedAnime as $anime): ?>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-4">
                        <div class="anime-card">
                            <div class="anime-thumbnail">
                                <a href="anime.php?id=<?php echo $anime['id']; ?>">
                                    <img src="<?php echo ANIME_COVERS_URL . '/' . $anime['cover_image']; ?>" alt="<?php echo $anime['title']; ?>" class="img-fluid">
                                    <div class="anime-overlay">
                                        <span class="anime-status"><?php echo $anime['status']; ?></span>
                                        <span class="anime-episodes"><?php echo $anime['episodes_count']; ?> حلقة</span>
                                    </div>
                                </a>
                            </div>
                            <div class="anime-info">
                                <h3 class="anime-title">
                                    <a href="anime.php?id=<?php echo $anime['id']; ?>">
                                        <?php echo $anime['title']; ?>
                                    </a>
                                </h3>
                                <div class="anime-meta">
                                    <span class="anime-year"><?php echo $anime['release_year']; ?></span>
                                    <?php if (!empty($anime['avg_rating'])): ?>
                                        <span class="anime-rating">
                                            <i class="fas fa-star text-warning"></i>
                                            <?php echo number_format($anime['avg_rating'], 1); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- قسم الأنمي الأعلى تقييماً -->
    <section class="section-padding bg-light">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">الأعلى تقييماً</h2>
                <a href="anime_list.php?sort=rating" class="section-link">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            
            <div class="row">
                <?php foreach ($topRatedAnime as $anime): ?>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-4">
                        <div class="anime-card">
                            <div class="anime-thumbnail">
                                <a href="anime.php?id=<?php echo $anime['id']; ?>">
                                    <img src="<?php echo ANIME_COVERS_URL . '/' . $anime['cover_image']; ?>" alt="<?php echo $anime['title']; ?>" class="img-fluid">
                                    <div class="anime-overlay">
                                        <span class="anime-status"><?php echo $anime['status']; ?></span>
                                        <span class="anime-episodes"><?php echo $anime['episodes_count']; ?> حلقة</span>
                                    </div>
                                </a>
                            </div>
                            <div class="anime-info">
                                <h3 class="anime-title">
                                    <a href="anime.php?id=<?php echo $anime['id']; ?>">
                                        <?php echo $anime['title']; ?>
                                    </a>
                                </h3>
                                <div class="anime-meta">
                                    <span class="anime-year"><?php echo $anime['release_year']; ?></span>
                                    <span class="anime-rating">
                                        <i class="fas fa-star text-warning"></i>
                                        <?php echo number_format($anime['avg_rating'], 1); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- قسم التصنيفات -->
    <section class="section-padding">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">تصفح حسب التصنيف</h2>
                <a href="genres.php" class="section-link">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            
            <div class="genres-container">
                <?php foreach ($genres as $genre): ?>
                    <a href="anime_list.php?genre_id=<?php echo $genre['id']; ?>" class="genre-badge">
                        <?php echo $genre['name']; ?>
                        <span class="genre-count"><?php echo $genre['anime_count']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <?php include_once 'templates/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo JS_URL; ?>/script.js"></script>
    
    <script>
        // تهيئة Swiper للبانر الرئيسي
        const heroSwiper = new Swiper('.hero-swiper', {
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
        });
    </script>
</body>
</html>
