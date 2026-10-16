<?php
/**
 * صفحة تعديل التصنيف
 */
require_once '../includes/init.php';
require_once 'auth_check.php';

// التحقق من صلاحيات المستخدم (يجب أن يكون مشرف أو مدير)
if ($_SESSION['user_role'] < USER_ROLE_MODERATOR) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية تعديل التصنيفات';
    redirect('genres_list.php');
}

// التحقق من وجود معرف التصنيف
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف التصنيف غير صالح';
    redirect('genres_list.php');
}

$genreId = (int)$_GET['id'];
$errors = [];

// الحصول على بيانات التصنيف
try {
    $stmt = $conn->prepare("SELECT * FROM genres WHERE id = :id");
    $stmt->bindParam(':id', $genreId);
    $stmt->execute();
    
    $genre = $stmt->fetch();
    
    if (!$genre) {
        $_SESSION['error_message'] = 'التصنيف غير موجود';
        redirect('genres_list.php');
    }
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على بيانات التصنيف: " . $e->getMessage());
    $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    redirect('genres_list.php');
}

// معالجة نموذج تعديل التصنيف
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // الحصول على البيانات وتنظيفها
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    
    // التحقق من صحة البيانات
    if (empty($name)) {
        $errors[] = 'اسم التصنيف مطلوب';
    }
    
    // التحقق من عدم وجود أخطاء
    if (empty($errors)) {
        try {
            // التحقق من عدم وجود تصنيف آخر بنفس الاسم
            $stmt = $conn->prepare("SELECT id FROM genres WHERE name = :name AND id != :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':id', $genreId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $errors[] = 'يوجد تصنيف آخر بنفس الاسم';
            } else {
                // تحديث بيانات التصنيف
                $stmt = $conn->prepare("UPDATE genres SET name = :name, description = :description WHERE id = :id");
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':id', $genreId);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'تم تحديث التصنيف بنجاح';
                    redirect('genres_list.php');
                } else {
                    $errors[] = 'فشل تحديث التصنيف';
                }
            }
        } catch (PDOException $e) {
            error_log("خطأ في تحديث التصنيف: " . $e->getMessage());
            $errors[] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
        }
    }
} else {
    // ملء النموذج ببيانات التصنيف الحالية
    $name = $genre['name'];
    $description = $genre['description'];
}

// الحصول على عدد الأنمي المرتبطة بالتصنيف
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM anime_genres WHERE genre_id = :genre_id");
    $stmt->bindParam(':genre_id', $genreId);
    $stmt->execute();
    $animeCount = $stmt->fetch()['count'];
} catch (PDOException $e) {
    error_log("خطأ في الحصول على عدد الأنمي المرتبطة بالتصنيف: " . $e->getMessage());
    $animeCount = 0;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل التصنيف - <?php echo SITE_NAME; ?></title>
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
                    <h1 class="h2">تعديل التصنيف</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="genres_list.php" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-right me-1"></i> العودة إلى قائمة التصنيفات
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
                        <h6 class="m-0 font-weight-bold text-primary">معلومات التصنيف</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">اسم التصنيف <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $name; ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="description" class="form-label">وصف التصنيف</label>
                                    <input type="text" class="form-control" id="description" name="description" value="<?php echo $description; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <p>عدد الأنمي المرتبطة بهذا التصنيف: 
                                    <?php if ($animeCount > 0): ?>
                                        <a href="anime_list.php?genre_id=<?php echo $genreId; ?>">
                                            <?php echo $animeCount; ?> أنمي
                                        </a>
                                    <?php else: ?>
                                        0 أنمي
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="genres_list.php" class="btn btn-secondary">إلغاء</a>
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
</body>
</html>
