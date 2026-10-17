<?php
/**
 * صفحة إدارة المستخدمين - عرض قائمة المستخدمين
 */
require_once '../includes/init.php';
require_once 'auth_check.php';

// التحقق من صلاحيات المستخدم (يجب أن يكون مدير)
if ($_SESSION['user_role'] < USER_ROLE_ADMIN) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية إدارة المستخدمين';
    redirect('index.php');
}

// حذف مستخدم
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = (int)$_GET['delete'];
    
    // لا يمكن للمستخدم حذف نفسه
    if ($userId === $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'لا يمكنك حذف حسابك الخاص';
        redirect('users_list.php');
    }
    
    try {
        // الحصول على معلومات المستخدم قبل الحذف
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['error_message'] = 'المستخدم غير موجود';
            redirect('users_list.php');
        }
        
        // لا يمكن حذف المدير الرئيسي (المستخدم رقم 1)
        if ($userId === 1) {
            $_SESSION['error_message'] = 'لا يمكن حذف المدير الرئيسي';
            redirect('users_list.php');
        }
        
        // حذف المستخدم
        $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId);
        
        if ($stmt->execute()) {
            // حذف صورة الملف الشخصي إذا وجدت
            if (!empty($user['profile_picture']) && file_exists(USER_PROFILES_PATH . '/' . $user['profile_picture'])) {
                unlink(USER_PROFILES_PATH . '/' . $user['profile_picture']);
            }
            
            $_SESSION['success_message'] = 'تم حذف المستخدم بنجاح';
        } else {
            $_SESSION['error_message'] = 'فشل حذف المستخدم';
        }
    } catch (PDOException $e) {
        error_log("خطأ في حذف المستخدم: " . $e->getMessage());
        $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    }
    
    redirect('users_list.php');
}

// تغيير دور المستخدم
if (isset($_GET['change_role']) && is_numeric($_GET['change_role']) && isset($_GET['role']) && is_numeric($_GET['role'])) {
    $userId = (int)$_GET['change_role'];
    $newRole = (int)$_GET['role'];
    
    // التحقق من صحة الدور
    if ($newRole < USER_ROLE_USER || $newRole > USER_ROLE_ADMIN) {
        $_SESSION['error_message'] = 'دور المستخدم غير صالح';
        redirect('users_list.php');
    }
    
    // لا يمكن للمستخدم تغيير دوره الخاص
    if ($userId === $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'لا يمكنك تغيير دورك الخاص';
        redirect('users_list.php');
    }
    
    // لا يمكن تغيير دور المدير الرئيسي (المستخدم رقم 1)
    if ($userId === 1) {
        $_SESSION['error_message'] = 'لا يمكن تغيير دور المدير الرئيسي';
        redirect('users_list.php');
    }
    
    try {
        // تحديث دور المستخدم
        $stmt = $conn->prepare("UPDATE users SET role = :role WHERE id = :id");
        $stmt->bindParam(':role', $newRole);
        $stmt->bindParam(':id', $userId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'تم تغيير دور المستخدم بنجاح';
        } else {
            $_SESSION['error_message'] = 'فشل تغيير دور المستخدم';
        }
    } catch (PDOException $e) {
        error_log("خطأ في تغيير دور المستخدم: " . $e->getMessage());
        $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    }
    
    redirect('users_list.php');
}

// البحث والتصفية
$search = sanitizeInput($_GET['search'] ?? '');
$role = isset($_GET['role']) && is_numeric($_GET['role']) ? (int)$_GET['role'] : null;
$sort = sanitizeInput($_GET['sort'] ?? 'created_at');
$order = sanitizeInput($_GET['order'] ?? 'DESC');

// التحقق من صحة معايير الفرز
$allowedSortFields = ['username', 'email', 'role', 'created_at'];
$allowedOrderValues = ['ASC', 'DESC'];

if (!in_array($sort, $allowedSortFields)) {
    $sort = 'created_at';
}

if (!in_array($order, $allowedOrderValues)) {
    $order = 'DESC';
}

// بناء استعلام SQL
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (username LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($role !== null) {
    $sql .= " AND role = :role";
    $params[':role'] = $role;
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
    // الحصول على إجمالي عدد المستخدمين للتقسيم إلى صفحات
    $countSql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
    $countParams = [];
    
    if (!empty($search)) {
        $countSql .= " AND (username LIKE :search OR email LIKE :search)";
        $countParams[':search'] = "%$search%";
    }
    
    if ($role !== null) {
        $countSql .= " AND role = :role";
        $countParams[':role'] = $role;
    }
    
    $countStmt = $conn->prepare($countSql);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalUsers = $countStmt->fetch()['total'];
    $totalPages = ceil($totalUsers / $perPage);
    
    // الحصول على قائمة المستخدمين
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === ':offset' || $key === ':perPage') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $usersList = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على قائمة المستخدمين: " . $e->getMessage());
    $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
    $usersList = [];
    $totalPages = 1;
}

// دالة لعرض اسم الدور
function getRoleName($role) {
    switch ($role) {
        case USER_ROLE_VISITOR:
            return 'زائر';
        case USER_ROLE_USER:
            return 'مستخدم';
        case USER_ROLE_MODERATOR:
            return 'مشرف';
        case USER_ROLE_ADMIN:
            return 'مدير';
        default:
            return 'غير معروف';
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين - <?php echo SITE_NAME; ?></title>
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
                    <h1 class="h2">إدارة المستخدمين</h1>
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
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo $search; ?>" placeholder="ابحث عن اسم المستخدم أو البريد الإلكتروني...">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="role" class="form-label">الدور</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="">الكل</option>
                                    <option value="<?php echo USER_ROLE_USER; ?>" <?php echo $role === USER_ROLE_USER ? 'selected' : ''; ?>>مستخدم</option>
                                    <option value="<?php echo USER_ROLE_MODERATOR; ?>" <?php echo $role === USER_ROLE_MODERATOR ? 'selected' : ''; ?>>مشرف</option>
                                    <option value="<?php echo USER_ROLE_ADMIN; ?>" <?php echo $role === USER_ROLE_ADMIN ? 'selected' : ''; ?>>مدير</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="sort" class="form-label">ترتيب حسب</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>اسم المستخدم</option>
                                    <option value="email" <?php echo $sort === 'email' ? 'selected' : ''; ?>>البريد الإلكتروني</option>
                                    <option value="role" <?php echo $sort === 'role' ? 'selected' : ''; ?>>الدور</option>
                                    <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>تاريخ التسجيل</option>
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
                                <a href="users_list.php" class="btn btn-secondary">إعادة تعيين</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- جدول المستخدمين -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">قائمة المستخدمين</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>الصورة</th>
                                        <th>اسم المستخدم</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>الدور</th>
                                        <th>تاريخ التسجيل</th>
                                        <th>آخر تحديث</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($usersList)): ?>
                                        <?php foreach ($usersList as $user): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <?php if (!empty($user['profile_picture'])): ?>
                                                        <img src="<?php echo USER_PROFILES_URL . '/' . $user['profile_picture']; ?>" alt="<?php echo $user['username']; ?>" class="img-thumbnail rounded-circle" width="50">
                                                    <?php else: ?>
                                                        <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $user['username']; ?></td>
                                                <td><?php echo $user['email']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $user['role'] == USER_ROLE_ADMIN ? 'danger' : 
                                                            ($user['role'] == USER_ROLE_MODERATOR ? 'warning' : 'primary'); 
                                                    ?>">
                                                        <?php echo getRoleName($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($user['created_at'], 'Y-m-d H:i'); ?></td>
                                                <td><?php echo formatDate($user['updated_at'], 'Y-m-d H:i'); ?></td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $user['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                            تغيير الدور
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $user['id']; ?>">
                                                            <li><a class="dropdown-item <?php echo $user['role'] == USER_ROLE_USER ? 'active' : ''; ?>" href="users_list.php?change_role=<?php echo $user['id']; ?>&role=<?php echo USER_ROLE_USER; ?>">مستخدم</a></li>
                                                            <li><a class="dropdown-item <?php echo $user['role'] == USER_ROLE_MODERATOR ? 'active' : ''; ?>" href="users_list.php?change_role=<?php echo $user['id']; ?>&role=<?php echo USER_ROLE_MODERATOR; ?>">مشرف</a></li>
                                                            <li><a class="dropdown-item <?php echo $user['role'] == USER_ROLE_ADMIN ? 'active' : ''; ?>" href="users_list.php?change_role=<?php echo $user['id']; ?>&role=<?php echo USER_ROLE_ADMIN; ?>">مدير</a></li>
                                                        </ul>
                                                    </div>
                                                    
                                                    <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm mt-1" title="تعديل">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <?php if ($user['id'] !== 1 && $user['id'] !== $_SESSION['user_id']): ?>
                                                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $user['id']; ?>)" class="btn btn-danger btn-sm mt-1" title="حذف">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-danger btn-sm mt-1" disabled title="لا يمكن حذف هذا المستخدم">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">لا توجد بيانات متاحة</td>
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
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role; ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role; ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role; ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" aria-label="Next">
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
                    هل أنت متأكد من حذف هذا المستخدم؟ سيتم حذف جميع البيانات المرتبطة به.
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
            document.getElementById('confirmDeleteBtn').href = 'users_list.php?delete=' + id;
            modal.show();
        }
    </script>
</body>
</html>
