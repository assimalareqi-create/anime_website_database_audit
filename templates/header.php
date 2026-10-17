<?php
/**
 * قالب الهيدر للموقع
 */
?>
<header class="main-header">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-play-circle me-2"></i> <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>">الرئيسية</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/anime_list.php">قائمة الأنمي</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/episodes.php">أحدث الحلقات</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            التصنيفات
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <?php
                            // عرض أهم التصنيفات
                            try {
                                $stmt = $conn->prepare("
                                    SELECT g.*, COUNT(ag.anime_id) as anime_count
                                    FROM genres g
                                    LEFT JOIN anime_genres ag ON g.id = ag.genre_id
                                    GROUP BY g.id
                                    ORDER BY anime_count DESC
                                    LIMIT 10
                                ");
                                $stmt->execute();
                                $topGenres = $stmt->fetchAll();
                                
                                foreach ($topGenres as $genre) {
                                    echo '<li><a class="dropdown-item" href="' . SITE_URL . '/anime_list.php?genre_id=' . $genre['id'] . '">' . $genre['name'] . '</a></li>';
                                }
                            } catch (PDOException $e) {
                                error_log("خطأ في الحصول على التصنيفات: " . $e->getMessage());
                            }
                            ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/genres.php">جميع التصنيفات</a></li>
                        </ul>
                    </li>
                </ul>
                
                <form class="d-flex me-2" action="<?php echo SITE_URL; ?>/anime_list.php" method="GET">
                    <input class="form-control me-2" type="search" name="search" placeholder="ابحث عن أنمي..." aria-label="Search">
                    <button class="btn btn-outline-light" type="submit">بحث</button>
                </form>
                
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i> <?php echo $_SESSION['username']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <?php if ($_SESSION['user_role'] >= USER_ROLE_MODERATOR): ?>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/index.php"><i class="fas fa-cog me-1"></i> لوحة التحكم</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/profile.php"><i class="fas fa-user-circle me-1"></i> الملف الشخصي</a></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/watchlist.php"><i class="fas fa-bookmark me-1"></i> قائمة المتابعة</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/pages/logout.php"><i class="fas fa-sign-out-alt me-1"></i> تسجيل الخروج</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>/pages/login.php">تسجيل الدخول</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>/pages/register.php">إنشاء حساب</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
