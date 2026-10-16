<?php
/**
 * صفحة التحقق من الصلاحيات
 * يتم استدعاؤها في بداية كل صفحة في لوحة التحكم
 */

// التحقق من تسجيل دخول المستخدم
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    // حفظ الصفحة الحالية للعودة إليها بعد تسجيل الدخول
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect(SITE_URL . '/pages/login.php');
}

// التحقق من صلاحيات المستخدم (يجب أن يكون مشرف أو مدير)
if ($_SESSION['user_role'] < USER_ROLE_MODERATOR) {
    // عرض رسالة خطأ وإعادة التوجيه إلى الصفحة الرئيسية
    $_SESSION['error_message'] = 'ليس لديك صلاحية الوصول إلى لوحة التحكم';
    redirect(SITE_URL);
}
