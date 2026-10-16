<?php
/**
 * صفحة إضافة حلقة جديدة
 */
require_once '../includes/init.php';
require_once 'auth_check.php';

// التحقق من صلاحيات المستخدم (يجب أن يكون مشرف أو مدير)
if ($_SESSION['user_role'] < USER_ROLE_MODERATOR) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية إضافة حلقات جديدة';
    redirect('episodes_list.php');
}

// التحقق من وجود معرف الأنمي
if (!isset($_GET['anime_id']) || !is_numeric($_GET['anime_id'])) {
    $_SESSION['error_message'] = 'معرف الأنمي غير صالح';
    redirect('anime_list.php');
}

$animeId = (int)$_GET['anime_id'];
$errors = [];

// الحصول على معلومات الأنمي
try {
    $stmt = $conn->prepare("SELECT * FROM anime WHERE id = :id");
    $stmt->bindParam(':id', $animeId);
    $stmt->execute();
    
    $anime = $stmt->fetch();
    
    if (!$anime) {
        $_SESSION['error_message'] = 'الأنمي غير موجود';
        redirect('anime_list.php');
    }
    
    // الحصول على آخر رقم حلقة
    $stmt = $conn->prepare("SELECT MAX(episode_number) as last_episode FROM episodes WHERE anime_id = :anime_id");
    $stmt->bindParam(':anime_id', $animeId);
    $stmt->execute();
    
    $lastEpisode = $stmt->fetch();
    $nextEpisodeNumber = ($lastEpisode && $lastEpisode['last_episode']) ? $lastEpisode['last_episode'] + 1 : 1;
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على معلومات الأنمي: " . $e->getMessage());
    $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    redirect('anime_list.php');
}

// تعيين القيم الافتراضية
$episode_number = $nextEpisodeNumber;
$title = '';
$description = '';
$release_date = date('Y-m-d');
$duration = 0;

// معالجة نموذج إضافة حلقة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // الحصول على البيانات وتنظيفها
    $episode_number = sanitizeInput($_POST['episode_number'] ?? '');
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $release_date = sanitizeInput($_POST['release_date'] ?? '');
    $duration = sanitizeInput($_POST['duration'] ?? '');
    
    // التحقق من صحة البيانات
    if (empty($episode_number) || !is_numeric($episode_number) || $episode_number <= 0) {
        $errors[] = 'رقم الحلقة غير صالح';
    }
    
    if (empty($release_date)) {
        $errors[] = 'تاريخ الإصدار مطلوب';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $release_date)) {
        $errors[] = 'صيغة تاريخ الإصدار غير صالحة (YYYY-MM-DD)';
    }
    
    if (!is_numeric($duration) || $duration < 0) {
        $errors[] = 'مدة الحلقة غير صالحة';
    }
    
    // التحقق من الصورة المصغرة
    $thumbnail = null;
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        if (!isAllowedFileType($_FILES['thumbnail'], ALLOWED_IMAGE_TYPES)) {
            $errors[] = 'نوع ملف الصورة المصغرة غير مسموح به. الأنواع المسموح بها: JPG, PNG, WebP';
        } elseif (!isAllowedFileSize($_FILES['thumbnail'], MAX_IMAGE_SIZE)) {
            $errors[] = 'حجم الصورة المصغرة كبير جداً. الحد الأقصى: ' . (MAX_IMAGE_SIZE / (1024 * 1024)) . ' ميجابايت';
        }
    }
    
    // التحقق من ملف الفيديو
    $video_path = null;
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        if (!isAllowedFileType($_FILES['video'], ALLOWED_VIDEO_TYPES)) {
            $errors[] = 'نوع ملف الفيديو غير مسموح به. الأنواع المسموح بها: MP4, WebM';
        } elseif (!isAllowedFileSize($_FILES['video'], MAX_VIDEO_SIZE)) {
            $errors[] = 'حجم الفيديو كبير جداً. الحد الأقصى: ' . (MAX_VIDEO_SIZE / (1024 * 1024)) . ' ميجابايت';
        }
    } elseif (empty($_POST['video_url'])) {
        $errors[] = 'يجب تحميل ملف فيديو أو إدخال رابط فيديو';
    }
    
    // التحقق من عدم وجود حلقة بنفس الرقم
    try {
        $stmt = $conn->prepare("SELECT id FROM episodes WHERE anime_id = :anime_id AND episode_number = :episode_number");
        $stmt->bindParam(':anime_id', $animeId);
        $stmt->bindParam(':episode_number', $episode_number);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $errors[] = 'يوجد حلقة بنفس الرقم بالفعل';
        }
    } catch (PDOException $e) {
        error_log("خطأ في التحقق من وجود الحلقة: " . $e->getMessage());
        $errors[] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    }
    
    // التحقق من عدم وجود أخطاء
    if (empty($errors)) {
        try {
            // بدء المعاملة
            $conn->beginTransaction();
            
            // رفع الصورة المصغرة إذا وجدت
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $thumbnail = uploadFile($_FILES['thumbnail'], EPISODE_THUMBNAILS_PATH);
                if (!$thumbnail) {
                    throw new Exception('فشل رفع الصورة المصغرة');
                }
            }
            
            // رفع ملف الفيديو إذا وجد
            if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
                $video_path = uploadFile($_FILES['video'], VIDEOS_PATH);
                if (!$video_path) {
                    throw new Exception('فشل رفع ملف الفيديو');
                }
            } else {
                // استخدام رابط الفيديو
                $video_path = sanitizeInput($_POST['video_url']);
            }
            
            // إدراج الحلقة الجديدة
            $stmt = $conn->prepare("INSERT INTO episodes (anime_id, episode_number, title, description, thumbnail, video_path, duration, release_date) VALUES (:anime_id, :episode_number, :title, :description, :thumbnail, :video_path, :duration, :release_date)");
            $stmt->bindParam(':anime_id', $animeId);
            $stmt->bindParam(':episode_number', $episode_number);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':thumbnail', $thumbnail);
            $stmt->bindParam(':video_path', $video_path);
            $stmt->bindParam(':duration', $duration);
            $stmt->bindParam(':release_date', $release_date);
            
            if ($stmt->execute()) {
                // تأكيد المعاملة
                $conn->commit();
                
                $_SESSION['success_message'] = 'تم إضافة الحلقة بنجاح';
                redirect('episodes_list.php?anime_id=' . $animeId);
            } else {
                throw new Exception('فشل إضافة الحلقة');
            }
        } catch (Exception $e) {
            // التراجع عن المعاملة في حالة حدوث خطأ
            $conn->rollBack();
            
            error_log("خطأ في إضافة الحلقة: " . $e->getMessage());
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
    <title>إضافة حلقة جديدة - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap RTL CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/admin.css">
</head>
<body>
    <?php include_once 'templates/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include_once 'templates/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">إضافة حلقة جديدة</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="episodes_list.php?anime_id=<?php echo $animeId; ?>" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-right me-1"></i> العودة إلى قائمة الحلقات
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">معلومات الحلقة - <?php echo $anime['title']; ?></h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="episode_number" class="form-label">رقم الحلقة <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="episode_number" name="episode_number" value="<?php echo $episode_number; ?>" min="1" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="title" class="form-label">عنوان الحلقة</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo $title; ?>">
                                    <small class="text-muted">اختياري، إذا تركته فارغاً سيتم استخدام "الحلقة X"</small>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="release_date" class="form-label">تاريخ الإصدار <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="release_date" name="release_date" value="<?php echo $release_date; ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">وصف الحلقة</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo $description; ?></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="thumbnail" class="form-label">الصورة المصغرة</label>
                                    <input type="file" class="form-control" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/webp">
                                    <small class="text-muted">الحد الأقصى للحجم: <?php echo MAX_IMAGE_SIZE / (1024 * 1024); ?> ميجابايت. الأنواع المسموح بها: JPG, PNG, WebP</small>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="duration" class="form-label">مدة الحلقة (بالثواني)</label>
                                    <input type="number" class="form-control" id="duration" name="duration" value="<?php echo $duration; ?>" min="0">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ملف الفيديو أو رابط الفيديو <span class="text-danger">*</span></label>
                                
                                <ul class="nav nav-tabs" id="videoTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-content" type="button" role="tab" aria-controls="upload-content" aria-selected="true">رفع ملف</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="url-tab" data-bs-toggle="tab" data-bs-target="#url-content" type="button" role="tab" aria-controls="url-content" aria-selected="false">رابط خارجي</button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content" id="videoTabsContent">
                                    <div class="tab-pane fade show active p-3 border border-top-0 rounded-bottom" id="upload-content" role="tabpanel" aria-labelledby="upload-tab">
                                        <input type="file" class="form-control" id="video" name="video" accept="video/mp4,video/webm">
                                        <small class="text-muted">الحد الأقصى للحجم: <?php echo MAX_VIDEO_SIZE / (1024 * 1024); ?> ميجابايت. الأنواع المسموح بها: MP4, WebM</small>
                                    </div>
                                    <div class="tab-pane fade p-3 border border-top-0 rounded-bottom" id="url-content" role="tabpanel" aria-labelledby="url-tab">
                                        <input type="url" class="form-control" id="video_url" name="video_url" placeholder="أدخل رابط الفيديو">
                                        <small class="text-muted">يمكنك استخدام روابط YouTube أو Vimeo أو أي منصة أخرى</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-secondary">إعادة تعيين</button>
                                <button type="submit" class="btn btn-primary">إضافة الحلقة</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include_once 'templates/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo JS_URL; ?>/admin.js"></script>
    
    <script>
        // تبديل بين علامات التبويب
        document.addEventListener('DOMContentLoaded', function() {
            const uploadTab = document.getElementById('upload-tab');
            const urlTab = document.getElementById('url-tab');
            const videoInput = document.getElementById('video');
            const videoUrlInput = document.getElementById('video_url');
            
            uploadTab.addEventListener('click', function() {
                videoUrlInput.value = '';
            });
            
            urlTab.addEventListener('click', function() {
                videoInput.value = '';
            });
        });
    </script>
</body>
</html>
