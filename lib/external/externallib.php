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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service related functions
 *
 * @package    core
 * @category   external
 * @copyright  2012 Jerome Mouneyrac <jerome@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.4
 */
class core_external extends external_api {

    /**
     * Format the received string parameters to be sent to the core get_string() function.
     *
     * @param array $stringparams
     * @return object|string
     * @since Moodle 2.4
     */
    public static function format_string_parameters($stringparams) {
        // Check if there are some string params.
        $strparams = new stdClass();
        if (!empty($stringparams)) {
            // There is only one string parameter.
            if (count($stringparams) == 1) {
                $stringparam = array_pop($stringparams);
                if (isset($stringparam['name'])) {
                    $strparams->{$stringparam['name']} = $stringparam['value'];
                } else {
                    // It is a not named string parameter.
                    $strparams = $stringparam['value'];
                }
            } else {
                // There are more than one parameter.
                foreach ($stringparams as $stringparam) {
                    // If a parameter is unnamed throw an exception
                    // unnamed param is only possible if one only param is sent.
                    if (empty($stringparam['name'])) {
                        throw new moodle_exception('unnamedstringparam', 'webservice');
                    }
                    $strparams->{$stringparam['name']} = $stringparam['value'];
                }
            }
        }
        return $strparams;
    }

    /**
     * Returns description of get_string parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.4
     */
    public static function get_string_parameters() {
        return new external_function_parameters([
            'stringid' => new external_value(PARAM_STRINGID, 'string identifier'),
            'component' => new external_value(PARAM_COMPONENT, 'component', VALUE_DEFAULT, 'moodle'),
            'lang' => new external_value(PARAM_LANG, 'lang', VALUE_DEFAULT, null),
            'stringparams' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(
                        PARAM_ALPHANUMEXT,
                        'param name - if the string expect only one $a parameter then don\'t send this field, just send the value.',
                        VALUE_OPTIONAL
                    ),
                    'value' => new external_value(PARAM_RAW, 'param value')
                ]),
                'the definition of a string param (i.e. {$a->name})',
                VALUE_DEFAULT,
                []
            )
        ]);
    }

    /**
     * Return a core get_string() call
     *
     * @param string $stringid string identifier
     * @param string $component string component
     * @param string|null $lang optional lang
     * @param array $stringparams the string params
     * @return string
     * @since Moodle 2.4
     */
    public static function get_string($stringid, $component = 'moodle', $lang = null, $stringparams = []) {
        $params = self::validate_parameters(
            self::get_string_parameters(),
            [
                'stringid' => $stringid,
                'component' => $component,
                'lang' => $lang,
                'stringparams' => $stringparams
            ]
        );

        $stringmanager = get_string_manager();
        return $stringmanager->get_string(
            $params['stringid'],
            $params['component'],
            core_external::format_string_parameters($params['stringparams']),
            $params['lang']
        );
    }

    /**
     * Returns description of get_string() result value
     *
     * @return \core_external\external_description
     * @since Moodle 2.4
     */
    public static function get_string_returns() {
        return new external_value(PARAM_RAW, 'translated string');
    }

    /**
     * Returns description of get_strings parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.4
     */
    public static function get_strings_parameters() {
        return new external_function_parameters([
            'strings' => new external_multiple_structure(
                new external_single_structure([
                    'stringid' => new external_value(PARAM_STRINGID, 'string identifier'),
                    'component' => new external_value(PARAM_COMPONENT, 'component', VALUE_DEFAULT, 'moodle'),
                    'lang' => new external_value(PARAM_LANG, 'lang', VALUE_DEFAULT, null),
                    'stringparams' => new external_multiple_structure(
                        new external_single_structure([
                            'name' => new external_value(
                                PARAM_ALPHANUMEXT,
                                'param name - if the string expect only one $a parameter then don\'t send this field, just send the value.',
                                VALUE_OPTIONAL
                            ),
                            'value' => new external_value(PARAM_RAW, 'param value')
                        ]),
                        'the definition of a string param (i.e. {$a->name})',
                        VALUE_DEFAULT,
                        []
                    )
                ])
            )
        ]);
    }

    /**
     * Return multiple call to core get_string()
     *
     * @param array $strings strings to translate
     * @return array
     * @since Moodle 2.4
     */
    public static function get_strings($strings) {
        $params = self::validate_parameters(self::get_strings_parameters(), ['strings' => $strings]);
        $stringmanager = get_string_manager();

        $translatedstrings = [];
        foreach ($params['strings'] as $string) {
            $lang = !empty($string['lang']) ? $string['lang'] : current_language();
            $translatedstrings[] = [
                'stringid' => $string['stringid'],
                'component' => $string['component'],
                'lang' => $lang,
                'string' => $stringmanager->get_string(
                    $string['stringid'],
                    $string['component'],
                    core_external::format_string_parameters($string['stringparams']),
                    $lang
                )
            ];
        }

        return $translatedstrings;
    }

    /**
     * Returns description of get_strings() result value
     *
     * @return \core_external\external_description
     * @since Moodle 2.4
     */
    public static function get_strings_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'stringid' => new external_value(PARAM_STRINGID, 'string id'),
                'component' => new external_value(PARAM_COMPONENT, 'string component'),
                'lang' => new external_value(PARAM_LANG, 'lang'),
                'string' => new external_value(PARAM_RAW, 'translated string')
            ])
        );
    }

    /**
     * Returns description of get_user_dates parameters
     *
     * @return external_function_parameters
     */
    public static function get_user_dates_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(
                PARAM_INT,
                'Context ID. Either use this value, or level and instanceid.',
                VALUE_DEFAULT,
                0
            ),
            'contextlevel' => new external_value(
                PARAM_ALPHA,
                'Context level. To be used with instanceid.',
                VALUE_DEFAULT,
                ''
            ),
            'instanceid' => new external_value(
                PARAM_INT,
                'Context instance ID. To be used with level',
                VALUE_DEFAULT,
                0
            ),
            'timestamps' => new external_multiple_structure(
                new external_single_structure([
                    'timestamp' => new external_value(PARAM_INT, 'unix timestamp'),
                    'format' => new external_value(PARAM_TEXT, 'format string'),
                    'type' => new external_value(PARAM_PLUGIN, 'The calendar type', VALUE_DEFAULT),
                    'fixday' => new external_value(PARAM_INT, 'Remove leading zero for day', VALUE_DEFAULT, 1),
                    'fixhour' => new external_value(PARAM_INT, 'Remove leading zero for hour', VALUE_DEFAULT, 1),
                ])
            )
        ]);
    }

    /**
     * Format an array of timestamps.
     *
     * @param int|null $contextid
     * @param string|null $contextlevel
     * @param int|null $instanceid
     * @param array $timestamps
     * @return array
     */
    public static function get_user_dates($contextid, $contextlevel, $instanceid, $timestamps) {
        $params = self::validate_parameters(
            self::get_user_dates_parameters(),
            [
                'contextid' => $contextid,
                'contextlevel' => $contextlevel,
                'instanceid' => $instanceid,
                'timestamps' => $timestamps,
            ]
        );

        $context = self::get_context_from_params($params);
        self::validate_context($context);

        $formatteddates = array_map(function($timestamp) {
            $calendartype = $timestamp['type'];
            $fixday = !empty($timestamp['fixday']);
            $fixhour = !empty($timestamp['fixhour']);
            $calendar  = \core_calendar\type_factory::get_calendar_instance($calendartype);
            return $calendar->timestamp_to_date_string($timestamp['timestamp'], $timestamp['format'], 99, $fixday, $fixhour);
        }, $params['timestamps']);

        return ['dates' => $formatteddates];
    }

    /**
     * Returns description of get_user_dates() result value
     *
     * @return \core_external\external_description
     */
    public static function get_user_dates_returns() {
        return new external_single_structure([
            'dates' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'formatted dates strings')
            )
        ]);
    }

    /**
     * Returns description of get_component_strings parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.4
     */
    public static function get_component_strings_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'component'),
            'lang' => new external_value(PARAM_LANG, 'lang', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Return all lang strings of a component.
     *
     * @param string $component
     * @param string|null $lang
     * @return array
     * @since Moodle 2.4
     */
    public static function get_component_strings($component, $lang = null) {
        if (empty($lang)) {
            $lang = current_language();
        }

        $params = self::validate_parameters(
            self::get_component_strings_parameters(),
            ['component' => $component, 'lang' => $lang]
        );

        $stringmanager = get_string_manager();
        $wsstrings = [];
        $componentstrings = $stringmanager->load_component_strings($params['component'], $params['lang']);
        foreach ($componentstrings as $stringid => $string) {
            $wsstring = [];
            $wsstring['stringid'] = $stringid;
            $wsstring['string']   = $string;
            $wsstrings[] = $wsstring;
        }

        return $wsstrings;
    }

    /**
     * Returns description of get_component_strings() result value
     *
     * @return \core_external\external_description
     * @since Moodle 2.4
     */
    public static function get_component_strings_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'stringid' => new external_value(PARAM_STRINGID, 'string id'),
                'string' => new external_value(PARAM_RAW, 'translated string')
            ])
        );
    }

    /**
     * Returns description of get_fragment parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_fragment_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component for the callback e.g. mod_assign'),
            'callback' => new external_value(PARAM_ALPHANUMEXT, 'Name of the callback to execute'),
            'contextid' => new external_value(PARAM_INT, 'Context ID that the fragment is from'),
            'args' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'param name'),
                    'value' => new external_value(PARAM_RAW, 'param value')
                ]),
                'args for the callback are optional',
                VALUE_OPTIONAL
            )
        ]);
    }

    /**
     * Get a HTML fragment for inserting into something (like an mform).
     * This web service is designed to be called only via AJAX.
     */
    public static function get_fragment($component, $callback, $contextid, $args = null) {
        global $OUTPUT, $PAGE;

        $params = self::validate_parameters(
            self::get_fragment_parameters(),
            [
                'component' => $component,
                'callback' => $callback,
                'contextid' => $contextid,
                'args' => $args
            ]
        );

        // Reformat arguments into something less unwieldy.
        $arguments = [];
        foreach ($params['args'] as $paramargument) {
            $arguments[$paramargument['name']] = $paramargument['value'];
        }

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        $arguments['context'] = $context;

        // Set a default URL to prevent debugging warnings.
        $PAGE->set_url('/');
        $OUTPUT->header();

        // Overwrite the page_requirements_manager so only JS from now on is collected.
        $PAGE->start_collecting_javascript_requirements();
        $data = component_callback($params['component'], 'output_fragment_' . $params['callback'], [$arguments]);
        $jsfooter = $PAGE->requires->get_end_code();
        $output = ['html' => $data, 'javascript' => $jsfooter];
        return $output;
    }

    /**
     * Returns description of get_fragment() result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.1
     */
    public static function get_fragment_returns() {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'HTML fragment.'),
            'javascript' => new external_value(PARAM_RAW, 'JavaScript fragment')
        ]);
    }

    /**
     * Parameters for function update_inplace_editable()
     *
     * @since Moodle 3.1
     * @return external_function_parameters
     */
    public static function update_inplace_editable_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'component responsible for the update', VALUE_REQUIRED),
            'itemtype' => new external_value(PARAM_NOTAGS, 'type of the updated item inside the component', VALUE_REQUIRED),
            'itemid' => new external_value(PARAM_RAW, 'identifier of the updated item', VALUE_REQUIRED),
            'value' => new external_value(PARAM_RAW, 'new value', VALUE_REQUIRED),
        ]);
    }

    /**
     * Update any component's editable value if it implements the callback
     *
     * @since Moodle 3.1
     */
    public static function update_inplace_editable($component, $itemtype, $itemid, $value) {
        global $PAGE;
        $params = self::validate_parameters(
            self::update_inplace_editable_parameters(),
            [
                'component' => $component,
                'itemtype' => $itemtype,
                'itemid' => $itemid,
                'value' => $value
            ]
        );
        if (!component_callback_exists($component, 'inplace_editable')) {
            throw new \moodle_exception('inplaceeditableerror');
        }
        $tmpl = component_callback($params['component'], 'inplace_editable', [$params['itemtype'], $params['itemid'], $params['value']]);
        if (!$tmpl || !($tmpl instanceof \core\output\inplace_editable)) {
            throw new \moodle_exception('inplaceeditableerror');
        }
        return $tmpl->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Return structure for update_inplace_editable()
     *
     * @since Moodle 3.1
     * @return \core_external\external_description
     */
    public static function update_inplace_editable_returns() {
        return new external_single_structure([
            'displayvalue' => new external_value(PARAM_RAW, 'display value (may contain link or other html tags)'),
            'component' => new external_value(PARAM_NOTAGS, 'component responsible for the update', VALUE_OPTIONAL),
            'itemtype' => new external_value(PARAM_NOTAGS, 'itemtype', VALUE_OPTIONAL),
            'value' => new external_value(PARAM_RAW, 'value of the item as it is stored', VALUE_OPTIONAL),
            'itemid' => new external_value(PARAM_RAW, 'identifier of the updated item', VALUE_OPTIONAL),
            'edithint' => new external_value(PARAM_NOTAGS, 'hint for editing element', VALUE_OPTIONAL),
            'editlabel' => new external_value(PARAM_RAW, 'label for editing element', VALUE_OPTIONAL),
            'editicon' => new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Edit icon key', VALUE_OPTIONAL),
                'component' => new external_value(PARAM_COMPONENT, 'Edit icon component', VALUE_OPTIONAL),
                'title' => new external_value(PARAM_NOTAGS, 'Edit icon title', VALUE_OPTIONAL),
            ], 'Edit icon', VALUE_OPTIONAL),
            'type' => new external_value(PARAM_ALPHA, 'type of the element (text, toggle, select)', VALUE_OPTIONAL),
            'options' => new external_value(PARAM_RAW, 'options of the element, format depends on type', VALUE_OPTIONAL),
            'linkeverything' => new external_value(PARAM_INT, 'Should everything be wrapped in the edit link or link displayed separately', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Returns description of fetch_notifications() parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function fetch_notifications_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Returns description of fetch_notifications() result value.
     *
     * @return \core_external\external_description
     * @since Moodle 3.1
     */
    public static function fetch_notifications_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'template'      => new external_value(PARAM_RAW, 'Name of the template'),
                'variables'     => new external_single_structure([
                    'message'       => new external_value(PARAM_RAW, 'HTML content of the Notification'),
                    'extraclasses'  => new external_value(PARAM_RAW, 'Extra classes to provide to the template'),
                    'announce'      => new external_value(PARAM_RAW, 'Whether to announce'),
                    'closebutton'   => new external_value(PARAM_RAW, 'Whether to close'),
                ]),
            ])
        );
    }

    /**
     * Returns the list of notifications in the current session.
     *
     * @param int $contextid
     * @return array
     * @since Moodle 3.1
     */
    public static function fetch_notifications($contextid) {
        global $PAGE;

        self::validate_parameters(
            self::fetch_notifications_parameters(),
            ['contextid' => $contextid]
        );

        $context = \context::instance_by_id($contextid);
        self::validate_context($context);

        return \core\notification::fetch_as_array($PAGE->get_renderer('core'));
    }

//     // ---------------------------------------------------------------
//     //  delete_section_hard
//     // ---------------------------------------------------------------
//     /**
//      * Returns the description of delete_section_hard parameters
//      *
//      * @return \external_function_parameters
//      */
//     public static function delete_section_hard_parameters() {
//         return new external_function_parameters([
//             'sectionid' => new external_value(PARAM_INT, 'Section ID to delete', VALUE_REQUIRED),
//             'courseid'  => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
//         ]);
//     }

//     /**
//      * Delete a section physically from the DB (example).
//      *
//      * @param int $sectionid
//      * @param int $courseid
//      * @return bool
//      * @throws moodle_exception
//      */
//     public static function delete_section_hard($sectionid, $courseid) {
//         global $DB;

//         $params = self::validate_parameters(
//             self::delete_section_hard_parameters(),
//             ['sectionid' => $sectionid, 'courseid' => $courseid]
//         );
//         $context = \context_course::instance($params['courseid']);
//         self::validate_context($context);

//         require_capability('moodle/course:update', $context);

//         if ($DB->record_exists('course_sections', ['id' => $params['sectionid'], 'course' => $params['courseid']])) {
//             $DB->delete_records('course_sections', ['id' => $params['sectionid'], 'course' => $params['courseid']]);
//         } else {
//             throw new \moodle_exception(
//                 'invalidrecord',
//                 'error',
//                 '',
//                 null,
//                 "No section found with ID {$params['sectionid']} in course {$params['courseid']}"
//             );
//         }

//         return true;
//     }

//     /**
//      * Returns description of delete_section_hard() result value
//      *
//      * @return \external_value
//      */
//     public static function delete_section_hard_returns() {
//         return new external_value(PARAM_BOOL, 'True if success');
//     }

//     // ---------------------------------------------------------------
//     //  update_subsection_hard
//     // ---------------------------------------------------------------
//     /**
//      * Returns the description of update_subsection_hard() parameters
//      *
//      * @return \external_function_parameters
//      */
//     public static function update_subsection_hard_parameters() {
//         return new external_function_parameters([
//             'sectionid' => new external_value(PARAM_INT, 'Section (subsection) ID to update', VALUE_REQUIRED),
//             'courseid'  => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
//             'name'      => new external_value(PARAM_TEXT, 'New name', VALUE_OPTIONAL),
//             'summary'   => new external_value(PARAM_RAW, 'New summary text', VALUE_OPTIONAL),
//         ]);
//     }

//     /**
//      * Update a subsection's name/summary in DB (physically in course_sections table).
//      *
//      * @param int $sectionid
//      * @param int $courseid
//      * @param string $name
//      * @param string $summary
//      * @return bool
//      * @throws moodle_exception
//      */
//     public static function update_subsection_hard($sectionid, $courseid, $name='', $summary='') {
//         global $DB;

//         $params = self::validate_parameters(
//             self::update_subsection_hard_parameters(),
//             [
//                 'sectionid' => $sectionid,
//                 'courseid'  => $courseid,
//                 'name'      => $name,
//                 'summary'   => $summary
//             ]
//         );

//         $context = \context_course::instance($params['courseid']);
//         self::validate_context($context);
//         require_capability('moodle/course:update', $context);

//         $record = $DB->get_record(
//             'course_sections',
//             ['id' => $params['sectionid'], 'course' => $params['courseid']],
//             '*',
//             MUST_EXIST
//         );

//         $record->name    = $params['name'];
//         $record->summary = $params['summary'];

//         $DB->update_record('course_sections', $record);

//         return true;
//     }

//     /**
//      * Returns description of update_subsection_hard() result value
//      *
//      * @return \external_value
//      */
//     public static function update_subsection_hard_returns() {
//         return new external_value(PARAM_BOOL, 'True if success');
//     }

//     // ---------------------------------------------------------------
//     //  create_subsection_hard
//     // ---------------------------------------------------------------
    /**
     * Returns the description of create_subsection_hard() parameters
     */
    public static function create_subsection_hard_parameters() {
        return new external_function_parameters([
            'sectionnumber'   => new external_value(PARAM_INT, 'Section number', VALUE_REQUIRED),
            'courseid'        => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
            'parentsectionid' => new external_value(PARAM_INT, 'Parent section ID', VALUE_REQUIRED),
            'name'            => new external_value(PARAM_TEXT, 'Subsection name', VALUE_REQUIRED),
            'summary'         => new external_value(PARAM_RAW, 'Summary text', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Create a new subsection record in course_sections table
     * + Possibly create a mod_subsection instance in course_modules if needed
     *
     * @param int $sectionnumber
     * @param int $courseid
     * @param int $parentsectionid
     * @param string $name
     * @param string $summary
     * @return array
     * @throws moodle_exception
     */
    public static function create_subsection_hard($sectionnumber, $courseid, $parentsectionid, $name, $summary='') {
        global $DB;

        $params = self::validate_parameters(
            self::create_subsection_hard_parameters(),
            [
                'sectionnumber'   => $sectionnumber,
                'courseid'        => $courseid,
                'parentsectionid' => $parentsectionid,
                'name'            => $name,
                'summary'         => $summary
            ]
        );

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        // First, create a new record in course_sections
        $record = new stdClass();
        $record->course        = $params['courseid'];
        $record->section       = $params['sectionnumber'];
        $record->name          = $params['name'];
        $record->summary       = $params['summary'];
        $record->summaryformat = 1;
        $record->visible       = 1;
        $record->availability  = '{"op":"&","c":[],"showc":[]}';

        $newsectionid = $DB->insert_record('course_sections', $record);

        // If you need to create a course_module with mod_subsection:
        // e.g. $module = $DB->get_record('modules', ['name' => 'subsection'], '*', MUST_EXIST);
        // $mrec = new stdClass();
        // $mrec->course  = $params['courseid'];
        // $mrec->module  = $module->id;
        // $mrec->instance= ??? (the instance id of the "subsection" plugin)
        // $mrec->section = $parentsectionid; // meaning it's placed under the parent
        // $newcmid = $DB->insert_record('course_modules', $mrec);

        // Also you may want to update the parent's ->sequence with the newly inserted coursemodule ID

        // Return the new IDs:
        // for now, let's assume we have $subinstanceid and $newcmid if needed:
        $subinstanceid = 0; // or actual ID if you create a mod_subsection instance
        $newcmid       = 0; // or the newly created coursemodule

        return [
            'id'             => $newsectionid,     // The new course_sections id
            'subsection_id'  => $subinstanceid,    // If you created an instance in mod_subsection
            'course_module_id' => $newcmid
        ];
    }

    /**
     * Returns structure for create_subsection_hard() result
     *
     * @return \external_single_structure
     */
    public static function create_subsection_hard_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Newly created course_sections.id'),
            'subsection_id' => new external_value(PARAM_INT, 'Subsection plugin instance id (if any)', VALUE_OPTIONAL),
            'course_module_id' => new external_value(PARAM_INT, 'Course module id for the newly created submodule (if any)', VALUE_OPTIONAL),
        ]);
    }

}
