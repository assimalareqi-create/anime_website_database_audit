<?php
/**
 * قالب الفوتر للموقع
 */
?>
<footer class="main-footer bg-dark text-white py-5 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <h3 class="h5 mb-3">عن <?php echo SITE_NAME; ?></h3>
                <p>موقع <?php echo SITE_NAME; ?> هو منصة متخصصة في عرض الأنمي بطريقة احترافية ومبتكرة، يوفر للمستخدمين تجربة مشاهدة مميزة مع إمكانية البحث المتقدم والتصنيف والتعليقات وقوائم المتابعة.</p>
                <div class="social-links mt-3">
                    <a href="#" class="me-2 text-white"><i class="fab fa-facebook-f fa-lg"></i></a>
                    <a href="#" class="me-2 text-white"><i class="fab fa-twitter fa-lg"></i></a>
                    <a href="#" class="me-2 text-white"><i class="fab fa-instagram fa-lg"></i></a>
                    <a href="#" class="me-2 text-white"><i class="fab fa-youtube fa-lg"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-discord fa-lg"></i></a>
                </div>
            </div>
            
            <div class="col-md-4 mb-4 mb-md-0">
                <h3 class="h5 mb-3">روابط سريعة</h3>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?php echo SITE_URL; ?>" class="text-white">الرئيسية</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/anime_list.php" class="text-white">قائمة الأنمي</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/episodes.php" class="text-white">أحدث الحلقات</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/genres.php" class="text-white">التصنيفات</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="<?php echo SITE_URL; ?>/watchlist.php" class="text-white">قائمة المتابعة</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo SITE_URL; ?>/pages/login.php" class="text-white">تسجيل الدخول</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/pages/register.php" class="text-white">إنشاء حساب</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="col-md-4">
                <h3 class="h5 mb-3">اتصل بنا</h3>
                <ul class="list-unstyled footer-contact">
                    <li><i class="fas fa-envelope me-2"></i> info@<?php echo str_replace('http://', '', str_replace('https://', '', SITE_URL)); ?></li>
                    <li><i class="fas fa-phone me-2"></i> +123 456 7890</li>
                    <li><i class="fas fa-map-marker-alt me-2"></i> العنوان، المدينة، البلد</li>
                </ul>
                
                <div class="newsletter mt-3">
                    <h4 class="h6">اشترك في النشرة الإخبارية</h4>
                    <form class="mt-2">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="البريد الإلكتروني" aria-label="البريد الإلكتروني">
                            <button class="btn btn-primary" type="button">اشتراك</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <hr class="mt-4 mb-3">
        
        <div class="row">
            <div class="col-md-6">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. جميع الحقوق محفوظة.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <ul class="list-inline mb-0">
                    <li class="list-inline-item"><a href="#" class="text-white">سياسة الخصوصية</a></li>
                    <li class="list-inline-item"><a href="#" class="text-white">شروط الاستخدام</a></li>
                    <li class="list-inline-item"><a href="#" class="text-white">الأسئلة الشائعة</a></li>
                </ul>
            </div>
        </div>
    </div>
</footer>
