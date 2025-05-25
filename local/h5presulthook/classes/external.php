<?php
// local/h5presulthook/classes/external.php

namespace local_h5presulthook;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/filelib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use core\http_client;

class external extends external_api {

    // 📤 تحديث send_result_parameters لتشمل الحقول الجديدة
    public static function send_result_parameters() {
        return new external_function_parameters([
            'activity_id'    => new external_value(PARAM_INT, 'Course Module ID (cmid)'),
            'h5pactivityid'  => new external_value(PARAM_INT, 'H5P Activity ID'),
            'user_id'        => new external_value(PARAM_INT, 'User ID'),
            'score'          => new external_value(PARAM_FLOAT, 'Raw score (0-100)', VALUE_OPTIONAL),
            'duration'       => new external_value(PARAM_INT, 'Duration in seconds', VALUE_OPTIONAL),
            'raw'            => new external_value(PARAM_RAW, 'xAPI raw JSON result', VALUE_OPTIONAL),
            'attempt'        => new external_value(PARAM_INT, 'Attempt number', VALUE_OPTIONAL),
            'started_at'     => new external_value(PARAM_INT, 'Attempt start time', VALUE_OPTIONAL),
            'finished_at'    => new external_value(PARAM_INT, 'Attempt finish time', VALUE_OPTIONAL),
        ]);
    }

    // 📤 تعديل send_result لتجميع معلومات شاملة تلقائيًا
    public static function send_result($activity_id, $h5pactivityid, $user_id, $score = null, $duration = 60, $raw = '', $attempt = null, $started_at = null, $finished_at = null) {
        global $DB, $CFG;

        // جلب المحاولة الأخيرة لهذا المستخدم والنشاط
        $latest_attempt = $DB->get_record('h5pactivity_attempts', [
            'h5pactivityid' => $h5pactivityid,
            'userid'        => $user_id,
        ], '*', IGNORE_MULTIPLE);

        if (!$latest_attempt) {
            return ['status' => false, 'response' => 'No attempt found'];
        }

        // جلب بيانات xAPI المفصلة
        $xapi_result = $DB->get_record('h5pactivity_attempts_results', [
            'attemptid' => $latest_attempt->id
        ]);

        // جلب معلومات النشاط
        $h5pactivity = $DB->get_record('h5pactivity', ['id' => $h5pactivityid]);

        $payload = [
            'activity_id'    => $activity_id,
            'h5pactivityid'  => $h5pactivityid,
            'user_id'        => $user_id,
            'result' => [
                'attempt_id'      => $latest_attempt->id,
                'attempt_number'  => $latest_attempt->attempt,
                'score'           => $latest_attempt->rawscore,
                'maxscore'        => $latest_attempt->maxscore,
                'scaled'          => $latest_attempt->maxscore ? round($latest_attempt->rawscore / $latest_attempt->maxscore, 2) : null,
                'duration'        => $latest_attempt->duration,
                'completion'      => $latest_attempt->completion,
                'success'         => $latest_attempt->success,
                'started_at'      => $latest_attempt->timecreated,
                'finished_at'     => $latest_attempt->timemodified,
                'subcontent'      => $xapi_result->subcontent ?? null,
                'xapi_data'       => json_decode($xapi_result->rawjson ?? '{}', true),
            ],
            'h5pactivity' => [
                'id'                => $h5pactivityid,
                'name'              => $h5pactivity->name ?? null,
                'course'            => $h5pactivity->course ?? null,
                'intro'             => $h5pactivity->intro ?? null,
                'introformat'       => $h5pactivity->introformat ?? null,
                'grade'             => $h5pactivity->grade ?? null,
                'completionexpected'=> $h5pactivity->completionexpected ?? null,
            ],
        ];

        $endpoint = get_config('local_h5presulthook', 'laravel_endpoint') 
                    ?: 'https://your-laravel.com/api/h5p/result';

        $client = new http_client();
        $headers = ['Content-Type' => 'application/json'];

        $response = $client->post($endpoint, $headers, json_encode($payload));

        return [
            'status'   => true,
            'response' => $response,
        ];
    }

    public static function send_result_returns() {
        return new external_single_structure([
            'status'   => new external_value(PARAM_BOOL, 'Success flag'),
            'response' => new external_value(PARAM_RAW, 'Laravel raw response'),
        ]);
    }

    // 🔥 get_result لتضم كل البيانات الإضافية
    public static function get_result_parameters() {
        return new external_function_parameters([
            'h5pactivityid' => new external_value(PARAM_INT, 'H5P Activity ID'),
            'user_id'       => new external_value(PARAM_INT, 'User ID'),
        ]);
    }

    public static function get_result($h5pactivityid, $user_id) {
        global $DB;

        $attempts = $DB->get_records('h5pactivity_attempts', [
            'h5pactivityid' => $h5pactivityid,
            'userid'        => $user_id,
        ]);

        $results = [];
        foreach ($attempts as $a) {
            $xapi_result = $DB->get_record('h5pactivity_attempts_results', [
                'attemptid' => $a->id
            ]);

            $results[] = [
                'id'           => $a->id,
                'attempt'      => $a->attempt,
                'score'        => $a->rawscore,
                'maxscore'     => $a->maxscore,
                'scaled'       => $a->maxscore ? round($a->rawscore / $a->maxscore, 2) : null,
                'duration'     => $a->duration,
                'completion'   => $a->completion,
                'success'      => $a->success,
                'timecreated'  => $a->timecreated,
                'timemodified' => $a->timemodified,
                'subcontent'   => $xapi_result->subcontent ?? null,
                'xapi_data'    => json_decode($xapi_result->rawjson ?? '{}', true),
            ];
        }

        return ['results' => $results];
    }

    public static function get_result_returns() {
        return new external_single_structure([
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT, 'Attempt ID'),
                    'attempt'      => new external_value(PARAM_INT, 'Attempt number'),
                    'score'        => new external_value(PARAM_FLOAT, 'Raw score (0-100)', VALUE_OPTIONAL),
                    'maxscore'     => new external_value(PARAM_FLOAT, 'Max score', VALUE_OPTIONAL),
                    'scaled'       => new external_value(PARAM_FLOAT, 'Scaled score', VALUE_OPTIONAL),
                    'duration'     => new external_value(PARAM_INT, 'Duration', VALUE_OPTIONAL),
                    'completion'   => new external_value(PARAM_BOOL, 'Completion flag', VALUE_OPTIONAL),
                    'success'      => new external_value(PARAM_BOOL, 'Success flag', VALUE_OPTIONAL),
                    'timecreated'  => new external_value(PARAM_INT, 'Time created'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'subcontent'   => new external_value(PARAM_RAW, 'Subcontent ID', VALUE_OPTIONAL),
                    'xapi_data'    => new external_value(PARAM_RAW, 'Raw xAPI JSON', VALUE_OPTIONAL),
                ])
            )
        ]);
    }
}
