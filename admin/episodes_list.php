<?php
/**
 * صفحة إدارة الحلقات - عرض قائمة الحلقات
 */
require_once '../includes/init.php';
require_once 'auth_check.php';

// التحقق من وجود معرف الأنمي
$animeId = null;
$anime = null;

if (isset($_GET['anime_id']) && is_numeric($_GET['anime_id'])) {
    $animeId = (int)$_GET['anime_id'];
    
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
    } catch (PDOException $e) {
        error_log("خطأ في الحصول على معلومات الأنمي: " . $e->getMessage());
        $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
        redirect('anime_list.php');
    }
}

// حذف حلقة
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $episodeId = (int)$_GET['delete'];
    
    // التحقق من صلاحيات المستخدم (يجب أن يكون مشرف أو مدير)
    if ($_SESSION['user_role'] < USER_ROLE_MODERATOR) {
        $_SESSION['error_message'] = 'ليس لديك صلاحية حذف الحلقات';
        redirect('episodes_list.php' . ($animeId ? "?anime_id=$animeId" : ''));
    }
    
    try {
        // الحصول على معلومات الحلقة قبل الحذف
        $stmt = $conn->prepare("SELECT * FROM episodes WHERE id = :id");
        $stmt->bindParam(':id', $episodeId);
        $stmt->execute();
        $episode = $stmt->fetch();
        
        if (!$episode) {
            $_SESSION['error_message'] = 'الحلقة غير موجودة';
            redirect('episodes_list.php' . ($animeId ? "?anime_id=$animeId" : ''));
        }
        
        // حذف الحلقة
        $stmt = $conn->prepare("DELETE FROM episodes WHERE id = :id");
        $stmt->bindParam(':id', $episodeId);
        
        if ($stmt->execute()) {
            // حذف ملفات الحلقة إذا وجدت
            if (!empty($episode['thumbnail']) && file_exists(EPISODE_THUMBNAILS_PATH . '/' . $episode['thumbnail'])) {
                unlink(EPISODE_THUMBNAILS_PATH . '/' . $episode['thumbnail']);
            }
            
            if (!empty($episode['video_path']) && file_exists(VIDEOS_PATH . '/' . $episode['video_path'])) {
                unlink(VIDEOS_PATH . '/' . $episode['video_path']);
            }
            
            $_SESSION['success_message'] = 'تم حذف الحلقة بنجاح';
        } else {
            $_SESSION['error_message'] = 'فشل حذف الحلقة';
        }
    } catch (PDOException $e) {
        error_log("خطأ في حذف الحلقة: " . $e->getMessage());
        $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    }
    
    redirect('episodes_list.php' . ($animeId ? "?anime_id=$animeId" : ''));
}

// البحث والتصفية
$search = sanitizeInput($_GET['search'] ?? '');
$sort = sanitizeInput($_GET['sort'] ?? 'episode_number');
$order = sanitizeInput($_GET['order'] ?? 'ASC');

// التحقق من صحة معايير الفرز
$allowedSortFields = ['episode_number', 'title', 'duration', 'views', 'release_date', 'created_at'];
$allowedOrderValues = ['ASC', 'DESC'];

if (!in_array($sort, $allowedSortFields)) {
    $sort = 'episode_number';
}

if (!in_array($order, $allowedOrderValues)) {
    $order = 'ASC';
}

// بناء استعلام SQL
$sql = "SELECT e.*, a.title as anime_title FROM episodes e 
        JOIN anime a ON e.anime_id = a.id 
        WHERE 1=1";
$params = [];

if ($animeId) {
    $sql .= " AND e.anime_id = :anime_id";
    $params[':anime_id'] = $animeId;
}

if (!empty($search)) {
    $sql .= " AND (e.title LIKE :search OR a.title LIKE :search)";
    $params[':search'] = "%$search%";
}

$sql .= " ORDER BY " . ($animeId ? "e.$sort $order" : "a.title ASC, e.$sort $order");

// التقسيم إلى صفحات
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$sql .= " LIMIT :offset, :perPage";
$params[':offset'] = $offset;
$params[':perPage'] = $perPage;

try {
    // الحصول على إجمالي عدد الحلقات للتقسيم إلى صفحات
    $countSql = "SELECT COUNT(*) as total FROM episodes e 
                JOIN anime a ON e.anime_id = a.id 
                WHERE 1=1";
    $countParams = [];
    
    if ($animeId) {
        $countSql .= " AND e.anime_id = :anime_id";
        $countParams[':anime_id'] = $animeId;
    }
    
    if (!empty($search)) {
        $countSql .= " AND (e.title LIKE :search OR a.title LIKE :search)";
        $countParams[':search'] = "%$search%";
    }
    
    $countStmt = $conn->prepare($countSql);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalEpisodes = $countStmt->fetch()['total'];
    $totalPages = ceil($totalEpisodes / $perPage);
    
    // الحصول على قائمة الحلقات
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === ':offset' || $key === ':perPage') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $episodesList = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على قائمة الحلقات: " . $e->getMessage());
    $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    $episodesList = [];
    $totalPages = 1;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الحلقات - <?php echo SITE_NAME; ?></title>
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
                    <h1 class="h2">
                        <?php if ($anime): ?>
                            إدارة حلقات: <?php echo $anime['title']; ?>
                        <?php else: ?>
                            إدارة جميع الحلقات
                        <?php endif; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($anime): ?>
                            <a href="episode_add.php?anime_id=<?php echo $animeId; ?>" class="btn btn-sm btn-primary me-2">
                                <i class="fas fa-plus me-1"></i> إضافة حلقة جديدة
                            </a>
                            <a href="anime_list.php" class="btn btn-sm btn-secondary">
                                <i class="fas fa-arrow-right me-1"></i> العودة إلى قائمة الأنمي
                            </a>
                        <?php else: ?>
                            <a href="anime_list.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-list me-1"></i> إدارة الأنمي
                            </a>
                        <?php endif; ?>
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
                            <?php if ($animeId): ?>
                                <input type="hidden" name="anime_id" value="<?php echo $animeId; ?>">
                            <?php endif; ?>
                            
                            <div class="col-md-4">
                                <label for="search" class="form-label">بحث</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo $search; ?>" placeholder="ابحث عن عنوان الحلقة...">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="sort" class="form-label">ترتيب حسب</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="episode_number" <?php echo $sort === 'episode_number' ? 'selected' : ''; ?>>رقم الحلقة</option>
                                    <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>العنوان</option>
                                    <option value="duration" <?php echo $sort === 'duration' ? 'selected' : ''; ?>>المدة</option>
                                    <option value="views" <?php echo $sort === 'views' ? 'selected' : ''; ?>>المشاهدات</option>
                                    <option value="release_date" <?php echo $sort === 'release_date' ? 'selected' : ''; ?>>تاريخ الإصدار</option>
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
                                <a href="episodes_list.php<?php echo $animeId ? "?anime_id=$animeId" : ''; ?>" class="btn btn-secondary">إعادة تعيين</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- جدول الحلقات -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">قائمة الحلقات</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>الصورة المصغرة</th>
                                        <?php if (!$animeId): ?>
                                            <th>الأنمي</th>
                                        <?php endif; ?>
                                        <th>رقم الحلقة</th>
                                        <th>العنوان</th>
                                        <th>المدة</th>
                                        <th>المشاهدات</th>
                                        <th>تاريخ الإصدار</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($episodesList)): ?>
                                        <?php foreach ($episodesList as $episode): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <?php if (!empty($episode['thumbnail'])): ?>
                                                        <img src="<?php echo EPISODE_THUMBNAILS_URL . '/' . $episode['thumbnail']; ?>" alt="<?php echo $episode['title']; ?>" class="img-thumbnail" width="80">
                                                    <?php else: ?>
                                                        <span class="text-muted">لا توجد صورة</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if (!$animeId): ?>
                                                    <td>
                                                        <a href="episodes_list.php?anime_id=<?php echo $episode['anime_id']; ?>">
                                                            <?php echo $episode['anime_title']; ?>
                                                        </a>
                                                    </td>
                                                <?php endif; ?>
                                                <td><?php echo $episode['episode_number']; ?></td>
                                                <td><?php echo $episode['title'] ?: 'الحلقة ' . $episode['episode_number']; ?></td>
                                                <td>
                                                    <?php
                                                    $minutes = floor($episode['duration'] / 60);
                                                    $seconds = $episode['duration'] % 60;
                                                    echo sprintf('%02d:%02d', $minutes, $seconds);
                                                    ?>
                                                </td>
                                                <td><?php echo number_format($episode['views']); ?></td>
                                                <td><?php echo $episode['release_date']; ?></td>
                                                <td>
                                                    <a href="episode_edit.php?id=<?php echo $episode['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $episode['id']; ?>)" class="btn btn-danger btn-sm" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?php echo $animeId ? '7' : '8'; ?>" class="text-center">لا توجد حلقات متاحة</td>
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
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?><?php echo $animeId ? "&anime_id=$animeId" : ''; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?><?php echo $animeId ? "&anime_id=$animeId" : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?><?php echo $animeId ? "&anime_id=$animeId" : ''; ?>" aria-label="Next">
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
                    هل أنت متأكد من حذف هذه الحلقة؟ سيتم حذف جميع التعليقات المرتبطة بها.
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
            document.getElementById('confirmDeleteBtn').href = 'episodes_list.php?delete=' + id + '<?php echo $animeId ? "&anime_id=$animeId" : ''; ?>';
            modal.show();
        }
    </script>
</body>
</html>
