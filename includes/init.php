<?php
/**
 * ملف تهيئة الموقع
 * يتم استدعاؤه في بداية كل صفحة
 */

// استدعاء ملفات الإعدادات والوظائف
require_once  ('C:\xampp\htdocs\anime_website\config\config.php');
require_once  ('C:\xampp\htdocs\anime_website\config\database.php');
require_once ('C:\xampp\htdocs\anime_website\includes\functions.php');

// بدء الجلسة
startSession();

// إنشاء اتصال قاعدة البيانات
$conn = getDbConnection();

// التحقق من وجود اتصال بقاعدة البيانات
if (!$conn) {
    die("فشل الاتصال بقاعدة البيانات. يرجى التحقق من إعدادات الاتصال.");
}
