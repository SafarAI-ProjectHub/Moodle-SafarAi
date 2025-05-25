<?php

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use mod_h5pactivity\local\manager;
use core_h5p\factory;
use core_h5p\player;
use core_h5p\helper;

$id = required_param('id', PARAM_INT); // cmid
$userid = optional_param('userid', 0, PARAM_INT);
$token = optional_param('token', '', PARAM_RAW);
$returnurl = optional_param('returnurl', '', PARAM_URL);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'h5pactivity');
require_login($course, true, $cm);

$manager = manager::create_from_coursemodule($cm);
$instance = $manager->get_instance();
$context = $manager->get_context();

$factory = new factory();
$core = $factory->get_core();
$config = helper::decode_display_options($core, $instance->displayoptions);

// ✅ تعطيل عرض التفاصيل بعد النشاط
$config->showresults = false;
$config->frame = false; // بدون الإطار
$config->export = false;
$config->embed = false;
$config->copyright = false;
$config->icon = false;

$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
$file = reset($files);
$fileurl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                $file->get_filearea(), $file->get_itemid(), $file->get_filepath(),
                $file->get_filename(), false);

$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->set_url('/mod/h5pactivity/externalview.php', ['id' => $id]);
$PAGE->set_title('');
$PAGE->set_heading('');

echo $OUTPUT->header();

// ✅ عرض النشاط فقط (بدون أي عناصر إضافية)
echo '<style>
    body { margin: 0; overflow: hidden; background-color: #fff; }
    .h5p-iframe-wrapper { border: none !important; }
</style>';

echo player::display($fileurl, $config, true, 'mod_h5pactivity', false);

echo $OUTPUT->footer();

// ✅ CSS: إخفاء الإطارات والهوامش وكل شيء خارجي
echo '<style>
    body { margin: 0; overflow: hidden; background-color: #fff; }
    .h5p-iframe-wrapper { border: none !important; }
</style>';

// ✅ JS: منع الرجوع للخلف وتعطيل right-click والتنقل
echo '<script>
    // منع الرجوع باستخدام backspace أو history
    window.history.pushState(null, "", window.location.href);
    window.onpopstate = function() {
        window.history.pushState(null, "", window.location.href);
    };

    // منع زر الرجوع في المتصفح
    window.addEventListener("beforeunload", function (e) {
        e.preventDefault();
        e.returnValue = "";
    });

    // تعطيل كليك يمين
    document.addEventListener("contextmenu", function(e){
        e.preventDefault();
    });

    // إخفاء أزرار/روابط لو ظهرت داخل H5P بطريقة ما (احتياطي)
    document.addEventListener("DOMContentLoaded", function() {
        const links = document.querySelectorAll("a");
        links.forEach(a => {
            a.setAttribute("href", "javascript:void(0)");
            a.style.pointerEvents = "none";
        });
    });
</script>';
