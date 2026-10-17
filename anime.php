<?php
/**
 * صفحة عرض تفاصيل الأنمي
 */
require_once 'includes/init.php';

// التحقق من وجود معرف الأنمي
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('index.php');
}

$animeId = (int)$_GET['id'];

// الحصول على بيانات الأنمي
try {
    $stmt = $conn->prepare("
        SELECT a.*, COUNT(DISTINCT e.id) as episodes_count, 
        AVG(r.rating) as avg_rating, COUNT(DISTINCT r.id) as ratings_count
        FROM anime a
        LEFT JOIN episodes e ON a.id = e.anime_id
        LEFT JOIN ratings r ON a.id = r.anime_id
        WHERE a.id = :id
        GROUP BY a.id
    ");
    $stmt->bindParam(':id', $animeId);
    $stmt->execute();
    
    $anime = $stmt->fetch();
    
    if (!$anime) {
        redirect('index.php');
    }
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على بيانات الأنمي: " . $e->getMessage());
    redirect('index.php');
}

// الحصول على تصنيفات الأنمي
try {
    $stmt = $conn->prepare("
        SELECT g.* 
        FROM genres g
        JOIN anime_genres ag ON g.id = ag.genre_id
        WHERE ag.anime_id = :anime_id
        ORDER BY g.name
    ");
    $stmt->bindParam(':anime_id', $animeId);
    $stmt->execute();
    
    $genres = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على تصنيفات الأنمي: " . $e->getMessage());
    $genres = [];
}

// الحصول على حلقات الأنمي
try {
    $stmt = $conn->prepare("
        SELECT * 
        FROM episodes
        WHERE anime_id = :anime_id
        ORDER BY episode_number
    ");
    $stmt->bindParam(':anime_id', $animeId);
    $stmt->execute();
    
    $episodes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على حلقات الأنمي: " . $e->getMessage());
    $episodes = [];
}

// الحصول على تعليقات الأنمي
try {
    $stmt = $conn->prepare("
        SELECT c.*, u.username, u.profile_picture
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.anime_id = :anime_id AND c.episode_id IS NULL AND c.status = 'approved'
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $stmt->bindParam(':anime_id', $animeId);
    $stmt->execute();
    
    $comments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على تعليقات الأنمي: " . $e->getMessage());
    $comments = [];
}

// الحصول على أنمي مشابه
try {
    // الحصول على تصنيفات الأنمي الحالي
    $stmt = $conn->prepare("
        SELECT genre_id 
        FROM anime_genres
        WHERE anime_id = :anime_id
    ");
    $stmt->bindParam(':anime_id', $animeId);
    $stmt->execute();
    
    $animeGenreIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($animeGenreIds)) {
        // البحث عن أنمي مشابه بناءً على التصنيفات المشتركة
        $placeholders = implode(',', array_fill(0, count($animeGenreIds), '?'));
        
        $stmt = $conn->prepare("
            SELECT a.*, COUNT(ag.genre_id) as common_genres, 
            AVG(r.rating) as avg_rating
            FROM anime a
            JOIN anime_genres ag ON a.id = ag.anime_id
            LEFT JOIN ratings r ON a.id = r.anime_id
            WHERE ag.genre_id IN ($placeholders) AND a.id != ?
            GROUP BY a.id
            ORDER BY common_genres DESC, avg_rating DESC
            LIMIT 6
        ");
        
        $i = 1;
        foreach ($animeGenreIds as $genreId) {
            $stmt->bindValue($i++, $genreId);
        }
        $stmt->bindValue($i, $animeId);
        
        $stmt->execute();
        $similarAnime = $stmt->fetchAll();
    } else {
        $similarAnime = [];
    }
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على أنمي مشابه: " . $e->getMessage());
    $similarAnime = [];
}

// التحقق مما إذا كان المستخدم قد أضاف الأنمي إلى قائمة المتابعة
$isInWatchlist = false;
if (isLoggedIn()) {
    try {
        $stmt = $conn->prepare("
            SELECT id 
            FROM watchlist
            WHERE user_id = :user_id AND anime_id = :anime_id
        ");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':anime_id', $animeId);
        $stmt->execute();
        
        $isInWatchlist = $stmt->rowCount() > 0;
        
    } catch (PDOException $e) {
        error_log("خطأ في التحقق من قائمة المتابعة: " . $e->getMessage());
    }
}

// التحقق مما إذا كان المستخدم قد قيم الأنمي
$userRating = 0;
if (isLoggedIn()) {
    try {
        $stmt = $conn->prepare("
            SELECT rating 
            FROM ratings
            WHERE user_id = :user_id AND anime_id = :anime_id
        ");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':anime_id', $animeId);
        $stmt->execute();
        
        $ratingRow = $stmt->fetch();
        if ($ratingRow) {
            $userRating = $ratingRow['rating'];
        }
        
    } catch (PDOException $e) {
        error_log("خطأ في التحقق من تقييم المستخدم: " . $e->getMessage());
    }
}

// معالجة إضافة تعليق جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $content = sanitizeInput($_POST['content'] ?? '');
    $errors = [];
    
    if (empty($content)) {
        $errors[] = 'محتوى التعليق مطلوب';
    } elseif (strlen($content) > 1000) {
        $errors[] = 'محتوى التعليق طويل جداً (الحد الأقصى 1000 حرف)';
    }
    
    if (empty($errors)) {
        try {
            if (isLoggedIn()) {
                // إضافة تعليق للمستخدم المسجل
                $stmt = $conn->prepare("
                    INSERT INTO comments (anime_id, user_id, content, status)
                    VALUES (:anime_id, :user_id, :content, :status)
                ");
                $stmt->bindParam(':anime_id', $animeId);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':content', $content);
                
                // تحديد حالة التعليق (موافقة تلقائية للمشرفين والمدراء)
                $status = $_SESSION['user_role'] >= USER_ROLE_MODERATOR ? 'approved' : 'pending';
                $stmt->bindParam(':status', $status);
                
                if ($stmt->execute()) {
                    if ($status === 'approved') {
                        $_SESSION['success_message'] = 'تم إضافة التعليق بنجاح';
                    } else {
                        $_SESSION['success_message'] = 'تم إرسال التعليق وسيتم مراجعته قريباً';
                    }
                } else {
                    $_SESSION['error_message'] = 'فشل إضافة التعليق';
                }
            } else {
                // إضافة تعليق للزائر
                $guest_name = sanitizeInput($_POST['guest_name'] ?? '');
                
                if (empty($guest_name)) {
                    $_SESSION['error_message'] = 'اسم الزائر مطلوب';
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO comments (anime_id, guest_name, content, status)
                        VALUES (:anime_id, :guest_name, :content, 'pending')
                    ");
                    $stmt->bindParam(':anime_id', $animeId);
                    $stmt->bindParam(':guest_name', $guest_name);
                    $stmt->bindParam(':content', $content);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = 'تم إرسال التعليق وسيتم مراجعته قريباً';
                    } else {
                        $_SESSION['error_message'] = 'فشل إضافة التعليق';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("خطأ في إضافة التعليق: " . $e->getMessage());
            $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
        }
        
        redirect('anime.php?id=' . $animeId);
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
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
    <title><?php echo $anime['title']; ?> - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap RTL CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/style.css">
</head>
<body>
    <?php include_once 'templates/header.php'; ?>
    
    <!-- قسم بانر الأنمي -->
    <section class="anime-banner" style="background-image: url('<?php echo !empty($anime['banner_image']) ? ANIME_COVERS_URL . '/' . $anime['banner_image'] : ANIME_COVERS_URL . '/' . $anime['cover_image']; ?>');">
        <div class="container">
            <div class="row">
                <div class="col-md-3 col-lg-2">
                    <div class="anime-poster">
                        <img src="<?php echo ANIME_COVERS_URL . '/' . $anime['cover_image']; ?>" alt="<?php echo $anime['title']; ?>" class="img-fluid">
                    </div>
                </div>
                <div class="col-md-9 col-lg-10">
                    <div class="anime-banner-content">
                        <h1 class="anime-title"><?php echo $anime['title']; ?></h1>
                        <div class="anime-meta">
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
                        
                        <div class="anime-genres">
                            <?php foreach ($genres as $genre): ?>
                                <a href="anime_list.php?genre_id=<?php echo $genre['id']; ?>" class="badge bg-secondary me-1">
                                    <?php echo $genre['name']; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="anime-actions mt-3">
                            <?php if (!empty($episodes)): ?>
                                <a href="watch.php?id=<?php echo $episodes[0]['id']; ?>" class="btn btn-primary me-2">
                                    <i class="fas fa-play me-1"></i> مشاهدة
                                </a>
                            <?php endif; ?>
                            
                            <?php if (isLoggedIn()): ?>
                                <button id="watchlistBtn" class="btn <?php echo $isInWatchlist ? 'btn-danger' : 'btn-outline-light'; ?>" onclick="toggleWatchlist(<?php echo $animeId; ?>)">
                                    <i class="<?php echo $isInWatchlist ? 'fas' : 'far'; ?> fa-bookmark me-1"></i>
                                    <?php echo $isInWatchlist ? 'إزالة من المتابعة' : 'إضافة للمتابعة'; ?>
                                </button>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-outline-light">
                                    <i class="far fa-bookmark me-1"></i> إضافة للمتابعة
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- قسم محتوى الأنمي -->
    <section class="section-padding">
        <div class="container">
            <div class="row">
                <!-- العمود الرئيسي -->
                <div class="col-lg-8">
                    <!-- وصف الأنمي -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="card-title h5">القصة</h2>
                        </div>
                        <div class="card-body">
                            <p class="anime-description">
                                <?php echo nl2br($anime['description']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- قائمة الحلقات -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h2 class="card-title h5 mb-0">الحلقات</h2>
                            <span class="badge bg-primary"><?php echo count($episodes); ?> حلقة</span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($episodes)): ?>
                                <div class="row episodes-grid">
                                    <?php foreach ($episodes as $episode): ?>
                                        <div class="col-6 col-md-4 col-xl-3 mb-3">
                                            <div class="episode-item">
                                                <a href="watch.php?id=<?php echo $episode['id']; ?>" class="episode-link">
                                                    <div class="episode-thumbnail">
                                                        <img src="<?php echo !empty($episode['thumbnail']) ? EPISODE_THUMBNAILS_URL . '/' . $episode['thumbnail'] : ANIME_COVERS_URL . '/' . $anime['cover_image']; ?>" alt="الحلقة <?php echo $episode['episode_number']; ?>" class="img-fluid">
                                                        <div class="episode-overlay">
                                                            <span class="play-icon"><i class="fas fa-play"></i></span>
                                                        </div>
                                                    </div>
                                                    <div class="episode-info">
                                                        <div class="episode-number">الحلقة <?php echo $episode['episode_number']; ?></div>
                                                        <?php if (!empty($episode['title'])): ?>
                                                            <div class="episode-title"><?php echo $episode['title']; ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    لا توجد حلقات متاحة حالياً.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- قسم التعليقات -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h2 class="card-title h5 mb-0">التعليقات</h2>
                            <a href="comments.php?anime_id=<?php echo $animeId; ?>" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                        </div>
                        <div class="card-body">
                            <!-- نموذج إضافة تعليق -->
                            <div class="add-comment mb-4">
                                <h3 class="h6 mb-3">أضف تعليقاً</h3>
                                
                                <?php if (isset($_SESSION['error_message'])): ?>
                                    <div class="alert alert-danger">
                                        <?php 
                                        echo $_SESSION['error_message']; 
                                        unset($_SESSION['error_message']);
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($_SESSION['success_message'])): ?>
                                    <div class="alert alert-success">
                                        <?php 
                                        echo $_SESSION['success_message']; 
                                        unset($_SESSION['success_message']);
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <?php if (!isLoggedIn()): ?>
                                        <div class="mb-3">
                                            <label for="guest_name" class="form-label">الاسم <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="guest_name" name="guest_name" required>
                                            <div class="form-text">
                                                <a href="login.php">تسجيل الدخول</a> للتعليق باسم حسابك.
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="content" class="form-label">التعليق <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="content" name="content" rows="3" required></textarea>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" name="add_comment" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i> إرسال
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- قائمة التعليقات -->
                            <div class="comments-list">
                                <?php if (!empty($comments)): ?>
                                    <?php foreach ($comments as $comment): ?>
                                        <div class="comment-item">
                                            <div class="comment-avatar">
                                                <?php if (!empty($comment['profile_picture'])): ?>
                                                    <img src="<?php echo USER_PROFILES_URL . '/' . $comment['profile_picture']; ?>" alt="<?php echo $comment['username'] ?: $comment['guest_name']; ?>" class="rounded-circle">
                                                <?php else: ?>
                                                    <div class="default-avatar">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="comment-content">
                                                <div class="comment-header">
                                                    <h4 class="comment-author">
                                                        <?php echo $comment['username'] ?: $comment['guest_name']; ?>
                                                        <?php if (empty($comment['username'])): ?>
                                                            <span class="badge bg-secondary ms-1">زائر</span>
                                                        <?php endif; ?>
                                                    </h4>
                                                    <span class="comment-date">
                                                        <i class="far fa-clock me-1"></i>
                                                        <?php echo formatDate($comment['created_at']); ?>
                                                    </span>
                                                </div>
                                                <div class="comment-text">
                                                    <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        لا توجد تعليقات حتى الآن. كن أول من يعلق!
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- العمود الجانبي -->
                <div class="col-lg-4">
                    <!-- قسم التقييم -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="card-title h5">التقييم</h2>
                        </div>
                        <div class="card-body">
                            <div class="rating-summary text-center mb-3">
                                <div class="rating-score">
                                    <?php if (!empty($anime['avg_rating'])): ?>
                                        <span class="display-4"><?php echo number_format($anime['avg_rating'], 1); ?></span>
                                        <span class="text-muted">/ 5</span>
                                    <?php else: ?>
                                        <span class="display-4">-</span>
                                    <?php endif; ?>
                                </div>
                                <div class="rating-stars my-2">
                                    <?php echo formatRating($anime['avg_rating'] ?? 0); ?>
                                </div>
                                <div class="rating-count text-muted">
                                    <?php echo $anime['ratings_count']; ?> تقييم
                                </div>
                            </div>
                            
                            <?php if (isLoggedIn()): ?>
                                <div class="user-rating">
                                    <h3 class="h6 mb-2">تقييمك</h3>
                                    <div class="rating-input text-center">
                                        <div class="rating-stars-input">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo $i <= $userRating ? 'fas' : 'far'; ?> fa-star fa-lg star-rating" data-rating="<?php echo $i; ?>" onclick="rateAnime(<?php echo $animeId; ?>, <?php echo $i; ?>)"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="rating-message mt-2">
                                            <?php if ($userRating > 0): ?>
                                                <small class="text-success">شكراً لتقييمك!</small>
                                            <?php else: ?>
                                                <small class="text-muted">انقر على النجوم للتقييم</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center">
                                    <a href="login.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-sign-in-alt me-1"></i> تسجيل الدخول للتقييم
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- قسم المعلومات -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="card-title h5">معلومات</h2>
                        </div>
                        <div class="card-body">
                            <ul class="anime-info-list">
                                <li>
                                    <span class="info-label">الحالة:</span>
                                    <span class="info-value"><?php echo $anime['status']; ?></span>
                                </li>
                                <li>
                                    <span class="info-label">سنة الإصدار:</span>
                                    <span class="info-value"><?php echo $anime['release_year']; ?></span>
                                </li>
                                <li>
                                    <span class="info-label">عدد الحلقات:</span>
                                    <span class="info-value"><?php echo $anime['episodes_count']; ?></span>
                                </li>
                                <li>
                                    <span class="info-label">التصنيفات:</span>
                                    <span class="info-value">
                                        <?php if (!empty($genres)): ?>
                                            <?php echo implode(', ', array_map(function($genre) {
                                                return $genre['name'];
                                            }, $genres)); ?>
                                        <?php else: ?>
                                            غير محدد
                                        <?php endif; ?>
                                    </span>
                                </li>
                                <li>
                                    <span class="info-label">تاريخ الإضافة:</span>
                                    <span class="info-value"><?php echo formatDate($anime['created_at'], 'Y-m-d'); ?></span>
                                </li>
                                <li>
                                    <span class="info-label">آخر تحديث:</span>
                                    <span class="info-value"><?php echo formatDate($anime['updated_at'], 'Y-m-d'); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- قسم أنمي مشابه -->
                    <?php if (!empty($similarAnime)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h2 class="card-title h5">أنمي مشابه</h2>
                            </div>
                            <div class="card-body">
                                <div class="similar-anime">
                                    <?php foreach ($similarAnime as $similar): ?>
                                        <div class="similar-anime-item">
                                            <a href="anime.php?id=<?php echo $similar['id']; ?>" class="similar-anime-link">
                                                <div class="row g-0">
                                                    <div class="col-3">
                                                        <img src="<?php echo ANIME_COVERS_URL . '/' . $similar['cover_image']; ?>" alt="<?php echo $similar['title']; ?>" class="img-fluid rounded">
                                                    </div>
                                                    <div class="col-9">
                                                        <div class="similar-anime-info">
                                                            <h3 class="similar-anime-title"><?php echo $similar['title']; ?></h3>
                                                            <div class="similar-anime-meta">
                                                                <span class="year"><?php echo $similar['release_year']; ?></span>
                                                                <?php if (!empty($similar['avg_rating'])): ?>
                                                                    <span class="rating">
                                                                        <i class="fas fa-star text-warning"></i>
                                                                        <?php echo number_format($similar['avg_rating'], 1); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <?php include_once 'templates/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo JS_URL; ?>/script.js"></script>
    
    <script>
        // دالة إضافة/إزالة الأنمي من قائمة المتابعة
        function toggleWatchlist(animeId) {
            fetch('ajax/watchlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'anime_id=' + animeId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const watchlistBtn = document.getElementById('watchlistBtn');
                    
                    if (data.action === 'add') {
                        watchlistBtn.classList.remove('btn-outline-light');
                        watchlistBtn.classList.add('btn-danger');
                        watchlistBtn.innerHTML = '<i class="fas fa-bookmark me-1"></i> إزالة من المتابعة';
                    } else {
                        watchlistBtn.classList.remove('btn-danger');
                        watchlistBtn.classList.add('btn-outline-light');
                        watchlistBtn.innerHTML = '<i class="far fa-bookmark me-1"></i> إضافة للمتابعة';
                    }
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ أثناء معالجة الطلب');
            });
        }
        
        // دالة تقييم الأنمي
        function rateAnime(animeId, rating) {
            fetch('ajax/rating.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'anime_id=' + animeId + '&rating=' + rating
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // تحديث نجوم التقييم
                    const stars = document.querySelectorAll('.star-rating');
                    stars.forEach(star => {
                        const starRating = parseInt(star.getAttribute('data-rating'));
                        if (starRating <= rating) {
                            star.classList.remove('far');
                            star.classList.add('fas');
                        } else {
                            star.classList.remove('fas');
                            star.classList.add('far');
                        }
                    });
                    
                    // تحديث رسالة التقييم
                    document.querySelector('.rating-message').innerHTML = '<small class="text-success">شكراً لتقييمك!</small>';
                    
                    // تحديث متوسط التقييم (اختياري - يمكن تحديثه بإعادة تحميل الصفحة)
                    // للتحديث الفوري، يمكن إضافة المزيد من المنطق هنا
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ أثناء معالجة الطلب');
            });
        }
    </script>
</body>
</html>
