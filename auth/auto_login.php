<?php
// سكربت تسجيل دخول صامت للمستخدم بناءً على الـ username فقط

require_once(__DIR__ . '/../config.php');

$username = required_param('username', PARAM_ALPHANUMEXT); // يُمرر من Laravel عبر الرابط
$redirect = optional_param('redirect', '/', PARAM_RAW);    // رابط إعادة التوجيه بعد الدخول

if ($user = get_complete_user_data('username', $username)) {
    // تسجيل الدخول الفعلي
    complete_user_login($user);

    // إعادة التوجيه بعد النجاح
    redirect(new moodle_url($redirect));
} else {
    // فشل في جلب المستخدم
    echo "⚠️ User not found or login failed.";
}
