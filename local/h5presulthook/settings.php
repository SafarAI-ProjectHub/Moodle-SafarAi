<?php
// local/h5presulthook/settings.php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_h5presulthook', get_string('pluginname', 'local_h5presulthook'));

    $settings->add(new admin_setting_configtext(
        'local_h5presulthook/laravel_endpoint',
        'Laravel API Endpoint',
        'Enter the full URL to your Laravel endpoint for receiving H5P results.',
        'https://your-laravel.com/api/h5p/result',
        PARAM_URL
    ));

    $ADMIN->add('localplugins', $settings);
}
