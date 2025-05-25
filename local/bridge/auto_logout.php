<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/moodlelib.php');

$username = optional_param('username', '', PARAM_RAW);
$redirect = optional_param('redirect', '/', PARAM_RAW);

if (!empty($username)) {
    $user = get_complete_user_data('username', $username);
    if ($user) {
        \core\session\manager::kill_user_sessions($user->id);
    }
}

if (!empty($redirect)) {
    redirect(new moodle_url($redirect));
} else {
    echo "User session ended in Moodle.";
}
