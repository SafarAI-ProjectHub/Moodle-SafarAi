<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Prints an instance of mod_h5pactivity.
 *
 * @package     mod_h5pactivity
 * @copyright   2020 Ferran Recio <ferran@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_h5pactivity\local\manager;
use core_h5p\factory;
use core_h5p\player;
use core_h5p\helper;

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$token = optional_param('token', '', PARAM_RAW);
$returnurl = optional_param('returnurl', '', PARAM_URL);
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'h5pactivity');
require_login($course, true, $cm);

$manager = manager::create_from_coursemodule($cm);
$instance = $manager->get_instance();
$context = $manager->get_context();
$moduleinstance = $manager->get_instance();
$context = $manager->get_context();

// Trigger module viewed event and completion.
$manager->set_module_viewed($course);

// Convert display options to a valid object.
$factory = new factory();
$core = $factory->get_core();
$config = core_h5p\helper::decode_display_options($core, $moduleinstance->displayoptions);

// ✅ تعطيل عرض التفاصيل بعد النشاط
$config->frame = false; // بدون الإطار
$config->export = false;
$config->embed = false;
$config->copyright = false;
$config->icon = false;

// Instantiate player.
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
$file = reset($files);
$fileurl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                    $file->get_filearea(), $file->get_itemid(), $file->get_filepath(),
                    $file->get_filename(), false);

$PAGE->set_url('/mod/h5pactivity/view.php', ['id' => $cm->id]);

$shortname = format_string($course->shortname, true, ['context' => $context]);
$pagetitle = strip_tags($shortname.': '.format_string($moduleinstance->name));
$PAGE->set_title(format_string($pagetitle));

$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo '<style>
    body { margin: 0; overflow: hidden; background-color: #fff; }
    .h5p-iframe-wrapper { border: none !important; }
</style>';

// Only non-guest users without permission to submit can see the warning messages (typically a teacher or a content creator).
if (!$manager->can_submit() && !isguestuser()) {
    // Show preview mode message.
    $message = get_string('previewmode', 'mod_h5pactivity');
    echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_INFO, false);

    // If tracking is disabled, show a warning.
    if (!$manager->is_tracking_enabled()) {
        if (has_capability('moodle/course:manageactivities', $context)) {
            $url = new moodle_url('/course/modedit.php', ['update' => $cm->id]);
            $message = get_string('trackingdisabled_enable', 'mod_h5pactivity', $url->out());
        } else {
            $message = get_string('trackingdisabled', 'mod_h5pactivity');
        }
        echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_WARNING);
    }
}

$extraactions = [];

if ($manager->can_view_all_attempts() && $manager->is_tracking_enabled()) {
    $extraactions[] = new action_link(
        new moodle_url('/mod/h5pactivity/report.php', ['id' => $cm->id]),
        get_string('viewattempts', 'mod_h5pactivity', $manager->count_attempts()),
        null,
        null,
        new pix_icon('i/chartbar', '', 'core')
    );
}

echo player::display($fileurl, $config, true, 'mod_h5pactivity', true, $extraactions);

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
echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>'; // تأكد من تحميل jQuery أو استخدم fetch
echo '<script>
    // H5P xAPI Tracking
    (function() {
        if (H5P && H5P.externalDispatcher) {
            H5P.externalDispatcher.on("xAPI", function(event) {
                if (event.getVerb() && event.getVerb().display["en-US"] === "completed") {
                    const statement = event.data.statement;
                    const userId = ' . $USER->id . ';
                    const activityId = ' . $cm->id . ';
                    const h5pactivityId = ' . $moduleinstance->id . ';
                    const score = statement.result ? statement.result.score.raw : null;
                    const duration = statement.result ? statement.result.duration : null;
                    const xapiRaw = JSON.stringify(statement);

                    console.log("📤 Sending H5P completion to Laravel...");
                    
                    // 📨 AJAX إرسال البيانات إلى Laravel Webhook
                    $.ajax({
                        url: "https://.safarai.org/api/h5p/result",  // 🔥 ضع هنا رابط Webhook
                        method: "POST",
                        data: JSON.stringify({
                            activity_id: activityId,
                            h5pactivity_id: h5pactivityId,
                            user_id: userId,
                            score: score,
                            duration: duration,
                            xapi_data: JSON.parse(xapiRaw)
                        }),
                        contentType: "application/json",
                        success: function(response) {
                            console.log("✅ H5P Result sent to Laravel successfully.", response);
                        },
                        error: function(err) {
                            console.error("❌ Failed to send H5P result to Laravel.", err);
                        }
                    });
                }
            });
        }
    })();
</script>';