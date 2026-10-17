<?php
/**
 * ملف الإعدادات العامة للموقع
 * يحتوي على الثوابت والإعدادات المستخدمة في جميع أنحاء الموقع
 */

// معلومات الموقع الأساسية
define('SITE_NAME', 'موقع الأنمي');
define('SITE_URL', 'http://localhost/anime_website');
define('ADMIN_URL', SITE_URL . '/admin');

// مسارات المجلدات
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('PAGES_PATH', ROOT_PATH . '/pages');
define('ADMIN_PATH', ROOT_PATH . '/admin');

// مسارات الأصول
define('ASSETS_URL', SITE_URL . '/assets');
define('CSS_URL', ASSETS_URL . '/css');
define('JS_URL', ASSETS_URL . '/js');
define('IMAGES_URL', ASSETS_URL . '/images');

// مسارات التحميل
define('UPLOADS_PATH', ROOT_PATH . '/assets/uploads');
define('UPLOADS_URL', ASSETS_URL . '/uploads');
define('ANIME_COVERS_PATH', UPLOADS_PATH . '/anime_covers');
define('ANIME_COVERS_URL', UPLOADS_URL . '/anime_covers');
define('EPISODE_THUMBNAILS_PATH', UPLOADS_PATH . '/episode_thumbnails');
define('EPISODE_THUMBNAILS_URL', UPLOADS_URL . '/episode_thumbnails');
define('VIDEOS_PATH', UPLOADS_PATH . '/videos');
define('VIDEOS_URL', UPLOADS_URL . '/videos');
define('USER_PROFILES_PATH', UPLOADS_PATH . '/user_profiles');
define('USER_PROFILES_URL', UPLOADS_URL . '/user_profiles');

// إعدادات الجلسة
define('SESSION_NAME', 'anime_session');
define('SESSION_LIFETIME', 86400); // 24 ساعة بالثواني

// إعدادات الأمان
define('SALT', 'ChangeThisToARandomString123!@#');
define('PASSWORD_PEPPER', 'AnotherRandomStringForExtraSecurity456!@#');

// أنواع المستخدمين
define('USER_ROLE_VISITOR', 0);
define('USER_ROLE_USER', 1);
define('USER_ROLE_MODERATOR', 2);
define('USER_ROLE_ADMIN', 3);

// إعدادات التحميل
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5 ميجابايت
define('MAX_VIDEO_SIZE', 500 * 1024 * 1024); // 500 ميجابايت
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm']);

// إعدادات التصفح
define('ITEMS_PER_PAGE', 12);
