<?php
/**
 * صفحة تعديل حلقة
 */
require_once '../includes/init.php';
require_once 'auth_check.php';

// التحقق من صلاحيات المستخدم (يجب أن يكون مشرف أو مدير)
if ($_SESSION['user_role'] < USER_ROLE_MODERATOR) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية تعديل الحلقات';
    redirect('episodes_list.php');
}

// التحقق من وجود معرف الحلقة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف الحلقة غير صالح';
    redirect('episodes_list.php');
}

$episodeId = (int)$_GET['id'];
$errors = [];

// الحصول على بيانات الحلقة
try {
    $stmt = $conn->prepare("SELECT e.*, a.title as anime_title FROM episodes e JOIN anime a ON e.anime_id = a.id WHERE e.id = :id");
    $stmt->bindParam(':id', $episodeId);
    $stmt->execute();
    
    $episode = $stmt->fetch();
    
    if (!$episode) {
        $_SESSION['error_message'] = 'الحلقة غير موجودة';
        redirect('episodes_list.php');
    }
    
    $animeId = $episode['anime_id'];
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على بيانات الحلقة: " . $e->getMessage());
    $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    redirect('episodes_list.php');
}

// معالجة نموذج تعديل الحلقة
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
    $thumbnail = $episode['thumbnail'];
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        if (!isAllowedFileType($_FILES['thumbnail'], ALLOWED_IMAGE_TYPES)) {
            $errors[] = 'نوع ملف الصورة المصغرة غير مسموح به. الأنواع المسموح بها: JPG, PNG, WebP';
        } elseif (!isAllowedFileSize($_FILES['thumbnail'], MAX_IMAGE_SIZE)) {
            $errors[] = 'حجم الصورة المصغرة كبير جداً. الحد الأقصى: ' . (MAX_IMAGE_SIZE / (1024 * 1024)) . ' ميجابايت';
        }
    }
    
    // التحقق من ملف الفيديو
    $video_path = $episode['video_path'];
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        if (!isAllowedFileType($_FILES['video'], ALLOWED_VIDEO_TYPES)) {
            $errors[] = 'نوع ملف الفيديو غير مسموح به. الأنواع المسموح بها: MP4, WebM';
        } elseif (!isAllowedFileSize($_FILES['video'], MAX_VIDEO_SIZE)) {
            $errors[] = 'حجم الفيديو كبير جداً. الحد الأقصى: ' . (MAX_VIDEO_SIZE / (1024 * 1024)) . ' ميجابايت';
        }
    } elseif (!empty($_POST['video_url'])) {
        $video_path = sanitizeInput($_POST['video_url']);
    }
    
    // التحقق من عدم وجود حلقة أخرى بنفس الرقم
    if ($episode_number != $episode['episode_number']) {
        try {
            $stmt = $conn->prepare("SELECT id FROM episodes WHERE anime_id = :anime_id AND episode_number = :episode_number AND id != :id");
            $stmt->bindParam(':anime_id', $animeId);
            $stmt->bindParam(':episode_number', $episode_number);
            $stmt->bindParam(':id', $episodeId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $errors[] = 'يوجد حلقة أخرى بنفس الرقم';
            }
        } catch (PDOException $e) {
            error_log("خطأ في التحقق من وجود الحلقة: " . $e->getMessage());
            $errors[] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
        }
    }
    
    // التحقق من عدم وجود أخطاء
    if (empty($errors)) {
        try {
            // بدء المعاملة
            $conn->beginTransaction();
            
            // رفع الصورة المصغرة إذا وجدت
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $newThumbnail = uploadFile($_FILES['thumbnail'], EPISODE_THUMBNAILS_PATH);
                if (!$newThumbnail) {
                    throw new Exception('فشل رفع الصورة المصغرة');
                }
                
                // حذف الصورة القديمة إذا وجدت
                if (!empty($thumbnail) && file_exists(EPISODE_THUMBNAILS_PATH . '/' . $thumbnail)) {
                    unlink(EPISODE_THUMBNAILS_PATH . '/' . $thumbnail);
                }
                
                $thumbnail = $newThumbnail;
            }
            
            // رفع ملف الفيديو إذا وجد
            if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
                $newVideoPath = uploadFile($_FILES['video'], VIDEOS_PATH);
                if (!$newVideoPath) {
                    throw new Exception('فشل رفع ملف الفيديو');
                }
                
                // حذف الفيديو القديم إذا كان ملفاً محلياً
                if (!empty($video_path) && !filter_var($video_path, FILTER_VALIDATE_URL) && file_exists(VIDEOS_PATH . '/' . $video_path)) {
                    unlink(VIDEOS_PATH . '/' . $video_path);
                }
                
                $video_path = $newVideoPath;
            }
            
            // تحديث بيانات الحلقة
            $stmt = $conn->prepare("UPDATE episodes SET episode_number = :episode_number, title = :title, description = :description, thumbnail = :thumbnail, video_path = :video_path, duration = :duration, release_date = :release_date, updated_at = NOW() WHERE id = :id");
            $stmt->bindParam(':episode_number', $episode_number);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':thumbnail', $thumbnail);
            $stmt->bindParam(':video_path', $video_path);
            $stmt->bindParam(':duration', $duration);
            $stmt->bindParam(':release_date', $release_date);
            $stmt->bindParam(':id', $episodeId);
            
            if ($stmt->execute()) {
                // تأكيد المعاملة
                $conn->commit();
                
                $_SESSION['success_message'] = 'تم تحديث الحلقة بنجاح';
                redirect('episodes_list.php?anime_id=' . $animeId);
            } else {
                throw new Exception('فشل تحديث الحلقة');
            }
        } catch (Exception $e) {
            // التراجع عن المعاملة في حالة حدوث خطأ
            $conn->rollBack();
            
            error_log("خطأ في تحديث الحلقة: " . $e->getMessage());
            $errors[] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
        }
    }
} else {
    // ملء النموذج ببيانات الحلقة الحالية
    $episode_number = $episode['episode_number'];
    $title = $episode['title'];
    $description = $episode['description'];
    $release_date = $episode['release_date'];
    $duration = $episode['duration'];
}

// تحديد نوع مصدر الفيديو (ملف محلي أو رابط خارجي)
$isVideoUrl = filter_var($episode['video_path'], FILTER_VALIDATE_URL);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الحلقة - <?php echo SITE_NAME; ?></title>
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
                    <h1 class="h2">تعديل الحلقة</h1>
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
                        <h6 class="m-0 font-weight-bold text-primary">معلومات الحلقة - <?php echo $episode['anime_title']; ?></h6>
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
                                    <?php if (!empty($episode['thumbnail'])): ?>
                                        <div class="mb-2">
                                            <img src="<?php echo EPISODE_THUMBNAILS_URL . '/' . $episode['thumbnail']; ?>" alt="<?php echo $episode['title']; ?>" class="img-thumbnail" style="max-height: 150px;">
                                        </div>
                                    <?php endif; ?>
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
                                        <button class="nav-link <?php echo !$isVideoUrl ? 'active' : ''; ?>" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-content" type="button" role="tab" aria-controls="upload-content" aria-selected="<?php echo !$isVideoUrl ? 'true' : 'false'; ?>">رفع ملف</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?php echo $isVideoUrl ? 'active' : ''; ?>" id="url-tab" data-bs-toggle="tab" data-bs-target="#url-content" type="button" role="tab" aria-controls="url-content" aria-selected="<?php echo $isVideoUrl ? 'true' : 'false'; ?>">رابط خارجي</button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content" id="videoTabsContent">
                                    <div class="tab-pane fade <?php echo !$isVideoUrl ? 'show active' : ''; ?> p-3 border border-top-0 rounded-bottom" id="upload-content" role="tabpanel" aria-labelledby="upload-tab">
                                        <?php if (!$isVideoUrl && !empty($episode['video_path'])): ?>
                                            <div class="mb-2">
                                                <p>الفيديو الحالي: <?php echo $episode['video_path']; ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="video" name="video" accept="video/mp4,video/webm">
                                        <small class="text-muted">الحد الأقصى للحجم: <?php echo MAX_VIDEO_SIZE / (1024 * 1024); ?> ميجابايت. الأنواع المسموح بها: MP4, WebM</small>
                                    </div>
                                    <div class="tab-pane fade <?php echo $isVideoUrl ? 'show active' : ''; ?> p-3 border border-top-0 rounded-bottom" id="url-content" role="tabpanel" aria-labelledby="url-tab">
                                        <input type="url" class="form-control" id="video_url" name="video_url" value="<?php echo $isVideoUrl ? $episode['video_path'] : ''; ?>" placeholder="أدخل رابط الفيديو">
                                        <small class="text-muted">يمكنك استخدام روابط YouTube أو Vimeo أو أي منصة أخرى</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="episodes_list.php?anime_id=<?php echo $animeId; ?>" class="btn btn-secondary">إلغاء</a>
                                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
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
