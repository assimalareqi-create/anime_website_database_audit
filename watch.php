<?php
/**
 * صفحة مشاهدة الحلقة
 */
require_once 'includes/init.php';

// التحقق من وجود معرف الحلقة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('index.php');
}

$episodeId = (int)$_GET['id'];

// الحصول على بيانات الحلقة
try {
    $stmt = $conn->prepare("
        SELECT e.*, a.title as anime_title, a.cover_image as anime_cover, a.id as anime_id
        FROM episodes e
        JOIN anime a ON e.anime_id = a.id
        WHERE e.id = :id
    ");
    $stmt->bindParam(':id', $episodeId);
    $stmt->execute();
    
    $episode = $stmt->fetch();
    
    if (!$episode) {
        redirect('index.php');
    }
    
    // زيادة عدد المشاهدات
    $stmt = $conn->prepare("UPDATE episodes SET views = views + 1 WHERE id = :id");
    $stmt->bindParam(':id', $episodeId);
    $stmt->execute();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على بيانات الحلقة: " . $e->getMessage());
    redirect('index.php');
}

// الحصول على الحلقة السابقة والتالية
try {
    // الحلقة السابقة
    $stmt = $conn->prepare("
        SELECT id, episode_number
        FROM episodes
        WHERE anime_id = :anime_id AND episode_number < :episode_number
        ORDER BY episode_number DESC
        LIMIT 1
    ");
    $stmt->bindParam(':anime_id', $episode['anime_id']);
    $stmt->bindParam(':episode_number', $episode['episode_number']);
    $stmt->execute();
    $prevEpisode = $stmt->fetch();
    
    // الحلقة التالية
    $stmt = $conn->prepare("
        SELECT id, episode_number
        FROM episodes
        WHERE anime_id = :anime_id AND episode_number > :episode_number
        ORDER BY episode_number ASC
        LIMIT 1
    ");
    $stmt->bindParam(':anime_id', $episode['anime_id']);
    $stmt->bindParam(':episode_number', $episode['episode_number']);
    $stmt->execute();
    $nextEpisode = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على الحلقات المجاورة: " . $e->getMessage());
    $prevEpisode = $nextEpisode = null;
}

// الحصول على قائمة الحلقات للأنمي
try {
    $stmt = $conn->prepare("
        SELECT id, episode_number, title
        FROM episodes
        WHERE anime_id = :anime_id
        ORDER BY episode_number
    ");
    $stmt->bindParam(':anime_id', $episode['anime_id']);
    $stmt->execute();
    $episodesList = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على قائمة الحلقات: " . $e->getMessage());
    $episodesList = [];
}

// الحصول على تعليقات الحلقة
try {
    $stmt = $conn->prepare("
        SELECT c.*, u.username, u.profile_picture
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.episode_id = :episode_id AND c.status = 'approved'
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $stmt->bindParam(':episode_id', $episodeId);
    $stmt->execute();
    
    $comments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على تعليقات الحلقة: " . $e->getMessage());
    $comments = [];
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
                    INSERT INTO comments (anime_id, episode_id, user_id, content, status)
                    VALUES (:anime_id, :episode_id, :user_id, :content, :status)
                ");
                $stmt->bindParam(':anime_id', $episode['anime_id']);
                $stmt->bindParam(':episode_id', $episodeId);
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
                        INSERT INTO comments (anime_id, episode_id, guest_name, content, status)
                        VALUES (:anime_id, :episode_id, :guest_name, :content, 'pending')
                    ");
                    $stmt->bindParam(':anime_id', $episode['anime_id']);
                    $stmt->bindParam(':episode_id', $episodeId);
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
        
        redirect('watch.php?id=' . $episodeId);
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $episode['anime_title']; ?> - الحلقة <?php echo $episode['episode_number']; ?> - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap RTL CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Video.js CSS -->
   <!-- مكتبة Video.js -->
<link href="https://vjs.zencdn.net/8.5.2/video-js.css" rel="stylesheet" />
<script src="https://vjs.zencdn.net/8.5.2/video.min.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/style.css">
</head>
<body>
    <?php include_once 'templates/header.php'; ?>
    
<!-- قسم مشغل الفيديو -->
<section class="video-player-section">
    <div class="container">
        <div class="video-container">
            <video
                id="anime-player"
                class="video-js vjs-big-play-centered vjs-16-9"
                controls
                preload="auto"
                width="100%"
                height="auto"
                poster="<?php echo !empty($episode['thumbnail']) ? EPISODE_THUMBNAILS_URL . '/' . $episode['thumbnail'] : ANIME_COVERS_URL . '/' . $episode['anime_cover']; ?>"
                data-setup='{}'
            >
                <source src="<?php echo VIDEOS_URL . '/' . $episode['video_file']; ?>" type="video/mp4" />
                <p class="vjs-no-js">
                    لتشغيل هذا الفيديو، يرجى تمكين JavaScript وتحديث المتصفح إلى إصدار يدعم فيديو HTML5.
                </p>
            </video>
        </div>

        <!-- التنقل بين الحلقات -->
        <div class="episode-navigation mt-3">
            <div class="row align-items-center">
                <div class="col-4 text-start">
                    <?php if ($prevEpisode): ?>
                        <a href="watch.php?id=<?php echo $prevEpisode['id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-step-backward me-1"></i> الحلقة السابقة
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary" disabled>
                            <i class="fas fa-step-backward me-1"></i> الحلقة السابقة
                        </button>
                    <?php endif; ?>
                </div>
                <div class="col-4 text-center">
                    <a href="anime.php?id=<?php echo $episode['anime_id']; ?>" class="btn btn-primary">
                        <i class="fas fa-list me-1"></i> قائمة الحلقات
                    </a>
                </div>
                <div class="col-4 text-end">
                    <?php if ($nextEpisode): ?>
                        <a href="watch.php?id=<?php echo $nextEpisode['id']; ?>" class="btn btn-outline-primary">
                            الحلقة التالية <i class="fas fa-step-forward ms-1"></i>
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary" disabled>
                            الحلقة التالية <i class="fas fa-step-forward ms-1"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
    
    <!-- قسم معلومات الحلقة -->
    <section class="section-padding">
        <div class="container">
            <div class="row">
                <!-- العمود الرئيسي -->
                <div class="col-lg-8">
                    <!-- معلومات الحلقة -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h1 class="episode-title h4">
                                <?php echo $episode['anime_title']; ?> - الحلقة <?php echo $episode['episode_number']; ?>
                                <?php if (!empty($episode['title'])): ?>
                                    <small class="text-muted d-block mt-1"><?php echo $episode['title']; ?></small>
                                <?php endif; ?>
                            </h1>
                            <div class="episode-meta mt-3">
                                <span class="me-3"><i class="fas fa-eye me-1"></i> <?php echo number_format($episode['views']); ?> مشاهدة</span>
                                <span class="me-3"><i class="fas fa-calendar-alt me-1"></i> <?php echo formatDate($episode['created_at']); ?></span>
                                <?php if ($episode['duration'] > 0): ?>
                                    <span class="me-3">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php 
                                        $minutes = floor($episode['duration'] / 60);
                                        $seconds = $episode['duration'] % 60;
                                        echo sprintf('%02d:%02d', $minutes, $seconds);
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($episode['description'])): ?>
                                <div class="episode-description mt-3">
                                    <h2 class="h6">الوصف:</h2>
                                    <p><?php echo nl2br($episode['description']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- أزرار المشاركة -->
                            <div class="share-buttons mt-3">
                                <h2 class="h6">مشاركة:</h2>
                                <div class="d-flex">
                                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/watch.php?id=' . $episodeId); ?>" target="_blank" class="btn btn-sm btn-primary me-2">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . '/watch.php?id=' . $episodeId); ?>&text=<?php echo urlencode($episode['anime_title'] . ' - الحلقة ' . $episode['episode_number']); ?>" target="_blank" class="btn btn-sm btn-info me-2">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($episode['anime_title'] . ' - الحلقة ' . $episode['episode_number'] . ' ' . SITE_URL . '/watch.php?id=' . $episodeId); ?>" target="_blank" class="btn btn-sm btn-success me-2">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                    <a href="https://t.me/share/url?url=<?php echo urlencode(SITE_URL . '/watch.php?id=' . $episodeId); ?>&text=<?php echo urlencode($episode['anime_title'] . ' - الحلقة ' . $episode['episode_number']); ?>" target="_blank" class="btn btn-sm btn-info">
                                        <i class="fab fa-telegram"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- قسم التعليقات -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h2 class="card-title h5 mb-0">التعليقات</h2>
                            <a href="comments.php?episode_id=<?php echo $episodeId; ?>" class="btn btn-sm btn-outline-primary">عرض الكل</a>
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
                    <!-- قائمة الحلقات -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h2 class="card-title h5 mb-0">قائمة الحلقات</h2>
                            <span class="badge bg-primary"><?php echo count($episodesList); ?> حلقة</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="episodes-list">
                                <?php foreach ($episodesList as $ep): ?>
                                    <a href="watch.php?id=<?php echo $ep['id']; ?>" class="episode-list-item <?php echo $ep['id'] == $episodeId ? 'active' : ''; ?>">
                                        <div class="episode-number">الحلقة <?php echo $ep['episode_number']; ?></div>
                                        <?php if (!empty($ep['title'])): ?>
                                            <div class="episode-title"><?php echo $ep['title']; ?></div>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- معلومات الأنمي -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="card-title h5">معلومات الأنمي</h2>
                        </div>
                        <div class="card-body">
                            <div class="anime-info-card">
                                <div class="row g-0">
                                    <div class="col-4">
                                        <img src="<?php echo ANIME_COVERS_URL . '/' . $episode['anime_cover']; ?>" alt="<?php echo $episode['anime_title']; ?>" class="img-fluid rounded">
                                    </div>
                                    <div class="col-8">
                                        <div class="anime-info-content">
                                            <h3 class="anime-title">
                                                <a href="anime.php?id=<?php echo $episode['anime_id']; ?>">
                                                    <?php echo $episode['anime_title']; ?>
                                                </a>
                                            </h3>
                                            <div class="anime-meta">
                                                <div class="anime-episodes">
                                                    <i class="fas fa-film me-1"></i> <?php echo count($episodesList); ?> حلقة
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <a href="anime.php?id=<?php echo $episode['anime_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-info-circle me-1"></i> تفاصيل الأنمي
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include_once 'templates/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Video.js JS -->
    <script src="https://vjs.zencdn.net/7.20.3/video.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo JS_URL; ?>/script.js"></script>
    
    <script>
        // تهيئة مشغل الفيديو
        var player = videojs('anime-player', {
            fluid: true,
            language: 'ar',
            playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2],
            controlBar: {
                children: [
                    'playToggle',
                    'volumePanel',
                    'currentTimeDisplay',
                    'timeDivider',
                    'durationDisplay',
                    'progressControl',
                    'playbackRateMenuButton',
                    'qualitySelector',
                    'fullscreenToggle'
                ]
            }
        });
        
        // حفظ موضع التشغيل
        player.on('timeupdate', function() {
            localStorage.setItem('episode_<?php echo $episodeId; ?>_time', player.currentTime());
        });
        
        // استعادة موضع التشغيل
        player.on('loadedmetadata', function() {
            var savedTime = localStorage.getItem('episode_<?php echo $episodeId; ?>_time');
            if (savedTime) {
                player.currentTime(parseFloat(savedTime));
            }
        });
        
        // تسجيل المشاهدة عند الانتهاء
        player.on('ended', function() {
            localStorage.removeItem('episode_<?php echo $episodeId; ?>_time');
            
            // إذا كانت هناك حلقة تالية، عرض زر للانتقال إليها
            <?php if ($nextEpisode): ?>
                if (confirm('هل تريد الانتقال إلى الحلقة التالية؟')) {
                    window.location.href = 'watch.php?id=<?php echo $nextEpisode['id']; ?>';
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>
