<?php

// Moodle bootstrap
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->dirroot . '/user/lib.php');

// 📥 البيانات القادمة من Laravel
$username = required_param('username', PARAM_RAW);
$redirect = optional_param('redirect', '/', PARAM_RAW);

// ⚠️ تحقق من وجود المستخدم
$user = get_complete_user_data('username', $username);
if (!$user) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

// 🧹 اقتل أي جلسة حالية (اختياري)
\core\session\manager::kill_user_sessions($user->id);

// ✅ سجل الدخول
\core\session\manager::set_user($user);

// ✅ احسب الوقت الحالي (اختياري logging)
$time = date('Y-m-d H:i:s');
error_log("[Moodle Auto Login] ✅ Logged in {$user->username} at {$time}");

// ✅ إعادة التوجيه إن طلب ذلك
if (!empty($redirect)) {
    redirect(new moodle_url($redirect));
    exit;
}

// ✅ رد JSON (لو ما في redirect)
echo json_encode([
    'status' => 'success',
    'user' => [
        'id'       => $user->id,
        'username' => $user->username,
        'email'    => $user->email,
        'fullname' => fullname($user),
    ]
]);
exit;
