<?php
/**
 * صفحة إدارة التعليقات - عرض قائمة التعليقات
 */
require_once '../includes/init.php';
require_once 'auth_check.php';

// التحقق من صلاحيات المستخدم (يجب أن يكون مشرف أو مدير)
if ($_SESSION['user_role'] < USER_ROLE_MODERATOR) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية إدارة التعليقات';
    redirect('index.php');
}

// حذف تعليق
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $commentId = (int)$_GET['delete'];
    
    try {
        // حذف التعليق
        $stmt = $conn->prepare("DELETE FROM comments WHERE id = :id");
        $stmt->bindParam(':id', $commentId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'تم حذف التعليق بنجاح';
        } else {
            $_SESSION['error_message'] = 'فشل حذف التعليق';
        }
    } catch (PDOException $e) {
        error_log("خطأ في حذف التعليق: " . $e->getMessage());
        $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    }
    
    // إعادة التوجيه إلى نفس الصفحة مع الحفاظ على معايير التصفية
    $redirectUrl = 'comments_list.php';
    $queryParams = [];
    
    if (isset($_GET['anime_id'])) {
        $queryParams[] = 'anime_id=' . (int)$_GET['anime_id'];
    }
    
    if (isset($_GET['episode_id'])) {
        $queryParams[] = 'episode_id=' . (int)$_GET['episode_id'];
    }
    
    if (isset($_GET['user_id'])) {
        $queryParams[] = 'user_id=' . (int)$_GET['user_id'];
    }
    
    if (isset($_GET['status'])) {
        $queryParams[] = 'status=' . sanitizeInput($_GET['status']);
    }
    
    if (!empty($queryParams)) {
        $redirectUrl .= '?' . implode('&', $queryParams);
    }
    
    redirect($redirectUrl);
}

// تغيير حالة التعليق (موافقة/رفض)
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $commentId = (int)$_GET['approve'];
    $status = 'approved';
    
    try {
        $stmt = $conn->prepare("UPDATE comments SET status = :status, updated_at = NOW() WHERE id = :id");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $commentId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'تم الموافقة على التعليق بنجاح';
        } else {
            $_SESSION['error_message'] = 'فشل تحديث حالة التعليق';
        }
    } catch (PDOException $e) {
        error_log("خطأ في تحديث حالة التعليق: " . $e->getMessage());
        $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    }
    
    redirect('comments_list.php' . (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $commentId = (int)$_GET['reject'];
    $status = 'rejected';
    
    try {
        $stmt = $conn->prepare("UPDATE comments SET status = :status, updated_at = NOW() WHERE id = :id");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $commentId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'تم رفض التعليق بنجاح';
        } else {
            $_SESSION['error_message'] = 'فشل تحديث حالة التعليق';
        }
    } catch (PDOException $e) {
        error_log("خطأ في تحديث حالة التعليق: " . $e->getMessage());
        $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    }
    
    redirect('comments_list.php' . (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// البحث والتصفية
$search = sanitizeInput($_GET['search'] ?? '');
$animeId = isset($_GET['anime_id']) && is_numeric($_GET['anime_id']) ? (int)$_GET['anime_id'] : null;
$episodeId = isset($_GET['episode_id']) && is_numeric($_GET['episode_id']) ? (int)$_GET['episode_id'] : null;
$userId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$status = sanitizeInput($_GET['status'] ?? '');
$sort = sanitizeInput($_GET['sort'] ?? 'created_at');
$order = sanitizeInput($_GET['order'] ?? 'DESC');

// التحقق من صحة معايير الفرز
$allowedSortFields = ['created_at', 'updated_at', 'status'];
$allowedOrderValues = ['ASC', 'DESC'];
$allowedStatusValues = ['pending', 'approved', 'rejected', ''];

if (!in_array($sort, $allowedSortFields)) {
    $sort = 'created_at';
}

if (!in_array($order, $allowedOrderValues)) {
    $order = 'DESC';
}

if (!in_array($status, $allowedStatusValues)) {
    $status = '';
}

// بناء استعلام SQL
$sql = "SELECT c.*, u.username, a.title as anime_title, e.episode_number, e.title as episode_title 
        FROM comments c 
        LEFT JOIN users u ON c.user_id = u.id 
        LEFT JOIN anime a ON c.anime_id = a.id 
        LEFT JOIN episodes e ON c.episode_id = e.id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.content LIKE :search OR u.username LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($animeId !== null) {
    $sql .= " AND c.anime_id = :anime_id";
    $params[':anime_id'] = $animeId;
}

if ($episodeId !== null) {
    $sql .= " AND c.episode_id = :episode_id";
    $params[':episode_id'] = $episodeId;
}

if ($userId !== null) {
    $sql .= " AND c.user_id = :user_id";
    $params[':user_id'] = $userId;
}

if (!empty($status)) {
    $sql .= " AND c.status = :status";
    $params[':status'] = $status;
}

$sql .= " ORDER BY c.$sort $order";

// التقسيم إلى صفحات
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$sql .= " LIMIT :offset, :perPage";
$params[':offset'] = $offset;
$params[':perPage'] = $perPage;

try {
    // الحصول على إجمالي عدد التعليقات للتقسيم إلى صفحات
    $countSql = "SELECT COUNT(*) as total 
                FROM comments c 
                LEFT JOIN users u ON c.user_id = u.id 
                LEFT JOIN anime a ON c.anime_id = a.id 
                LEFT JOIN episodes e ON c.episode_id = e.id 
                WHERE 1=1";
    $countParams = [];
    
    if (!empty($search)) {
        $countSql .= " AND (c.content LIKE :search OR u.username LIKE :search)";
        $countParams[':search'] = "%$search%";
    }
    
    if ($animeId !== null) {
        $countSql .= " AND c.anime_id = :anime_id";
        $countParams[':anime_id'] = $animeId;
    }
    
    if ($episodeId !== null) {
        $countSql .= " AND c.episode_id = :episode_id";
        $countParams[':episode_id'] = $episodeId;
    }
    
    if ($userId !== null) {
        $countSql .= " AND c.user_id = :user_id";
        $countParams[':user_id'] = $userId;
    }
    
    if (!empty($status)) {
        $countSql .= " AND c.status = :status";
        $countParams[':status'] = $status;
    }
    
    $countStmt = $conn->prepare($countSql);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalComments = $countStmt->fetch()['total'];
    $totalPages = ceil($totalComments / $perPage);
    
    // الحصول على قائمة التعليقات
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === ':offset' || $key === ':perPage') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $commentsList = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على قائمة التعليقات: " . $e->getMessage());
    $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    $commentsList = [];
    $totalPages = 1;
}

// الحصول على قائمة الأنمي للتصفية
try {
    $animeStmt = $conn->prepare("SELECT id, title FROM anime ORDER BY title");
    $animeStmt->execute();
    $animeList = $animeStmt->fetchAll();
} catch (PDOException $e) {
    error_log("خطأ في الحصول على قائمة الأنمي: " . $e->getMessage());
    $animeList = [];
}

// دالة لعرض اسم حالة التعليق
function getStatusName($status) {
    switch ($status) {
        case 'pending':
            return 'قيد المراجعة';
        case 'approved':
            return 'تمت الموافقة';
        case 'rejected':
            return 'مرفوض';
        default:
            return 'غير معروف';
    }
}

// دالة لعرض لون حالة التعليق
function getStatusColor($status) {
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'approved':
            return 'success';
        case 'rejected':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة التعليقات - <?php echo SITE_NAME; ?></title>
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
                    <h1 class="h2">إدارة التعليقات</h1>
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
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo $search; ?>" placeholder="ابحث في محتوى التعليقات أو اسم المستخدم...">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="anime_id" class="form-label">الأنمي</label>
                                <select class="form-select" id="anime_id" name="anime_id">
                                    <option value="">الكل</option>
                                    <?php foreach ($animeList as $anime): ?>
                                        <option value="<?php echo $anime['id']; ?>" <?php echo $animeId === $anime['id'] ? 'selected' : ''; ?>><?php echo $anime['title']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="status" class="form-label">الحالة</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">الكل</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>قيد المراجعة</option>
                                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>تمت الموافقة</option>
                                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>مرفوض</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="sort" class="form-label">ترتيب حسب</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>تاريخ الإضافة</option>
                                    <option value="updated_at" <?php echo $sort === 'updated_at' ? 'selected' : ''; ?>>تاريخ التحديث</option>
                                    <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>الحالة</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="order" class="form-label">الترتيب</label>
                                <select class="form-select" id="order" name="order">
                                    <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>تنازلي</option>
                                    <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>تصاعدي</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">تطبيق</button>
                                <a href="comments_list.php" class="btn btn-secondary">إعادة تعيين</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- جدول التعليقات -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">قائمة التعليقات</h6>
                        <div>
                            <span class="badge bg-warning me-2">قيد المراجعة: <?php echo countCommentsByStatus($conn, 'pending'); ?></span>
                            <span class="badge bg-success me-2">تمت الموافقة: <?php echo countCommentsByStatus($conn, 'approved'); ?></span>
                            <span class="badge bg-danger">مرفوض: <?php echo countCommentsByStatus($conn, 'rejected'); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>المستخدم</th>
                                        <th>الأنمي / الحلقة</th>
                                        <th>التعليق</th>
                                        <th>الحالة</th>
                                        <th>تاريخ الإضافة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($commentsList)): ?>
                                        <?php foreach ($commentsList as $comment): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($comment['user_id']): ?>
                                                        <a href="users_list.php?search=<?php echo urlencode($comment['username']); ?>">
                                                            <?php echo $comment['username']; ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <?php echo $comment['guest_name']; ?> (زائر)
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($comment['anime_id']): ?>
                                                        <a href="anime_edit.php?id=<?php echo $comment['anime_id']; ?>">
                                                            <?php echo $comment['anime_title']; ?>
                                                        </a>
                                                        <?php if ($comment['episode_id']): ?>
                                                            <br>
                                                            <small>
                                                                <a href="episode_edit.php?id=<?php echo $comment['episode_id']; ?>">
                                                                    الحلقة <?php echo $comment['episode_number']; ?>
                                                                    <?php echo $comment['episode_title'] ? ' - ' . $comment['episode_title'] : ''; ?>
                                                                </a>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">غير محدد</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="max-height: 100px; overflow-y: auto;">
                                                        <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusColor($comment['status']); ?>">
                                                        <?php echo getStatusName($comment['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($comment['created_at'], 'Y-m-d H:i'); ?></td>
                                                <td>
                                                    <?php if ($comment['status'] === 'pending'): ?>
                                                        <a href="comments_list.php?approve=<?php echo $comment['id']; ?>&<?php echo http_build_query($_GET); ?>" class="btn btn-success btn-sm" title="موافقة">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                        <a href="comments_list.php?reject=<?php echo $comment['id']; ?>&<?php echo http_build_query($_GET); ?>" class="btn btn-warning btn-sm" title="رفض">
                                                            <i class="fas fa-ban"></i>
                                                        </a>
                                                    <?php elseif ($comment['status'] === 'approved'): ?>
                                                        <a href="comments_list.php?reject=<?php echo $comment['id']; ?>&<?php echo http_build_query($_GET); ?>" class="btn btn-warning btn-sm" title="رفض">
                                                            <i class="fas fa-ban"></i>
                                                        </a>
                                                    <?php elseif ($comment['status'] === 'rejected'): ?>
                                                        <a href="comments_list.php?approve=<?php echo $comment['id']; ?>&<?php echo http_build_query($_GET); ?>" class="btn btn-success btn-sm" title="موافقة">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $comment['id']; ?>)" class="btn btn-danger btn-sm" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">لا توجد تعليقات متاحة</td>
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
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter([
                                                'search' => $search,
                                                'anime_id' => $animeId,
                                                'episode_id' => $episodeId,
                                                'user_id' => $userId,
                                                'status' => $status,
                                                'sort' => $sort,
                                                'order' => $order
                                            ])); ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter([
                                                'search' => $search,
                                                'anime_id' => $animeId,
                                                'episode_id' => $episodeId,
                                                'user_id' => $userId,
                                                'status' => $status,
                                                'sort' => $sort,
                                                'order' => $order
                                            ])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter([
                                                'search' => $search,
                                                'anime_id' => $animeId,
                                                'episode_id' => $episodeId,
                                                'user_id' => $userId,
                                                'status' => $status,
                                                'sort' => $sort,
                                                'order' => $order
                                            ])); ?>" aria-label="Next">
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
                    هل أنت متأكد من حذف هذا التعليق؟
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
            document.getElementById('confirmDeleteBtn').href = 'comments_list.php?delete=' + id + '&<?php echo http_build_query(array_filter([
                'page' => $page,
                'search' => $search,
                'anime_id' => $animeId,
                'episode_id' => $episodeId,
                'user_id' => $userId,
                'status' => $status,
                'sort' => $sort,
                'order' => $order
            ])); ?>';
            modal.show();
        }
    </script>
</body>
</html>

<?php
// دالة لحساب عدد التعليقات حسب الحالة
function countCommentsByStatus($conn, $status) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM comments WHERE status = :status");
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        return $stmt->fetch()['count'];
    } catch (PDOException $e) {
        error_log("خطأ في حساب عدد التعليقات: " . $e->getMessage());
        return 0;
    }
}
?>
