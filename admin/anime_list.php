<?php
/**
 * صفحة إدارة الأنمي - عرض قائمة الأنمي
 */
require_once '../includes/init.php';
require_once 'auth_check.php';

// حذف أنمي
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $animeId = (int)$_GET['delete'];
    
    // التحقق من صلاحيات المستخدم (يجب أن يكون مدير)
    if ($_SESSION['user_role'] < USER_ROLE_ADMIN) {
        $_SESSION['error_message'] = 'ليس لديك صلاحية حذف الأنمي';
        redirect('anime_list.php');
    }
    
    try {
        // حذف الأنمي (سيتم حذف الحلقات والتعليقات والتقييمات تلقائياً بسبب قيود CASCADE)
        $stmt = $conn->prepare("DELETE FROM anime WHERE id = :id");
        $stmt->bindParam(':id', $animeId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'تم حذف الأنمي بنجاح';
        } else {
            $_SESSION['error_message'] = 'فشل حذف الأنمي';
        }
    } catch (PDOException $e) {
        error_log("خطأ في حذف الأنمي: " . $e->getMessage());
        $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    }
    
    redirect('anime_list.php');
}

// البحث والتصفية
$search = sanitizeInput($_GET['search'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$sort = sanitizeInput($_GET['sort'] ?? 'title');
$order = sanitizeInput($_GET['order'] ?? 'ASC');

// التحقق من صحة معايير الفرز
$allowedSortFields = ['title', 'release_year', 'status', 'rating', 'views', 'created_at'];
$allowedOrderValues = ['ASC', 'DESC'];

if (!in_array($sort, $allowedSortFields)) {
    $sort = 'title';
}

if (!in_array($order, $allowedOrderValues)) {
    $order = 'ASC';
}

// بناء استعلام SQL
$sql = "SELECT * FROM anime WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND title LIKE :search";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $sql .= " AND status = :status";
    $params[':status'] = $status;
}

$sql .= " ORDER BY $sort $order";

// التقسيم إلى صفحات
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$sql .= " LIMIT :offset, :perPage";
$params[':offset'] = $offset;
$params[':perPage'] = $perPage;

try {
    // الحصول على إجمالي عدد الأنمي للتقسيم إلى صفحات
    $countSql = "SELECT COUNT(*) as total FROM anime WHERE 1=1";
    $countParams = [];
    
    if (!empty($search)) {
        $countSql .= " AND title LIKE :search";
        $countParams[':search'] = "%$search%";
    }
    
    if (!empty($status)) {
        $countSql .= " AND status = :status";
        $countParams[':status'] = $status;
    }
    
    $countStmt = $conn->prepare($countSql);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalAnime = $countStmt->fetch()['total'];
    $totalPages = ceil($totalAnime / $perPage);
    
    // الحصول على قائمة الأنمي
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === ':offset' || $key === ':perPage') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $animeList = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على قائمة الأنمي: " . $e->getMessage());
    $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    $animeList = [];
    $totalPages = 1;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأنمي - <?php echo SITE_NAME; ?></title>
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
                    <h1 class="h2">إدارة الأنمي</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="anime_add.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i> إضافة أنمي جديد
                        </a>
                    </div>
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
                
                <!-- نموذج البحث والتصفية -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">بحث</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo $search; ?>" placeholder="ابحث عن عنوان الأنمي...">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="status" class="form-label">الحالة</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">الكل</option>
                                    <option value="مكتمل" <?php echo $status === 'مكتمل' ? 'selected' : ''; ?>>مكتمل</option>
                                    <option value="مستمر" <?php echo $status === 'مستمر' ? 'selected' : ''; ?>>مستمر</option>
                                    <option value="متوقف" <?php echo $status === 'متوقف' ? 'selected' : ''; ?>>متوقف</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="sort" class="form-label">ترتيب حسب</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>العنوان</option>
                                    <option value="release_year" <?php echo $sort === 'release_year' ? 'selected' : ''; ?>>سنة الإصدار</option>
                                    <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>التقييم</option>
                                    <option value="views" <?php echo $sort === 'views' ? 'selected' : ''; ?>>المشاهدات</option>
                                    <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>تاريخ الإضافة</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="order" class="form-label">الترتيب</label>
                                <select class="form-select" id="order" name="order">
                                    <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>تصاعدي</option>
                                    <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>تنازلي</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">تطبيق</button>
                                <a href="anime_list.php" class="btn btn-secondary">إعادة تعيين</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- جدول الأنمي -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">قائمة الأنمي</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>الصورة</th>
                                        <th>العنوان</th>
                                        <th>سنة الإصدار</th>
                                        <th>الحالة</th>
                                        <th>عدد الحلقات</th>
                                        <th>التقييم</th>
                                        <th>المشاهدات</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($animeList)): ?>
                                        <?php foreach ($animeList as $anime): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <?php if (!empty($anime['cover_image'])): ?>
                                                        <img src="<?php echo ANIME_COVERS_URL . '/' . $anime['cover_image']; ?>" alt="<?php echo $anime['title']; ?>" class="img-thumbnail" width="50">
                                                    <?php else: ?>
                                                        <span class="text-muted">لا توجد صورة</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $anime['title']; ?></td>
                                                <td><?php echo $anime['release_year']; ?></td>
                                                <td><?php echo $anime['status']; ?></td>
                                                <td><?php echo $anime['episodes_count']; ?></td>
                                                <td>
                                                    <?php
                                                    $rating = $anime['rating'];
                                                    echo number_format($rating, 1) . ' / 5.0';
                                                    ?>
                                                </td>
                                                <td><?php echo number_format($anime['views']); ?></td>
                                                <td>
                                                    <a href="episodes_list.php?anime_id=<?php echo $anime['id']; ?>" class="btn btn-info btn-sm" title="إدارة الحلقات">
                                                        <i class="fas fa-video"></i>
                                                    </a>
                                                    <a href="anime_edit.php?id=<?php echo $anime['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $anime['id']; ?>)" class="btn btn-danger btn-sm" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">لا توجد بيانات متاحة</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- التقسيم إلى صفحات -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
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
                    هل أنت متأكد من حذف هذا الأنمي؟ سيتم حذف جميع الحلقات والتعليقات والتقييمات المرتبطة به.
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
            document.getElementById('confirmDeleteBtn').href = 'anime_list.php?delete=' + id;
            modal.show();
        }
    </script>
</body>
</html>
