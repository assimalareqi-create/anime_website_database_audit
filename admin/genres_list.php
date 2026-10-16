<?php
/**
 * صفحة إدارة التصنيفات - عرض قائمة التصنيفات
 */
require_once '../includes/init.php';
require_once 'auth_check.php';

// التحقق من صلاحيات المستخدم (يجب أن يكون مشرف أو مدير)
if ($_SESSION['user_role'] < USER_ROLE_MODERATOR) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية إدارة التصنيفات';
    redirect('index.php');
}

// حذف تصنيف
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $genreId = (int)$_GET['delete'];
    
    try {
        // التحقق من عدم استخدام التصنيف في أي أنمي
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM anime_genres WHERE genre_id = :genre_id");
        $stmt->bindParam(':genre_id', $genreId);
        $stmt->execute();
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            $_SESSION['error_message'] = 'لا يمكن حذف التصنيف لأنه مستخدم في ' . $count . ' أنمي';
        } else {
            // حذف التصنيف
            $stmt = $conn->prepare("DELETE FROM genres WHERE id = :id");
            $stmt->bindParam(':id', $genreId);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'تم حذف التصنيف بنجاح';
            } else {
                $_SESSION['error_message'] = 'فشل حذف التصنيف';
            }
        }
    } catch (PDOException $e) {
        error_log("خطأ في حذف التصنيف: " . $e->getMessage());
        $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    }
    
    redirect('genres_list.php');
}

// إضافة تصنيف جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_genre'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'اسم التصنيف مطلوب';
    }
    
    if (empty($errors)) {
        try {
            // التحقق من عدم وجود تصنيف بنفس الاسم
            $stmt = $conn->prepare("SELECT id FROM genres WHERE name = :name");
            $stmt->bindParam(':name', $name);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['error_message'] = 'يوجد تصنيف بنفس الاسم بالفعل';
            } else {
                // إضافة التصنيف الجديد
                $stmt = $conn->prepare("INSERT INTO genres (name, description) VALUES (:name, :description)");
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'تم إضافة التصنيف بنجاح';
                } else {
                    $_SESSION['error_message'] = 'فشل إضافة التصنيف';
                }
            }
        } catch (PDOException $e) {
            error_log("خطأ في إضافة التصنيف: " . $e->getMessage());
            $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
        }
        
        redirect('genres_list.php');
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
        redirect('genres_list.php');
    }
}

// البحث والتصفية
$search = sanitizeInput($_GET['search'] ?? '');
$sort = sanitizeInput($_GET['sort'] ?? 'name');
$order = sanitizeInput($_GET['order'] ?? 'ASC');

// التحقق من صحة معايير الفرز
$allowedSortFields = ['name', 'id'];
$allowedOrderValues = ['ASC', 'DESC'];

if (!in_array($sort, $allowedSortFields)) {
    $sort = 'name';
}

if (!in_array($order, $allowedOrderValues)) {
    $order = 'ASC';
}

// بناء استعلام SQL
$sql = "SELECT g.*, COUNT(ag.anime_id) as anime_count 
        FROM genres g 
        LEFT JOIN anime_genres ag ON g.id = ag.genre_id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND g.name LIKE :search";
    $params[':search'] = "%$search%";
}

$sql .= " GROUP BY g.id ORDER BY g.$sort $order";

try {
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $genresList = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على قائمة التصنيفات: " . $e->getMessage());
    $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    $genresList = [];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة التصنيفات - <?php echo SITE_NAME; ?></title>
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
                    <h1 class="h2">إدارة التصنيفات</h1>
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
                
                <!-- نموذج إضافة تصنيف جديد -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">إضافة تصنيف جديد</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="name" class="form-label">اسم التصنيف <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                
                                <div class="col-md-8 mb-3">
                                    <label for="description" class="form-label">وصف التصنيف</label>
                                    <input type="text" class="form-control" id="description" name="description">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" name="add_genre" class="btn btn-primary">إضافة التصنيف</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- نموذج البحث والتصفية -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">بحث</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo $search; ?>" placeholder="ابحث عن اسم التصنيف...">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="sort" class="form-label">ترتيب حسب</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>الاسم</option>
                                    <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>المعرف</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="order" class="form-label">الترتيب</label>
                                <select class="form-select" id="order" name="order">
                                    <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>تصاعدي</option>
                                    <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>تنازلي</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">تطبيق</button>
                                <a href="genres_list.php" class="btn btn-secondary">إعادة تعيين</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- جدول التصنيفات -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">قائمة التصنيفات</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>المعرف</th>
                                        <th>الاسم</th>
                                        <th>الوصف</th>
                                        <th>عدد الأنمي</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($genresList)): ?>
                                        <?php foreach ($genresList as $genre): ?>
                                            <tr>
                                                <td><?php echo $genre['id']; ?></td>
                                                <td><?php echo $genre['name']; ?></td>
                                                <td><?php echo $genre['description'] ?: 'لا يوجد وصف'; ?></td>
                                                <td>
                                                    <?php if ($genre['anime_count'] > 0): ?>
                                                        <a href="anime_list.php?genre_id=<?php echo $genre['id']; ?>">
                                                            <?php echo $genre['anime_count']; ?> أنمي
                                                        </a>
                                                    <?php else: ?>
                                                        0 أنمي
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="genre_edit.php?id=<?php echo $genre['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($genre['anime_count'] == 0): ?>
                                                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $genre['id']; ?>)" class="btn btn-danger btn-sm" title="حذف">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-danger btn-sm" disabled title="لا يمكن حذف تصنيف مستخدم">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">لا توجد تصنيفات متاحة</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- نافذة تأكيد الحذف -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">تأكيد الحذف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    هل أنت متأكد من حذف هذا التصنيف؟
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">حذف</a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once 'templates/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo JS_URL; ?>/admin.js"></script>
    
    <script>
        // دالة تأكيد الحذف
        function confirmDelete(id) {
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            document.getElementById('confirmDeleteBtn').href = 'genres_list.php?delete=' + id;
            modal.show();
        }
    </script>
</body>
</html>
