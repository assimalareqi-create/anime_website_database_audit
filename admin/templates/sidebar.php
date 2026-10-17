<?php
/**
 * قالب الشريط الجانبي للوحة التحكم
 */
?>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    لوحة التحكم
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'anime_list.php' || basename($_SERVER['PHP_SELF']) == 'anime_add.php' || basename($_SERVER['PHP_SELF']) == 'anime_edit.php' ? 'active' : ''; ?>" href="anime_list.php">
                    <i class="fas fa-film me-2"></i>
                    إدارة الأنمي
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'episodes_list.php' || basename($_SERVER['PHP_SELF']) == 'episode_add.php' || basename($_SERVER['PHP_SELF']) == 'episode_edit.php' ? 'active' : ''; ?>" href="episodes_list.php">
                    <i class="fas fa-video me-2"></i>
                    إدارة الحلقات
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'genres_list.php' || basename($_SERVER['PHP_SELF']) == 'genre_add.php' || basename($_SERVER['PHP_SELF']) == 'genre_edit.php' ? 'active' : ''; ?>" href="genres_list.php">
                    <i class="fas fa-tags me-2"></i>
                    إدارة التصنيفات
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users_list.php' || basename($_SERVER['PHP_SELF']) == 'user_edit.php' ? 'active' : ''; ?>" href="users_list.php">
                    <i class="fas fa-users me-2"></i>
                    إدارة المستخدمين
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'comments_list.php' ? 'active' : ''; ?>" href="comments_list.php">
                    <i class="fas fa-comments me-2"></i>
                    إدارة التعليقات
                </a>
            </li>
        </ul>
        
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>الإعدادات</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog me-2"></i>
                    إعدادات الموقع
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="<?php echo SITE_URL; ?>/pages/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    تسجيل الخروج
                </a>
            </li>
        </ul>
    </div>
</nav>
