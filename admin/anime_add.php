<?php
/**
 * صفحة إضافة أنمي جديد
 */
require_once '../includes/init.php';
require_once 'auth_check.php';

// التحقق من صلاحيات المستخدم (يجب أن يكون مشرف أو مدير)
if ($_SESSION['user_role'] < USER_ROLE_MODERATOR) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية إضافة أنمي جديد';
    redirect('index.php');
}

$errors = [];
$title = '';
$description = '';
$release_year = date('Y');
$status = 'مستمر';

// معالجة نموذج إضافة أنمي
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // الحصول على البيانات وتنظيفها
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $release_year = sanitizeInput($_POST['release_year'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? '');
    $genres = $_POST['genres'] ?? [];
    
    // التحقق من صحة البيانات
    if (empty($title)) {
        $errors[] = 'عنوان الأنمي مطلوب';
    }
    
    if (empty($description)) {
        $errors[] = 'وصف الأنمي مطلوب';
    }
    
    if (empty($release_year) || !is_numeric($release_year) || $release_year < 1900 || $release_year > date('Y') + 1) {
        $errors[] = 'سنة الإصدار غير صالحة';
    }
    
    if (empty($status) || !in_array($status, ['مكتمل', 'مستمر', 'متوقف'])) {
        $errors[] = 'حالة الأنمي غير صالحة';
    }
    
    // التحقق من الصورة
    $cover_image = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        if (!isAllowedFileType($_FILES['cover_image'], ALLOWED_IMAGE_TYPES)) {
            $errors[] = 'نوع ملف الصورة غير مسموح به. الأنواع المسموح بها: JPG, PNG, WebP';
        } elseif (!isAllowedFileSize($_FILES['cover_image'], MAX_IMAGE_SIZE)) {
            $errors[] = 'حجم الصورة كبير جداً. الحد الأقصى: ' . (MAX_IMAGE_SIZE / (1024 * 1024)) . ' ميجابايت';
        }
    }
    
    $banner_image = null;
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        if (!isAllowedFileType($_FILES['banner_image'], ALLOWED_IMAGE_TYPES)) {
            $errors[] = 'نوع ملف صورة البانر غير مسموح به. الأنواع المسموح بها: JPG, PNG, WebP';
        } elseif (!isAllowedFileSize($_FILES['banner_image'], MAX_IMAGE_SIZE)) {
            $errors[] = 'حجم صورة البانر كبير جداً. الحد الأقصى: ' . (MAX_IMAGE_SIZE / (1024 * 1024)) . ' ميجابايت';
        }
    }
    
    // التحقق من عدم وجود أخطاء
    if (empty($errors)) {
        try {
            // بدء المعاملة
            $conn->beginTransaction();
            
            // رفع صورة الغلاف إذا وجدت
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $cover_image = uploadFile($_FILES['cover_image'], ANIME_COVERS_PATH);
                if (!$cover_image) {
                    throw new Exception('فشل رفع صورة الغلاف');
                }
            }
            
            // رفع صورة البانر إذا وجدت
            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                $banner_image = uploadFile($_FILES['banner_image'], ANIME_COVERS_PATH, 'banner_' . uniqid() . '.' . pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
                if (!$banner_image) {
                    throw new Exception('فشل رفع صورة البانر');
                }
            }
            
            // إدراج الأنمي الجديد
            $stmt = $conn->prepare("INSERT INTO anime (title, description, cover_image, banner_image, release_year, status) VALUES (:title, :description, :cover_image, :banner_image, :release_year, :status)");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':cover_image', $cover_image);
            $stmt->bindParam(':banner_image', $banner_image);
            $stmt->bindParam(':release_year', $release_year);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                $animeId = $conn->lastInsertId();
                
                // إضافة التصنيفات
                if (!empty($genres)) {
                    $insertGenreSql = "INSERT INTO anime_genres (anime_id, genre_id) VALUES (:anime_id, :genre_id)";
                    $insertGenreStmt = $conn->prepare($insertGenreSql);
                    
                    foreach ($genres as $genreId) {
                        $insertGenreStmt->bindParam(':anime_id', $animeId);
                        $insertGenreStmt->bindParam(':genre_id', $genreId);
                        $insertGenreStmt->execute();
                    }
                }
                
                // تأكيد المعاملة
                $conn->commit();
                
                $_SESSION['success_message'] = 'تم إضافة الأنمي بنجاح';
                redirect('anime_list.php');
            } else {
                throw new Exception('فشل إضافة الأنمي');
            }
        } catch (Exception $e) {
            // التراجع عن المعاملة في حالة حدوث خطأ
            $conn->rollBack();
            
            error_log("خطأ في إضافة الأنمي: " . $e->getMessage());
            $errors[] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
        }
    }
}

// الحصول على قائمة التصنيفات
try {
    $stmt = $conn->prepare("SELECT * FROM genres ORDER BY name");
    $stmt->execute();
    $genres = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("خطأ في الحصول على قائمة التصنيفات: " . $e->getMessage());
    $genres = [];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة أنمي جديد - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap RTL CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
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
                    <h1 class="h2">إضافة أنمي جديد</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="anime_list.php" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-right me-1"></i> العودة إلى القائمة
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
                        <h6 class="m-0 font-weight-bold text-primary">معلومات الأنمي</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="title" class="form-label">عنوان الأنمي <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo $title; ?>" required>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="release_year" class="form-label">سنة الإصدار <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="release_year" name="release_year" value="<?php echo $release_year; ?>" min="1900" max="<?php echo date('Y') + 1; ?>" required>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="status" class="form-label">الحالة <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="مكتمل" <?php echo $status === 'مكتمل' ? 'selected' : ''; ?>>مكتمل</option>
                                        <option value="مستمر" <?php echo $status === 'مستمر' ? 'selected' : ''; ?>>مستمر</option>
                                        <option value="متوقف" <?php echo $status === 'متوقف' ? 'selected' : ''; ?>>متوقف</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">وصف الأنمي <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="5" required><?php echo $description; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="genres" class="form-label">التصنيفات</label>
                                <select class="form-select select2" id="genres" name="genres[]" multiple>
                                    <?php foreach ($genres as $genre): ?>
                                        <option value="<?php echo $genre['id']; ?>"><?php echo $genre['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">يمكنك اختيار أكثر من تصنيف</small>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="cover_image" class="form-label">صورة الغلاف</label>
                                    <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp">
                                    <small class="text-muted">الحد الأقصى للحجم: <?php echo MAX_IMAGE_SIZE / (1024 * 1024); ?> ميجابايت. الأنواع المسموح بها: JPG, PNG, WebP</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="banner_image" class="form-label">صورة البانر</label>
                                    <input type="file" class="form-control" id="banner_image" name="banner_image" accept="image/jpeg,image/png,image/webp">
                                    <small class="text-muted">الحد الأقصى للحجم: <?php echo MAX_IMAGE_SIZE / (1024 * 1024); ?> ميجابايت. الأنواع المسموح بها: JPG, PNG, WebP</small>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-secondary">إعادة تعيين</button>
                                <button type="submit" class="btn btn-primary">إضافة الأنمي</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include_once 'templates/footer.php'; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo JS_URL; ?>/admin.js"></script>
    
    <script>
        $(document).ready(function() {
            // تهيئة Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                placeholder: 'اختر التصنيفات',
                allowClear: true
            });
        });
    </script>
</body>
</html>
