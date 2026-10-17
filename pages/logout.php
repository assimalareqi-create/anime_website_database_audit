<?php
/**
 * صفحة تسجيل الخروج
 */
require_once '../includes/init.php';

// إنهاء الجلسة
endSession();

// إعادة التوجيه إلى صفحة تسجيل الدخول
redirect(SITE_URL . '/pages/login.php');
