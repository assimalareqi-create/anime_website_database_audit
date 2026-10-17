<?php
/**
 * الصفحة الرئيسية للوحة التحكم
 */
require_once '../includes/init.php';
require_once 'auth_check.php';

// الحصول على إحصائيات الموقع
try {
    // عدد الأنمي
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM anime");
    $stmt->execute();
    $animeCount = $stmt->fetch()['total'];
    
    // عدد الحلقات
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM episodes");
    $stmt->execute();
    $episodesCount = $stmt->fetch()['total'];
    
    // عدد المستخدمين
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = :role");
    $role = USER_ROLE_USER;
    $stmt->bindParam(':role', $role);
    $stmt->execute();
    $usersCount = $stmt->fetch()['total'];
    
    // عدد التعليقات
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM comments");
    $stmt->execute();
    $commentsCount = $stmt->fetch()['total'];
    
    // أحدث الأنمي المضافة
    $stmt = $conn->prepare("SELECT * FROM anime ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $latestAnime = $stmt->fetchAll();
    
    // أحدث المستخدمين المسجلين
    $stmt = $conn->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $latestUsers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في الحصول على إحصائيات الموقع: " . $e->getMessage());
    $_SESSION['error_message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.';
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - <?php echo SITE_NAME; ?></title>
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
                    <h1 class="h2">لوحة التحكم</h1>
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
                
                <!-- بطاقات الإحصائيات -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-right-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            إجمالي الأنمي</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $animeCount; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-film fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-right-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            إجمالي الحلقات</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $episodesCount; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-video fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-right-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            إجمالي المستخدمين</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $usersCount; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-right-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            إجمالي التعليقات</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $commentsCount; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-comments fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- أحدث الأنمي والمستخدمين -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">أحدث الأنمي المضافة</h6>
                                <a href="anime_list.php" class="btn btn-sm btn-primary">عرض الكل</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>العنوان</th>
                                                <th>الحالة</th>
                                                <th>تاريخ الإضافة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($latestAnime)): ?>
                                                <?php foreach ($latestAnime as $anime): ?>
                                                    <tr>
                                                        <td><?php echo $anime['title']; ?></td>
                                                        <td><?php echo $anime['status']; ?></td>
                                                        <td><?php echo formatDate($anime['created_at'], 'Y-m-d'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">لا يوجد أنمي مضاف حتى الآن</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">أحدث المستخدمين المسجلين</h6>
                                <a href="users_list.php" class="btn btn-sm btn-primary">عرض الكل</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>اسم المستخدم</th>
                                                <th>البريد الإلكتروني</th>
                                                <th>تاريخ التسجيل</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($latestUsers)): ?>
                                                <?php foreach ($latestUsers as $user): ?>
                                                    <tr>
                                                        <td><?php echo $user['username']; ?></td>
                                                        <td><?php echo $user['email']; ?></td>
                                                        <td><?php echo formatDate($user['created_at'], 'Y-m-d'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">لا يوجد مستخدمين مسجلين حتى الآن</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include_once 'templates/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo JS_URL; ?>/admin.js"></script>
</body>
</html>
