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

namespace core_search;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/testable_core_search.php');
require_once(__DIR__ . '/fixtures/mock_search_area.php');

/**
 * Unit tests for search manager.
 *
 * @package     core_search
 * @category    phpunit
 * @copyright   2015 David Monllao {@link http://www.davidmonllao.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager_test extends \advanced_testcase {

    /**
     * Forum area id.
     *
     * @var string
     */

    protected $forumpostareaid = null;

    /**
     * Courses area id.
     *
     * @var string
     */
    protected $coursesareaid = null;

    public function setUp(): void {
        parent::setUp();
        $this->forumpostareaid = \core_search\manager::generate_areaid('mod_forum', 'post');
        $this->coursesareaid = \core_search\manager::generate_areaid('core_course', 'course');
    }

    protected function tearDown(): void {
        // Stop it from faking time in the search manager (if set by test).
        \testable_core_search::fake_current_time();
        parent::tearDown();
    }

    public function test_search_enabled(): void {

        $this->resetAfterTest();

        // Disabled by default.
        $this->assertFalse(\core_search\manager::is_global_search_enabled());

        set_config('enableglobalsearch', true);
        $this->assertTrue(\core_search\manager::is_global_search_enabled());

        set_config('enableglobalsearch', false);
        $this->assertFalse(\core_search\manager::is_global_search_enabled());
    }

    /**
     * Tests the course search url is correct.
     *
     * @param bool|null $gsenabled Enable global search (null to leave as the default).
     * @param bool|null $allcourses Enable searching all courses (null to leave as the default).
     * @param bool|null $enablearea Enable the course search area (null to leave as the default).
     * @param string $expected The expected course search url.
     * @dataProvider data_course_search_url
     */
    public function test_course_search_url(?bool $gsenabled, ?bool $allcourses, ?bool $enablearea, string $expected): void {
        $this->resetAfterTest();

        if (!is_null($gsenabled)) {
            set_config('enableglobalsearch', $gsenabled);
        }

        if (!is_null($allcourses)) {
            set_config('searchincludeallcourses', $allcourses);
        }

        if (!is_null($enablearea)) {
            // Setup the course search area.
            $areaid = \core_search\manager::generate_areaid('core_course', 'course');
            $area = \core_search\manager::get_search_area($areaid);
            $area->set_enabled($enablearea);
        }

        $this->assertEquals(new \moodle_url($expected), \core_search\manager::get_course_search_url());
    }

    /**
     * Data for the test_course_search_url test.
     *
     * @return array[]
     */
    public static function data_course_search_url(): array {
        return [
            'defaults' => [null, null, null, '/course/search.php'],
            'enabled' => [true, true, true, '/search/index.php'],
            'no all courses, no search area' => [true, false, false, '/course/search.php'],
            'no search area' => [true, true, false, '/course/search.php'],
            'no all courses' => [true, false, true, '/course/search.php'],
            'disabled' => [false, false, false, '/course/search.php'],
            'no global search' => [false, true, false, '/course/search.php'],
            'no global search, no all courses' => [false, false, true, '/course/search.php'],
            'no global search, no search area' => [false, true, false, '/course/search.php'],
        ];
    }

    /**
     * Tests that we detect that global search can replace frontpage course search.
     *
     * @param bool|null $gsenabled Enable global search (null to leave as the default).
     * @param bool|null $allcourses Enable searching all courses (null to leave as the default).
     * @param bool|null $enablearea Enable the course search area (null to leave as the default).
     * @param bool $expected The expected result.
     * @dataProvider data_can_replace_course_search
     */
    public function test_can_replace_course_search(?bool $gsenabled, ?bool $allcourses, ?bool $enablearea, bool $expected): void {
        $this->resetAfterTest();

        if (!is_null($gsenabled)) {
            set_config('enableglobalsearch', $gsenabled);
        }

        if (!is_null($allcourses)) {
            set_config('searchincludeallcourses', $allcourses);
        }

        if (!is_null($enablearea)) {
            // Setup the course search area.
            $areaid = \core_search\manager::generate_areaid('core_course', 'course');
            $area = \core_search\manager::get_search_area($areaid);
            $area->set_enabled($enablearea);
        }

        $this->assertEquals($expected, \core_search\manager::can_replace_course_search());
    }

    /**
     * Data for the test_can_replace_course_search test.
     *
     * @return array[]
     */
    public static function data_can_replace_course_search(): array {
        return [
            'defaults' => [null, null, null, false],
            'enabled' => [true, true, true, true],
            'no all courses, no search area' => [true, false, false, false],
            'no search area' => [true, true, false, false],
            'no all courses' => [true, false, true, false],
            'disabled' => [false, false, false, false],
            'no global search' => [false, true, false, false],
            'no global search, no all courses' => [false, false, true, false],
            'no global search, no search area' => [false, true, false, false],
        ];
    }

    public function test_search_areas(): void {
        global $CFG;

        $this->resetAfterTest();

        set_config('enableglobalsearch', true);

        $fakeareaid = \core_search\manager::generate_areaid('mod_unexisting', 'chihuaquita');

        $searcharea = \core_search\manager::get_search_area($this->forumpostareaid);
        $this->assertInstanceOf('\core_search\base', $searcharea);

        $this->assertFalse(\core_search\manager::get_search_area($fakeareaid));

        $this->assertArrayHasKey($this->forumpostareaid, \core_search\manager::get_search_areas_list());
        $this->assertArrayNotHasKey($fakeareaid, \core_search\manager::get_search_areas_list());

        // Enabled by default once global search is enabled.
        $this->assertArrayHasKey($this->forumpostareaid, \core_search\manager::get_search_areas_list(true));

        list($componentname, $varname) = $searcharea->get_config_var_name();
        set_config($varname . '_enabled', 0, $componentname);
        \core_search\manager::clear_static();

        $this->assertArrayNotHasKey('mod_forum', \core_search\manager::get_search_areas_list(true));

        set_config($varname . '_enabled', 1, $componentname);

        // Although the result is wrong, we want to check that \core_search\manager::get_search_areas_list returns cached results.
        $this->assertArrayNotHasKey($this->forumpostareaid, \core_search\manager::get_search_areas_list(true));

        // Now we check the real result.
        \core_search\manager::clear_static();
        $this->assertArrayHasKey($this->forumpostareaid, \core_search\manager::get_search_areas_list(true));
    }

    public function test_search_config(): void {

        $this->resetAfterTest();

        $search = \testable_core_search::instance();

        // We should test both plugin types and core subsystems. No core subsystems available yet.
        $searcharea = $search->get_search_area($this->forumpostareaid);

        list($componentname, $varname) = $searcharea->get_config_var_name();

        // Just with a couple of vars should be enough.
        $start = time() - 100;
        $end = time();
        set_config($varname . '_indexingstart', $start, $componentname);
        set_config($varname . '_indexingend', $end, $componentname);

        $configs = $search->get_areas_config(array($this->forumpostareaid => $searcharea));
        $this->assertEquals($start, $configs[$this->forumpostareaid]->indexingstart);
        $this->assertEquals($end, $configs[$this->forumpostareaid]->indexingend);
        $this->assertEquals(false, $configs[$this->forumpostareaid]->partial);

        try {
            $fakeareaid = \core_search\manager::generate_areaid('mod_unexisting', 'chihuaquita');
            $search->reset_config($fakeareaid);
            $this->fail('An exception should be triggered if the provided search area does not exist.');
        } catch (\moodle_exception $ex) {
            $this->assertStringContainsString($fakeareaid . ' search area is not available.', $ex->getMessage());
        }

        // We clean it all but enabled components.
        $search->reset_config($this->forumpostareaid);
        $config = $searcharea->get_config();
        $this->assertEquals(1, $config[$varname . '_enabled']);
        $this->assertEquals(0, $config[$varname . '_indexingstart']);
        $this->assertEquals(0, $config[$varname . '_indexingend']);
        $this->assertEquals(0, $config[$varname . '_lastindexrun']);
        $this->assertEquals(0, $config[$varname . '_partial']);
        // No caching.
        $configs = $search->get_areas_config(array($this->forumpostareaid => $searcharea));
        $this->assertEquals(0, $configs[$this->forumpostareaid]->indexingstart);
        $this->assertEquals(0, $configs[$this->forumpostareaid]->indexingend);

        set_config($varname . '_indexingstart', $start, $componentname);
        set_config($varname . '_indexingend', $end, $componentname);

        // All components config should be reset.
        $search->reset_config();
        $this->assertEquals(0, get_config($componentname, $varname . '_indexingstart'));
        $this->assertEquals(0, get_config($componentname, $varname . '_indexingend'));
        $this->assertEquals(0, get_config($componentname, $varname . '_lastindexrun'));
        // No caching.
        $configs = $search->get_areas_config(array($this->forumpostareaid => $searcharea));
        $this->assertEquals(0, $configs[$this->forumpostareaid]->indexingstart);
        $this->assertEquals(0, $configs[$this->forumpostareaid]->indexingend);
    }

    /**
     * Tests the get_last_indexing_duration method in the base area class.
     */
    public function test_get_last_indexing_duration(): void {
        $this->resetAfterTest();

        $search = \testable_core_search::instance();

        $searcharea = $search->get_search_area($this->forumpostareaid);

        // When never indexed, the duration is false.
        $this->assertSame(false, $searcharea->get_last_indexing_duration());

        // Set the start/end times.
        list($componentname, $varname) = $searcharea->get_config_var_name();
        $start = time() - 100;
        $end = time();
        set_config($varname . '_indexingstart', $start, $componentname);
        set_config($varname . '_indexingend', $end, $componentname);

        // The duration should now be 100.
        $this->assertSame(100, $searcharea->get_last_indexing_duration());
    }

    /**
     * Tests that partial indexing works correctly.
     */
    public function test_partial_indexing(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and a forum.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);

        // Index everything up to current. Ensure the course is older than current second so it
        // definitely doesn't get indexed again next time.
        $this->waitForSecond();
        $search = \testable_core_search::instance();
        $search->index(false, 0);

        $searcharea = $search->get_search_area($this->forumpostareaid);
        list($componentname, $varname) = $searcharea->get_config_var_name();
        $this->assertFalse(get_config($componentname, $varname . '_partial'));

        // Add 3 discussions to the forum.
        $now = time();
        $generator->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id,
                'forum' => $forum->id, 'userid' => $USER->id, 'timemodified' => $now,
                'name' => 'Frog']);
        $generator->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id,
                'forum' => $forum->id, 'userid' => $USER->id, 'timemodified' => $now + 1,
                'name' => 'Toad']);
        $generator->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id,
                'forum' => $forum->id, 'userid' => $USER->id, 'timemodified' => $now + 2,
                'name' => 'Zombie']);
        $generator->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id,
                'forum' => $forum->id, 'userid' => $USER->id, 'timemodified' => $now + 2,
                'name' => 'Werewolf']);
        time_sleep_until($now + 3);

        // Clear the count of added documents.
        $search->get_engine()->get_and_clear_added_documents();

        // Make the search engine delay while indexing each document.
        $search->get_engine()->set_add_delay(1.2);

        // Use fake time, starting from now.
        \testable_core_search::fake_current_time(time());

        // Index with a limit of 2 seconds - it should index 2 of the documents (after the second
        // one, it will have taken 2.4 seconds so it will stop).
        $search->index(false, 2);
        $added = $search->get_engine()->get_and_clear_added_documents();
        $this->assertCount(2, $added);
        $this->assertEquals('Frog', $added[0]->get('title'));
        $this->assertEquals('Toad', $added[1]->get('title'));
        $this->assertEquals(1, get_config($componentname, $varname . '_partial'));
        // Whilst 2.4 seconds of "time" have elapsed, the indexing duration is
        // measured in seconds, so should be 2.
        $this->assertEquals(2, $searcharea->get_last_indexing_duration());

        // Add a label.
        $generator->create_module('label', ['course' => $course->id, 'intro' => 'Vampire']);

        // Wait to next second (so as to not reindex the label more than once, as it will now
        // be timed before the indexing run).
        $this->waitForSecond();
        \testable_core_search::fake_current_time(time());

        // Next index with 1 second limit should do the label and not the forum - the logic is,
        // if it spent ages indexing an area last time, do that one last on next run.
        $search->index(false, 1);
        $added = $search->get_engine()->get_and_clear_added_documents();
        $this->assertCount(1, $added);
        $this->assertEquals('Vampire', $added[0]->get('title'));

        // Index again with a 3 second limit - it will redo last post for safety (because of other
        // things possibly having the same time second), and then do the remaining one. (Note:
        // because it always does more than one second worth of items, it would actually index 2
        // posts even if the limit were less than 2, we are testing it does 3 posts to make sure
        // the time limiting is actually working with the specified time.)
        $search->index(false, 3);
        $added = $search->get_engine()->get_and_clear_added_documents();
        $this->assertCount(3, $added);
        $this->assertEquals('Toad', $added[0]->get('title'));
        $remainingtitles = [$added[1]->get('title'), $added[2]->get('title')];
        sort($remainingtitles);
        $this->assertEquals(['Werewolf', 'Zombie'], $remainingtitles);
        $this->assertFalse(get_config($componentname, $varname . '_partial'));

        // Index again - there should be nothing to index this time.
        $search->index(false, 2);
        $added = $search->get_engine()->get_and_clear_added_documents();
        $this->assertCount(0, $added);
        $this->assertFalse(get_config($componentname, $varname . '_partial'));
    }

    /**
     * Tests the progress display while indexing.
     *
     * This tests the different logic about displaying progress for slow/fast and
     * complete/incomplete processing.
     */
    public function test_index_progress(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        // Set up the fake search area.
        $search = \testable_core_search::instance();
        $area = new \core_mocksearch\search\mock_search_area();
        $search->add_search_area('whatever', $area);
        $searchgenerator = $generator->get_plugin_generator('core_search');
        $searchgenerator->setUp();

        // Add records with specific time modified values.
        $time = strtotime('2017-11-01 01:00');
        for ($i = 0; $i < 8; $i ++) {
            $searchgenerator->create_record((object)['timemodified' => $time]);
            $time += 60;
        }

        // Simulate slow progress on indexing and initial query.
        $now = strtotime('2017-11-11 01:00');
        \testable_core_search::fake_current_time($now);
        $area->set_indexing_delay(10.123);
        $search->get_engine()->set_add_delay(15.789);

        // Run search indexing and check output.
        $progress = new \progress_trace_buffer(new \text_progress_trace(), false);
        $search->index(false, 75, $progress);
        $out = $progress->get_buffer();
        $progress->reset_buffer();

        // Check for the standard text.
        $this->assertStringContainsString('Processing area: Mock search area', $out);
        $this->assertStringContainsString('Stopping indexing due to time limit', $out);

        // Check for initial query performance indication.
        $this->assertStringContainsString('Initial query took 10.1 seconds.', $out);

        // Check for the two (approximately) every-30-seconds messages.
        $this->assertStringContainsString('01:00:41: Done to 1/11/17, 01:01', $out);
        $this->assertStringContainsString('01:01:13: Done to 1/11/17, 01:03', $out);

        // Check for the 'not complete' indicator showing when it was done until.
        $this->assertStringContainsString('Processed 5 records containing 5 documents, in 89.1 seconds ' .
                '(not complete; done to 1/11/17, 01:04)', $out);

        // Make the initial query delay less than 5 seconds, so it won't appear. Make the documents
        // quicker, so that the 30-second delay won't be needed.
        $area->set_indexing_delay(4.9);
        $search->get_engine()->set_add_delay(1);

        // Run search indexing (still partial) and check output.
        $progress = new \progress_trace_buffer(new \text_progress_trace(), false);
        $search->index(false, 5, $progress);
        $out = $progress->get_buffer();
        $progress->reset_buffer();

        $this->assertStringContainsString('Processing area: Mock search area', $out);
        $this->assertStringContainsString('Stopping indexing due to time limit', $out);
        $this->assertStringNotContainsString('Initial query took', $out);
        $this->assertStringNotContainsString(': Done to', $out);
        $this->assertStringContainsString('Processed 2 records containing 2 documents, in 6.9 seconds ' .
                '(not complete; done to 1/11/17, 01:05).', $out);

        // Run the remaining items to complete it.
        $progress = new \progress_trace_buffer(new \text_progress_trace(), false);
        $search->index(false, 100, $progress);
        $out = $progress->get_buffer();
        $progress->reset_buffer();

        $this->assertStringContainsString('Processing area: Mock search area', $out);
        $this->assertStringNotContainsString('Stopping indexing due to time limit', $out);
        $this->assertStringNotContainsString('Initial query took', $out);
        $this->assertStringNotContainsString(': Done to', $out);
        $this->assertStringContainsString('Processed 3 records containing 3 documents, in 7.9 seconds.', $out);

        $searchgenerator->tearDown();
    }

    /**
     * Tests that documents with modified time in the future are NOT indexed (as this would cause
     * a problem by preventing it from indexing other documents modified between now and the future
     * date).
     */
    public function test_future_documents(): void {
        $this->resetAfterTest();

        // Create a course and a forum.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);

        // Index everything up to current. Ensure the course is older than current second so it
        // definitely doesn't get indexed again next time.
        $this->waitForSecond();
        $search = \testable_core_search::instance();
        $search->index(false, 0);
        $search->get_engine()->get_and_clear_added_documents();

        // Add 2 discussions to the forum, one of which happend just now, but the other is
        // incorrectly set to the future.
        $now = time();
        $userid = get_admin()->id;
        $generator->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id,
                'forum' => $forum->id, 'userid' => $userid, 'timemodified' => $now,
                'name' => 'Frog']);
        $generator->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id,
                'forum' => $forum->id, 'userid' => $userid, 'timemodified' => $now + 100,
                'name' => 'Toad']);

        // Wait for a second so we're not actually on the same second as the forum post (there's a
        // 1 second overlap between indexing; it would get indexed in both checks below otherwise).
        $this->waitForSecond();

        // Index.
        $search->index(false);
        $added = $search->get_engine()->get_and_clear_added_documents();
        $this->assertCount(1, $added);
        $this->assertEquals('Frog', $added[0]->get('title'));

        // Check latest time - it should be the same as $now, not the + 100.
        $searcharea = $search->get_search_area($this->forumpostareaid);
        list($componentname, $varname) = $searcharea->get_config_var_name();
        $this->assertEquals($now, get_config($componentname, $varname . '_lastindexrun'));

        // Index again - there should be nothing to index this time.
        $search->index(false);
        $added = $search->get_engine()->get_and_clear_added_documents();
        $this->assertCount(0, $added);
    }

    /**
     * Tests that indexing a specified context works correctly.
     */
    public function test_context_indexing(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and two forums and a page.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $now = time();
        $forum1 = $generator->create_module('forum', ['course' => $course->id]);
        $generator->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id,
                'forum' => $forum1->id, 'userid' => $USER->id, 'timemodified' => $now,
                'name' => 'Frog']);
        $this->waitForSecond();
        $generator->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id,
                'forum' => $forum1->id, 'userid' => $USER->id, 'timemodified' => $now + 2,
                'name' => 'Zombie']);
        $forum2 = $generator->create_module('forum', ['course' => $course->id]);
        $this->waitForSecond();
        $generator->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id,
                'forum' => $forum2->id, 'userid' => $USER->id, 'timemodified' => $now + 1,
                'name' => 'Toad']);
        $generator->create_module('page', ['course' => $course->id]);
        $generator->create_module('forum', ['course' => $course->id]);

        // Index forum 1 only.
        $search = \testable_core_search::instance();
        $buffer = new \progress_trace_buffer(new \text_progress_trace(), false);
        $result = $search->index_context(\context_module::instance($forum1->cmid), '', 0, $buffer);
        $this->assertTrue($result->complete);
        $log = $buffer->get_buffer();
        $buffer->reset_buffer();

        // Confirm that output only processed 1 forum activity and 2 posts.
        $this->assertNotFalse(strpos($log, "area: Forum - activity information\n  Processed 1 "));
        $this->assertNotFalse(strpos($log, "area: Forum - posts\n  Processed 2 "));

        // Confirm that some areas for different types of context were skipped.
        $this->assertNotFalse(strpos($log, "area: Users\n  Skipping"));
        $this->assertNotFalse(strpos($log, "area: Courses\n  Skipping"));

        // Confirm that another module area had no results.
        $this->assertNotFalse(strpos($log, "area: Page\n  No documents"));

        // Index whole course.
        $result = $search->index_context(\context_course::instance($course->id), '', 0, $buffer);
        $this->assertTrue($result->complete);
        $log = $buffer->get_buffer();
        $buffer->reset_buffer();

        // Confirm that output processed 3 forum activities and 3 posts.
        $this->assertNotFalse(strpos($log, "area: Forum - activity information\n  Processed 3 "));
        $this->assertNotFalse(strpos($log, "area: Forum - posts\n  Processed 3 "));

        // The course area was also included this time.
        $this->assertNotFalse(strpos($log, "area: Courses\n  Processed 1 "));

        // Confirm that another module area had results too.
        $this->assertNotFalse(strpos($log, "area: Page\n  Processed 1 "));

        // Index whole course, but only forum posts.
        $result = $search->index_context(\context_course::instance($course->id), 'mod_forum-post',
                0, $buffer);
        $this->assertTrue($result->complete);
        $log = $buffer->get_buffer();
        $buffer->reset_buffer();

        // Confirm that output processed 3 posts but not forum activities.
        $this->assertFalse(strpos($log, "area: Forum - activity information"));
        $this->assertNotFalse(strpos($log, "area: Forum - posts\n  Processed 3 "));

        // Set time limit and retry index of whole course, taking 3 tries to complete it.
        $search->get_engine()->set_add_delay(0.4);
        $result = $search->index_context(\context_course::instance($course->id), '', 1, $buffer);
        $log = $buffer->get_buffer();
        $buffer->reset_buffer();
        $this->assertFalse($result->complete);
        $this->assertNotFalse(strpos($log, "area: Forum - activity information\n  Processed 2 "));

        $result = $search->index_context(\context_course::instance($course->id), '', 1, $buffer,
                $result->startfromarea, $result->startfromtime);
        $log = $buffer->get_buffer();
        $buffer->reset_buffer();
        $this->assertNotFalse(strpos($log, "area: Forum - activity information\n  Processed 2 "));
        $this->assertNotFalse(strpos($log, "area: Forum - posts\n  Processed 2 "));
        $this->assertFalse($result->complete);

        $result = $search->index_context(\context_course::instance($course->id), '', 1, $buffer,
                $result->startfromarea, $result->startfromtime);
        $log = $buffer->get_buffer();
        $buffer->reset_buffer();
        $this->assertNotFalse(strpos($log, "area: Forum - posts\n  Processed 2 "));
        $this->assertTrue($result->complete);
    }

    /**
     * Adding this test here as get_areas_user_accesses process is the same, results just depend on the context level.
     *
     * @return void
     */
    public function test_search_user_accesses(): void {
        global $DB;

        $this->resetAfterTest();

        $frontpage = $DB->get_record('course', array('id' => SITEID));
        $frontpagectx = \context_course::instance($frontpage->id);
        $course1 = $this->getDataGenerator()->create_course();
        $course1ctx = \context_course::instance($course1->id);
        $course2 = $this->getDataGenerator()->create_course();
        $course2ctx = \context_course::instance($course2->id);
        $course3 = $this->getDataGenerator()->create_course();
        $course3ctx = \context_course::instance($course3->id);
        $teacher = $this->getDataGenerator()->create_user();
        $teacherctx = \context_user::instance($teacher->id);
        $student = $this->getDataGenerator()->create_user();
        $studentctx = \context_user::instance($student->id);
        $noaccess = $this->getDataGenerator()->create_user();
        $noaccessctx = \context_user::instance($noaccess->id);
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id, 'teacher');
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, 'student');

        $frontpageforum = $this->getDataGenerator()->create_module('forum', array('course' => $frontpage->id));
        $forum1 = $this->getDataGenerator()->create_module('forum', array('course' => $course1->id));
        $forum2 = $this->getDataGenerator()->create_module('forum', array('course' => $course1->id));
        $forum3 = $this->getDataGenerator()->create_module('forum', array('course' => $course2->id));
        $frontpageforumcontext = \context_module::instance($frontpageforum->cmid);
        $context1 = \context_module::instance($forum1->cmid);
        $context2 = \context_module::instance($forum2->cmid);
        $context3 = \context_module::instance($forum3->cmid);
        $forum4 = $this->getDataGenerator()->create_module('forum', array('course' => $course3->id));
        $context4 = \context_module::instance($forum4->cmid);

        $search = \testable_core_search::instance();
        $mockareaid = \core_search\manager::generate_areaid('core_mocksearch', 'mock_search_area');
        $search->add_core_search_areas();
        $search->add_search_area($mockareaid, new \core_mocksearch\search\mock_search_area());

        $this->setAdminUser();
        $this->assertEquals((object)['everything' => true], $search->get_areas_user_accesses());

        $sitectx = \context_course::instance(SITEID);

        // Can access the frontpage ones.
        $this->setUser($noaccess);
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $this->assertEquals(array($frontpageforumcontext->id => $frontpageforumcontext->id), $contexts[$this->forumpostareaid]);
        $this->assertEquals(array($sitectx->id => $sitectx->id), $contexts[$this->coursesareaid]);
        $mockctxs = array($noaccessctx->id => $noaccessctx->id, $frontpagectx->id => $frontpagectx->id);
        $this->assertEquals($mockctxs, $contexts[$mockareaid]);

        $this->setUser($teacher);
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $frontpageandcourse1 = array($frontpageforumcontext->id => $frontpageforumcontext->id, $context1->id => $context1->id,
            $context2->id => $context2->id);
        $this->assertEquals($frontpageandcourse1, $contexts[$this->forumpostareaid]);
        $this->assertEquals(array($sitectx->id => $sitectx->id, $course1ctx->id => $course1ctx->id),
            $contexts[$this->coursesareaid]);
        $mockctxs = array($teacherctx->id => $teacherctx->id,
                $frontpagectx->id => $frontpagectx->id, $course1ctx->id => $course1ctx->id);
        $this->assertEquals($mockctxs, $contexts[$mockareaid]);

        $this->setUser($student);
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $this->assertEquals($frontpageandcourse1, $contexts[$this->forumpostareaid]);
        $this->assertEquals(array($sitectx->id => $sitectx->id, $course1ctx->id => $course1ctx->id),
            $contexts[$this->coursesareaid]);
        $mockctxs = array($studentctx->id => $studentctx->id,
                $frontpagectx->id => $frontpagectx->id, $course1ctx->id => $course1ctx->id);
        $this->assertEquals($mockctxs, $contexts[$mockareaid]);

        // Hide the activity.
        set_coursemodule_visible($forum2->cmid, 0);
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $this->assertEquals(array($frontpageforumcontext->id => $frontpageforumcontext->id, $context1->id => $context1->id),
            $contexts[$this->forumpostareaid]);

        // Now test course limited searches.
        set_coursemodule_visible($forum2->cmid, 1);
        $this->getDataGenerator()->enrol_user($student->id, $course2->id, 'student');
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $allcontexts = array($frontpageforumcontext->id => $frontpageforumcontext->id, $context1->id => $context1->id,
            $context2->id => $context2->id, $context3->id => $context3->id);
        $this->assertEquals($allcontexts, $contexts[$this->forumpostareaid]);
        $this->assertEquals(array($sitectx->id => $sitectx->id, $course1ctx->id => $course1ctx->id,
            $course2ctx->id => $course2ctx->id), $contexts[$this->coursesareaid]);

        $contexts = $search->get_areas_user_accesses(array($course1->id, $course2->id))->usercontexts;
        $allcontexts = array($context1->id => $context1->id, $context2->id => $context2->id, $context3->id => $context3->id);
        $this->assertEquals($allcontexts, $contexts[$this->forumpostareaid]);
        $this->assertEquals(array($course1ctx->id => $course1ctx->id,
            $course2ctx->id => $course2ctx->id), $contexts[$this->coursesareaid]);

        $contexts = $search->get_areas_user_accesses(array($course2->id))->usercontexts;
        $allcontexts = array($context3->id => $context3->id);
        $this->assertEquals($allcontexts, $contexts[$this->forumpostareaid]);
        $this->assertEquals(array($course2ctx->id => $course2ctx->id), $contexts[$this->coursesareaid]);

        $contexts = $search->get_areas_user_accesses(array($course1->id))->usercontexts;
        $allcontexts = array($context1->id => $context1->id, $context2->id => $context2->id);
        $this->assertEquals($allcontexts, $contexts[$this->forumpostareaid]);
        $this->assertEquals(array($course1ctx->id => $course1ctx->id), $contexts[$this->coursesareaid]);

        // Test context limited search with no course limit.
        $contexts = $search->get_areas_user_accesses(false,
                [$frontpageforumcontext->id, $course2ctx->id])->usercontexts;
        $this->assertEquals([$frontpageforumcontext->id => $frontpageforumcontext->id],
                $contexts[$this->forumpostareaid]);
        $this->assertEquals([$course2ctx->id => $course2ctx->id],
                $contexts[$this->coursesareaid]);

        // Test context limited search with course limit.
        $contexts = $search->get_areas_user_accesses([$course1->id, $course2->id],
                [$frontpageforumcontext->id, $course2ctx->id])->usercontexts;
        $this->assertArrayNotHasKey($this->forumpostareaid, $contexts);
        $this->assertEquals([$course2ctx->id => $course2ctx->id],
                $contexts[$this->coursesareaid]);

        // Single context and course.
        $contexts = $search->get_areas_user_accesses([$course1->id], [$context1->id])->usercontexts;
        $this->assertEquals([$context1->id => $context1->id], $contexts[$this->forumpostareaid]);
        $this->assertArrayNotHasKey($this->coursesareaid, $contexts);

        // Enable "Include all visible courses" feature.
        set_config('searchincludeallcourses', 1);
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $expected = [
            $sitectx->id => $sitectx->id,
            $course1ctx->id => $course1ctx->id,
            $course2ctx->id => $course2ctx->id,
            $course3ctx->id => $course3ctx->id
        ];
        // Check that a student has assess to all courses data when "searchincludeallcourses" is enabled.
        $this->assertEquals($expected, $contexts[$this->coursesareaid]);
        // But at the same time doesn't have access to activities in the courses that the student can't access.
        $this->assertFalse(key_exists($context4->id, $contexts[$this->forumpostareaid]));

        // For admins, this is still limited only if we specify the things, so it should be same.
        $this->setAdminUser();
        $contexts = $search->get_areas_user_accesses([$course1->id], [$context1->id])->usercontexts;
        $this->assertEquals([$context1->id => $context1->id], $contexts[$this->forumpostareaid]);
        $this->assertArrayNotHasKey($this->coursesareaid, $contexts);
    }

    /**
     * Tests the block support in get_search_user_accesses.
     *
     * @return void
     */
    public function test_search_user_accesses_blocks(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create course and add HTML block.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $context1 = \context_course::instance($course1->id);
        $page = new \moodle_page();
        $page->set_context($context1);
        $page->set_course($course1);
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->blocks->load_blocks();
        $page->blocks->add_block_at_end_of_default_region('html');

        // Create another course with HTML blocks only in some weird page or a module page (not
        // yet supported, so both these blocks will be ignored).
        $course2 = $generator->create_course();
        $context2 = \context_course::instance($course2->id);
        $page = new \moodle_page();
        $page->set_context($context2);
        $page->set_course($course2);
        $page->set_pagelayout('standard');
        $page->set_pagetype('bogus-page');
        $page->blocks->load_blocks();
        $page->blocks->add_block_at_end_of_default_region('html');

        $forum = $this->getDataGenerator()->create_module('forum', array('course' => $course2->id));
        $forumcontext = \context_module::instance($forum->cmid);
        $page = new \moodle_page();
        $page->set_context($forumcontext);
        $page->set_course($course2);
        $page->set_pagelayout('standard');
        $page->set_pagetype('mod-forum-view');
        $page->blocks->load_blocks();
        $page->blocks->add_block_at_end_of_default_region('html');

        // The third course has 2 HTML blocks.
        $course3 = $generator->create_course();
        $context3 = \context_course::instance($course3->id);
        $page = new \moodle_page();
        $page->set_context($context3);
        $page->set_course($course3);
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->blocks->load_blocks();
        $page->blocks->add_block_at_end_of_default_region('html');
        $page->blocks->add_block_at_end_of_default_region('html');

        // Student 1 belongs to all 3 courses.
        $student1 = $generator->create_user();
        $generator->enrol_user($student1->id, $course1->id, 'student');
        $generator->enrol_user($student1->id, $course2->id, 'student');
        $generator->enrol_user($student1->id, $course3->id, 'student');

        // Student 2 belongs only to course 2.
        $student2 = $generator->create_user();
        $generator->enrol_user($student2->id, $course2->id, 'student');

        // And the third student is only in course 3.
        $student3 = $generator->create_user();
        $generator->enrol_user($student3->id, $course3->id, 'student');

        $search = \testable_core_search::instance();
        $search->add_core_search_areas();

        // Admin gets 'true' result to function regardless of blocks.
        $this->setAdminUser();
        $this->assertEquals((object)['everything' => true], $search->get_areas_user_accesses());

        // Student 1 gets all 3 block contexts.
        $this->setUser($student1);
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $this->assertArrayHasKey('block_html-content', $contexts);
        $this->assertCount(3, $contexts['block_html-content']);

        // Student 2 does not get any blocks.
        $this->setUser($student2);
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $this->assertArrayNotHasKey('block_html-content', $contexts);

        // Student 3 gets only two of them.
        $this->setUser($student3);
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $this->assertArrayHasKey('block_html-content', $contexts);
        $this->assertCount(2, $contexts['block_html-content']);

        // A course limited search for student 1 is the same as the student 3 search.
        $this->setUser($student1);
        $limitedcontexts = $search->get_areas_user_accesses([$course3->id])->usercontexts;
        $this->assertEquals($contexts['block_html-content'], $limitedcontexts['block_html-content']);

        // Get block context ids for the blocks that appear.
        $blockcontextids = $DB->get_fieldset_sql('
            SELECT x.id
              FROM {block_instances} bi
              JOIN {context} x ON x.instanceid = bi.id AND x.contextlevel = ?
             WHERE (parentcontextid = ? OR parentcontextid = ?)
                   AND blockname = ?
          ORDER BY bi.id', [CONTEXT_BLOCK, $context1->id, $context3->id, 'html']);

        // Context limited search (no course).
        $contexts = $search->get_areas_user_accesses(false,
                [$blockcontextids[0], $blockcontextids[2]])->usercontexts;
        $this->assertCount(2, $contexts['block_html-content']);

        // Context limited search (with course 3).
        $contexts = $search->get_areas_user_accesses([$course2->id, $course3->id],
                [$blockcontextids[0], $blockcontextids[2]])->usercontexts;
        $this->assertCount(1, $contexts['block_html-content']);
    }

    /**
     * Tests retrieval of users search areas when limiting to a course the user is not enrolled in
     */
    public function test_search_users_accesses_limit_non_enrolled_course(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $search = \testable_core_search::instance();
        $search->add_core_search_areas();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        // Limit courses to search to only those the user is enrolled in.
        set_config('searchallavailablecourses', 0);

        $usercontexts = $search->get_areas_user_accesses([$course->id])->usercontexts;
        $this->assertNotEmpty($usercontexts);
        $this->assertArrayNotHasKey('core_course-course', $usercontexts);

        // This config ensures the search will also include courses the user can view.
        set_config('searchallavailablecourses', 1);

        // Allow "Authenticated user" role to view the course without being enrolled in it.
        $userrole = $DB->get_record('role', ['shortname' => 'user'], '*', MUST_EXIST);
        role_change_permission($userrole->id, $context, 'moodle/course:view', CAP_ALLOW);

        $usercontexts = $search->get_areas_user_accesses([$course->id])->usercontexts;
        $this->assertNotEmpty($usercontexts);
        $this->assertArrayHasKey('core_course-course', $usercontexts);
        $this->assertEquals($context->id, reset($usercontexts['core_course-course']));
    }

    /**
     * Test get_areas_user_accesses with regard to the 'all available courses' config option.
     *
     * @return void
     */
    public function test_search_user_accesses_allavailable(): void {
        global $DB, $CFG;

        $this->resetAfterTest();

        // Front page, including a forum.
        $frontpage = $DB->get_record('course', array('id' => SITEID));
        $forumfront = $this->getDataGenerator()->create_module('forum', array('course' => $frontpage->id));
        $forumfrontctx = \context_module::instance($forumfront->cmid);

        // Course 1 does not allow guest access.
        $course1 = $this->getDataGenerator()->create_course((object)array(
                'enrol_guest_status_0' => ENROL_INSTANCE_DISABLED,
                'enrol_guest_password_0' => ''));
        $forum1 = $this->getDataGenerator()->create_module('forum', array('course' => $course1->id));
        $forum1ctx = \context_module::instance($forum1->cmid);

        // Course 2 does not allow guest but is accessible by all users.
        $course2 = $this->getDataGenerator()->create_course((object)array(
                'enrol_guest_status_0' => ENROL_INSTANCE_DISABLED,
                'enrol_guest_password_0' => ''));
        $course2ctx = \context_course::instance($course2->id);
        $forum2 = $this->getDataGenerator()->create_module('forum', array('course' => $course2->id));
        $forum2ctx = \context_module::instance($forum2->cmid);
        assign_capability('moodle/course:view', CAP_ALLOW, $CFG->defaultuserroleid, $course2ctx->id);

        // Course 3 allows guest access without password.
        $course3 = $this->getDataGenerator()->create_course((object)array(
                'enrol_guest_status_0' => ENROL_INSTANCE_ENABLED,
                'enrol_guest_password_0' => ''));
        $forum3 = $this->getDataGenerator()->create_module('forum', array('course' => $course2->id));
        $forum3ctx = \context_module::instance($forum3->cmid);

        // Student user is enrolled in course 1.
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, 'student');

        // No access user is just a user with no permissions.
        $noaccess = $this->getDataGenerator()->create_user();

        // First test without the all available option.
        $search = \testable_core_search::instance();

        // Admin user can access everything.
        $this->setAdminUser();
        $this->assertEquals((object)['everything' => true], $search->get_areas_user_accesses());

        // No-access user can access only the front page forum.
        $this->setUser($noaccess);
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $this->assertEquals([$forumfrontctx->id], array_keys($contexts[$this->forumpostareaid]));

        // Student can access the front page forum plus the enrolled one.
        $this->setUser($student);
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $this->assertEquals([$forum1ctx->id, $forumfrontctx->id],
                array_keys($contexts[$this->forumpostareaid]));

        // Now turn on the all available option.
        set_config('searchallavailablecourses', 1);

        // Admin user can access everything.
        $this->setAdminUser();
        $this->assertEquals((object)['everything' => true], $search->get_areas_user_accesses());

        // No-access user can access the front page forum and course 2, 3.
        $this->setUser($noaccess);
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $this->assertEquals([$forum2ctx->id, $forum3ctx->id, $forumfrontctx->id],
                array_keys($contexts[$this->forumpostareaid]));

        // Student can access the front page forum plus the enrolled one plus courses 2, 3.
        $this->setUser($student);
        $contexts = $search->get_areas_user_accesses()->usercontexts;
        $this->assertEquals([$forum1ctx->id, $forum2ctx->id, $forum3ctx->id, $forumfrontctx->id],
                array_keys($contexts[$this->forumpostareaid]));
    }

    /**
     * Tests group-related aspects of the get_areas_user_accesses function.
     */
    public function test_search_user_accesses_groups(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create 2 courses each with 2 groups and 2 forums (separate/visible groups).
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $course2 = $generator->create_course();
        $group1 = $generator->create_group(['courseid' => $course1->id]);
        $group2 = $generator->create_group(['courseid' => $course1->id]);
        $group3 = $generator->create_group(['courseid' => $course2->id]);
        $group4 = $generator->create_group(['courseid' => $course2->id]);
        $forum1s = $generator->create_module('forum', ['course' => $course1->id, 'groupmode' => SEPARATEGROUPS]);
        $id1s = \context_module::instance($forum1s->cmid)->id;
        $forum1v = $generator->create_module('forum', ['course' => $course1->id, 'groupmode' => VISIBLEGROUPS]);
        $id1v = \context_module::instance($forum1v->cmid)->id;
        $forum2s = $generator->create_module('forum', ['course' => $course2->id, 'groupmode' => SEPARATEGROUPS]);
        $id2s = \context_module::instance($forum2s->cmid)->id;
        $forum2n = $generator->create_module('forum', ['course' => $course2->id, 'groupmode' => NOGROUPS]);
        $id2n = \context_module::instance($forum2n->cmid)->id;

        // Get search instance.
        $search = \testable_core_search::instance();
        $search->add_core_search_areas();

        // User 1 is a manager in one course and a student in the other one. They belong to
        // all of the groups 1, 2, 3, and 4.
        $user1 = $generator->create_user();
        $generator->enrol_user($user1->id, $course1->id, 'manager');
        $generator->enrol_user($user1->id, $course2->id, 'student');
        groups_add_member($group1, $user1);
        groups_add_member($group2, $user1);
        groups_add_member($group3, $user1);
        groups_add_member($group4, $user1);

        $this->setUser($user1);
        $accessinfo = $search->get_areas_user_accesses();
        $contexts = $accessinfo->usercontexts;

        // Double-check all the forum contexts.
        $postcontexts = $contexts['mod_forum-post'];
        sort($postcontexts);
        $this->assertEquals([$id1s, $id1v, $id2s, $id2n], $postcontexts);

        // Only the context in the second course (no accessallgroups) is restricted.
        $restrictedcontexts = $accessinfo->separategroupscontexts;
        sort($restrictedcontexts);
        $this->assertEquals([$id2s], $restrictedcontexts);

        // Only the groups from the second course (no accessallgroups) are included.
        $groupids = $accessinfo->usergroups;
        sort($groupids);
        $this->assertEquals([$group3->id, $group4->id], $groupids);

        // User 2 is a student in each course and belongs to groups 2 and 4.
        $user2 = $generator->create_user();
        $generator->enrol_user($user2->id, $course1->id, 'student');
        $generator->enrol_user($user2->id, $course2->id, 'student');
        groups_add_member($group2, $user2);
        groups_add_member($group4, $user2);

        $this->setUser($user2);
        $accessinfo = $search->get_areas_user_accesses();
        $contexts = $accessinfo->usercontexts;

        // Double-check all the forum contexts.
        $postcontexts = $contexts['mod_forum-post'];
        sort($postcontexts);
        $this->assertEquals([$id1s, $id1v, $id2s, $id2n], $postcontexts);

        // Both separate groups forums are restricted.
        $restrictedcontexts = $accessinfo->separategroupscontexts;
        sort($restrictedcontexts);
        $this->assertEquals([$id1s, $id2s], $restrictedcontexts);

        // Groups from both courses are included.
        $groupids = $accessinfo->usergroups;
        sort($groupids);
        $this->assertEquals([$group2->id, $group4->id], $groupids);

        // User 3 is a manager at system level.
        $user3 = $generator->create_user();
        role_assign($DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST), $user3->id,
                \context_system::instance());

        $this->setUser($user3);
        $accessinfo = $search->get_areas_user_accesses();

        // Nothing is restricted and no groups are relevant.
        $this->assertEquals([], $accessinfo->separategroupscontexts);
        $this->assertEquals([], $accessinfo->usergroups);
    }

    /**
     * test_is_search_area
     *
     * @return void
     */
    public function test_is_search_area(): void {

        $this->assertFalse(\testable_core_search::is_search_area('\asd\asd'));
        $this->assertFalse(\testable_core_search::is_search_area('\mod_forum\search\posta'));
        $this->assertFalse(\testable_core_search::is_search_area('\core_search\base_mod'));
        $this->assertTrue(\testable_core_search::is_search_area('\mod_forum\search\post'));
        $this->assertTrue(\testable_core_search::is_search_area('\\mod_forum\\search\\post'));
        $this->assertTrue(\testable_core_search::is_search_area('mod_forum\\search\\post'));
    }

    /**
     * Tests the request_index function used for reindexing certain contexts. This only tests
     * adding things to the request list, it doesn't test that they are actually indexed by the
     * scheduled task.
     */
    public function test_request_index(): void {
        global $DB;

        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course1ctx = \context_course::instance($course1->id);
        $course2 = $this->getDataGenerator()->create_course();
        $course2ctx = \context_course::instance($course2->id);
        $forum1 = $this->getDataGenerator()->create_module('forum', ['course' => $course1->id]);
        $forum1ctx = \context_module::instance($forum1->cmid);
        $forum2 = $this->getDataGenerator()->create_module('forum', ['course' => $course2->id]);
        $forum2ctx = \context_module::instance($forum2->cmid);

        // Initially no requests.
        $this->assertEquals(0, $DB->count_records('search_index_requests'));

        // Request update for course 1, all areas.
        \core_search\manager::request_index($course1ctx);

        // Check all details of entry.
        $results = array_values($DB->get_records('search_index_requests'));
        $this->assertCount(1, $results);
        $this->assertEquals($course1ctx->id, $results[0]->contextid);
        $this->assertEquals('', $results[0]->searcharea);
        $now = time();
        $this->assertLessThanOrEqual($now, $results[0]->timerequested);
        $this->assertGreaterThan($now - 10, $results[0]->timerequested);
        $this->assertEquals('', $results[0]->partialarea);
        $this->assertEquals(0, $results[0]->partialtime);

        // Request forum 1, all areas; not added as covered by course 1.
        \core_search\manager::request_index($forum1ctx);
        $this->assertEquals(1, $DB->count_records('search_index_requests'));

        // Request forum 1, specific area; not added as covered by course 1 all areas.
        \core_search\manager::request_index($forum1ctx, 'forum-post');
        $this->assertEquals(1, $DB->count_records('search_index_requests'));

        // Request course 1 again, specific area; not added as covered by all areas.
        \core_search\manager::request_index($course1ctx, 'forum-post');
        $this->assertEquals(1, $DB->count_records('search_index_requests'));

        // Request course 1 again, all areas; not needed as covered already.
        \core_search\manager::request_index($course1ctx);
        $this->assertEquals(1, $DB->count_records('search_index_requests'));

        // Request course 2, specific area.
        \core_search\manager::request_index($course2ctx, 'label-activity');
        // Note: I'm ordering by ID for convenience - this is dangerous in real code (see MDL-43447)
        // but in a unit test it shouldn't matter as nobody is using clustered databases for unit
        // test.
        $results = array_values($DB->get_records('search_index_requests', null, 'id'));
        $this->assertCount(2, $results);
        $this->assertEquals($course1ctx->id, $results[0]->contextid);
        $this->assertEquals($course2ctx->id, $results[1]->contextid);
        $this->assertEquals('label-activity', $results[1]->searcharea);

        // Request forum 2, same specific area; not added.
        \core_search\manager::request_index($forum2ctx, 'label-activity');
        $this->assertEquals(2, $DB->count_records('search_index_requests'));

        // Request forum 2, different specific area; added.
        \core_search\manager::request_index($forum2ctx, 'forum-post');
        $this->assertEquals(3, $DB->count_records('search_index_requests'));

        // Request forum 2, all areas; also added. (Note: This could obviously remove the previous
        // one, but for simplicity, I didn't make it do that; also it could perhaps cause problems
        // if we had already begun processing the previous entry.)
        \core_search\manager::request_index($forum2ctx);
        $this->assertEquals(4, $DB->count_records('search_index_requests'));

        // Clear queue and do tests relating to priority.
        $DB->delete_records('search_index_requests');

        // Request forum 1, specific area, priority 100.
        \core_search\manager::request_index($forum1ctx, 'forum-post', 100);
        $results = array_values($DB->get_records('search_index_requests', null, 'id'));
        $this->assertCount(1, $results);
        $this->assertEquals(100, $results[0]->indexpriority);

        // Request forum 1, same area, lower priority; no change.
        \core_search\manager::request_index($forum1ctx, 'forum-post', 99);
        $results = array_values($DB->get_records('search_index_requests', null, 'id'));
        $this->assertCount(1, $results);
        $this->assertEquals(100, $results[0]->indexpriority);

        // Request forum 1, same area, higher priority; priority stored changes.
        \core_search\manager::request_index($forum1ctx, 'forum-post', 101);
        $results = array_values($DB->get_records('search_index_requests', null, 'id'));
        $this->assertCount(1, $results);
        $this->assertEquals(101, $results[0]->indexpriority);

        // Request forum 1, all areas, lower priority; adds second entry.
        \core_search\manager::request_index($forum1ctx, '', 100);
        $results = array_values($DB->get_records('search_index_requests', null, 'id'));
        $this->assertCount(2, $results);
        $this->assertEquals(100, $results[1]->indexpriority);

        // Request course 1, all areas, lower priority; adds third entry.
        \core_search\manager::request_index($course1ctx, '', 99);
        $results = array_values($DB->get_records('search_index_requests', null, 'id'));
        $this->assertCount(3, $results);
        $this->assertEquals(99, $results[2]->indexpriority);
    }

    /**
     * Tests the process_index_requests function.
     */
    public function test_process_index_requests(): void {
        global $DB;

        $this->resetAfterTest();

        $search = \testable_core_search::instance();

        // When there are no index requests, nothing gets logged.
        $progress = new \progress_trace_buffer(new \text_progress_trace(), false);
        $search->process_index_requests(0.0, $progress);
        $out = $progress->get_buffer();
        $progress->reset_buffer();
        $this->assertEquals('', $out);

        // Set up the course with 3 forums.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'TCourse']);
        $forum1 = $generator->create_module('forum', ['course' => $course->id, 'name' => 'TForum1']);
        $forum2 = $generator->create_module('forum', ['course' => $course->id, 'name' => 'TForum2']);
        $forum3 = $generator->create_module('forum', ['course' => $course->id, 'name' => 'TForum3']);

        // Hack the forums so they have different creation times.
        $now = time();
        $DB->set_field('forum', 'timemodified', $now - 30, ['id' => $forum1->id]);
        $DB->set_field('forum', 'timemodified', $now - 20, ['id' => $forum2->id]);
        $DB->set_field('forum', 'timemodified', $now - 10, ['id' => $forum3->id]);
        $forum2time = $now - 20;

        // Make 2 index requests.
        \testable_core_search::fake_current_time($now - 3);
        $search::request_index(\context_course::instance($course->id), 'mod_label-activity');
        \testable_core_search::fake_current_time($now - 2);
        $search::request_index(\context_module::instance($forum1->cmid));

        // Run with no time limit.
        $search->process_index_requests(0.0, $progress);
        $out = $progress->get_buffer();
        $progress->reset_buffer();

        // Check that it's done both areas.
        $this->assertStringContainsString(
                'Indexing requested context: Course: TCourse (search area: mod_label-activity)',
                $out);
        $this->assertStringContainsString(
                'Completed requested context: Course: TCourse (search area: mod_label-activity)',
                $out);
        $this->assertStringContainsString('Indexing requested context: Forum: TForum1', $out);
        $this->assertStringContainsString('Completed requested context: Forum: TForum1', $out);

        // Check the requests database table is now empty.
        $this->assertEquals(0, $DB->count_records('search_index_requests'));

        // Request indexing the course a couple of times.
        \testable_core_search::fake_current_time($now - 3);
        $search::request_index(\context_course::instance($course->id), 'mod_forum-activity');
        \testable_core_search::fake_current_time($now - 2);
        $search::request_index(\context_course::instance($course->id), 'mod_forum-post');

        // Do the processing again with a time limit and indexing delay. The time limit is too
        // small; because of the way the logic works, this means it will index 2 activities.
        $search->get_engine()->set_add_delay(0.2);
        $search->process_index_requests(0.1, $progress);
        $out = $progress->get_buffer();
        $progress->reset_buffer();

        // Confirm the right wrapper information was logged.
        $this->assertStringContainsString(
                'Indexing requested context: Course: TCourse (search area: mod_forum-activity)',
                $out);
        $this->assertStringContainsString('Stopping indexing due to time limit', $out);
        $this->assertStringContainsString(
                'Ending requested context: Course: TCourse (search area: mod_forum-activity)',
                $out);

        // Check the database table has been updated with progress.
        $records = array_values($DB->get_records('search_index_requests', null, 'searcharea'));
        $this->assertEquals('mod_forum-activity', $records[0]->partialarea);
        $this->assertEquals($forum2time, $records[0]->partialtime);

        // Run again and confirm it now finishes.
        $search->process_index_requests(2.0, $progress);
        $out = $progress->get_buffer();
        $progress->reset_buffer();
        $this->assertStringContainsString(
                'Completed requested context: Course: TCourse (search area: mod_forum-activity)',
                $out);
        $this->assertStringContainsString(
                'Completed requested context: Course: TCourse (search area: mod_forum-post)',
                $out);

        // Confirm table is now empty.
        $this->assertEquals(0, $DB->count_records('search_index_requests'));

        // Make 2 requests - first one is low priority.
        \testable_core_search::fake_current_time($now - 3);
        $search::request_index(\context_module::instance($forum1->cmid), 'mod_forum-activity',
                \core_search\manager::INDEX_PRIORITY_REINDEXING);
        \testable_core_search::fake_current_time($now - 2);
        $search::request_index(\context_module::instance($forum2->cmid), 'mod_forum-activity');

        // Process with short time limit and confirm it does the second one first.
        $search->process_index_requests(0.1, $progress);
        $out = $progress->get_buffer();
        $progress->reset_buffer();
        $this->assertStringContainsString(
                'Completed requested context: Forum: TForum2 (search area: mod_forum-activity)',
                $out);
        $search->process_index_requests(0.1, $progress);
        $out = $progress->get_buffer();
        $progress->reset_buffer();
        $this->assertStringContainsString(
                'Completed requested context: Forum: TForum1 (search area: mod_forum-activity)',
                $out);

        // Make a request for a course context...
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $search::request_index($context);

        // ...but then delete it (note: delete_course spews output, so we throw it away).
        ob_start();
        delete_course($course);
        ob_end_clean();

        // Process requests - it should only note the deleted context.
        $search->process_index_requests(10, $progress);
        $out = $progress->get_buffer();
        $progress->reset_buffer();
        $this->assertStringContainsString('Skipped deleted context: ' . $context->id, $out);

        // Confirm request table is now empty.
        $this->assertEquals(0, $DB->count_records('search_index_requests'));
    }

    /**
     * Test search area categories.
     */
    public function test_get_search_area_categories(): void {
        $categories = \core_search\manager::get_search_area_categories();

        $this->assertTrue(is_array($categories));
        $this->assertTrue(count($categories) >= 4); // We always should have 4 core categories.
        $this->assertArrayHasKey('core-all', $categories);
        $this->assertArrayHasKey('core-course-content', $categories);
        $this->assertArrayHasKey('core-courses', $categories);
        $this->assertArrayHasKey('core-users', $categories);

        foreach ($categories as $category) {
            $this->assertInstanceOf('\core_search\area_category', $category);
        }
    }

    /**
     * Test that we can find out if search area categories functionality is enabled.
     */
    public function test_is_search_area_categories_enabled(): void {
        $this->resetAfterTest();

        $this->assertFalse(\core_search\manager::is_search_area_categories_enabled());
        set_config('searchenablecategories', 1);
        $this->assertTrue(\core_search\manager::is_search_area_categories_enabled());
        set_config('searchenablecategories', 0);
        $this->assertFalse(\core_search\manager::is_search_area_categories_enabled());
    }

    /**
     * Test that we can find out if hiding all results category is enabled.
     */
    public function test_should_hide_all_results_category(): void {
        $this->resetAfterTest();

        $this->assertEquals(0, \core_search\manager::should_hide_all_results_category());
        set_config('searchhideallcategory', 1);
        $this->assertEquals(1, \core_search\manager::should_hide_all_results_category());
        set_config('searchhideallcategory', 0);
        $this->assertEquals(0, \core_search\manager::should_hide_all_results_category());
    }

    /**
     * Test that we can get default search category name.
     */
    public function test_get_default_area_category_name(): void {
        $this->resetAfterTest();

        $expected = 'core-all';
        $this->assertEquals($expected, \core_search\manager::get_default_area_category_name());

        set_config('searchhideallcategory', 1);
        $expected = 'core-course-content';
        $this->assertEquals($expected, \core_search\manager::get_default_area_category_name());

        set_config('searchhideallcategory', 0);
        $expected = 'core-all';
        $this->assertEquals($expected, \core_search\manager::get_default_area_category_name());
    }

    /**
     * Test that we can get correct search area category by its name.
     */
    public function test_get_search_area_category_by_name(): void {
        $this->resetAfterTest();

        $testcategory = \core_search\manager::get_search_area_category_by_name('test_random_name');
        $this->assertEquals('core-all', $testcategory->get_name());

        $testcategory = \core_search\manager::get_search_area_category_by_name('core-courses');
        $this->assertEquals('core-courses', $testcategory->get_name());

        set_config('searchhideallcategory', 1);
        $testcategory = \core_search\manager::get_search_area_category_by_name('test_random_name');
        $this->assertEquals('core-course-content', $testcategory->get_name());
    }

    /**
     * Test that we can check that "Include all visible courses" feature is enabled.
     */
    public function test_include_all_courses_enabled(): void {
        $this->resetAfterTest();
        $this->assertFalse(\core_search\manager::include_all_courses());
        set_config('searchincludeallcourses', 1);
        $this->assertTrue(\core_search\manager::include_all_courses());
    }

    /**
     * Test that we can correctly build a list of courses for a course filter for the search results.
     */
    public function test_build_limitcourseids(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();
        $course4 = $this->getDataGenerator()->create_course();

        $this->getDataGenerator()->enrol_user($USER->id, $course1->id);
        $this->getDataGenerator()->enrol_user($USER->id, $course3->id);

        $search = \testable_core_search::instance();

        $formdata = new \stdClass();
        $formdata->courseids = [];
        $formdata->mycoursesonly = false;
        $limitcourseids = $search->build_limitcourseids($formdata);
        $this->assertEquals(false, $limitcourseids);

        $formdata->courseids = [];
        $formdata->mycoursesonly = true;
        $limitcourseids = $search->build_limitcourseids($formdata);
        $this->assertEquals([$course1->id, $course3->id], $limitcourseids);

        $formdata->courseids = [$course1->id, $course2->id, $course4->id];
        $formdata->mycoursesonly = false;
        $limitcourseids = $search->build_limitcourseids($formdata);
        $this->assertEquals([$course1->id, $course2->id, $course4->id], $limitcourseids);

        $formdata->courseids = [$course1->id, $course2->id, $course4->id];
        $formdata->mycoursesonly = true;
        $limitcourseids = $search->build_limitcourseids($formdata);
        $this->assertEquals([$course1->id], $limitcourseids);
    }

    /**
     * Test data for test_parse_areaid test fucntion.
     *
     * @return array
     */
    public static function parse_search_area_id_data_provider(): array {
        return [
            ['mod_book-chapter', ['mod_book', 'search_chapter']],
            ['mod_customcert-activity', ['mod_customcert', 'search_activity']],
            ['core_course-mycourse', ['core_search', 'core_course_mycourse']],
        ];
    }

    /**
     * Test that manager class can parse area id correctly.
     * @dataProvider parse_search_area_id_data_provider
     *
     * @param string $areaid Area id to parse.
     * @param array $expected Expected result of parsing.
     */
    public function test_parse_search_area_id($areaid, $expected): void {
        $this->assertEquals($expected, \core_search\manager::parse_areaid($areaid));
    }

    /**
     * Test that manager class will throw an exception when parsing an invalid area id.
     */
    public function test_parse_invalid_search_area_id(): void {
        $this->expectException('coding_exception');
        $this->expectExceptionMessage('Trying to parse invalid search area id invalid_area');
        \core_search\manager::parse_areaid('invalid_area');
    }

    /**
     * Test getting a coding exception when trying to lean up existing search area.
     */
    public function test_cleaning_up_existing_search_area(): void {
        $expectedmessage = "Area mod_assign-activity exists. Please use appropriate search area class to manipulate the data.";

        $this->expectException('coding_exception');
        $this->expectExceptionMessage($expectedmessage);

        \core_search\manager::clean_up_non_existing_area('mod_assign-activity');
    }

    /**
     * Test clean up of non existing search area.
     */
    public function test_clean_up_non_existing_search_area(): void {
        global $DB;

        $this->resetAfterTest();

        $areaid = 'core_course-mycourse';
        $plugin = 'core_search';

        // Get all settings to DB and make sure they are there.
        foreach (\core_search\base::get_settingnames() as $settingname) {
            $record = new \stdClass();
            $record->plugin = $plugin;
            $record->name = 'core_course_mycourse'. $settingname;
            $record->value = 'test';

            $DB->insert_record('config_plugins', $record);
            $this->assertTrue($DB->record_exists('config_plugins', ['plugin' => $plugin, 'name' => $record->name]));
        }

        // Clean up the search area.
        \core_search\manager::clean_up_non_existing_area($areaid);

        // Check that records are not in DB after we ran clean up.
        foreach (\core_search\base::get_settingnames() as $settingname) {
            $plugin = 'core_search';
            $name = 'core_course_mycourse'. $settingname;
            $this->assertFalse($DB->record_exists('config_plugins', ['plugin' => $plugin, 'name' => $name]));
        }
    }

    /**
     * Tests the context_deleted, course_deleting_start, and course_deleting_finish methods.
     */
    public function test_context_deletion(): void {
        $this->resetAfterTest();

        // Create one course with 4 activities, and another with one.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $page1 = $generator->create_module('page', ['course' => $course1]);
        $context1 = \context_module::instance($page1->cmid);
        $page2 = $generator->create_module('page', ['course' => $course1]);
        $page3 = $generator->create_module('page', ['course' => $course1]);
        $context3 = \context_module::instance($page3->cmid);
        $page4 = $generator->create_module('page', ['course' => $course1]);
        $course2 = $generator->create_course();
        $page5 = $generator->create_module('page', ['course' => $course2]);
        $context5 = \context_module::instance($page5->cmid);

        // Also create a user.
        $user = $generator->create_user();
        $usercontext = \context_user::instance($user->id);

        $search = \testable_core_search::instance();

        // Delete two of the pages individually.
        course_delete_module($page1->cmid);
        course_delete_module($page3->cmid);

        // Delete the course with another two.
        delete_course($course1->id, false);

        // Delete the user.
        delete_user($user);

        // Delete the page from the other course.
        course_delete_module($page5->cmid);

        // It should have deleted the contexts and the course, but not the contexts in the course.
        $expected = [
            ['context', $context1->id],
            ['context', $context3->id],
            ['course', $course1->id],
            ['context', $usercontext->id],
            ['context', $context5->id]
        ];
        $this->assertEquals($expected, $search->get_engine()->get_and_clear_deletes());
    }

    /**
     * Tests the indexing delay (used to avoid race conditions) in {@see manager::index()}.
     *
     * @covers \core_search\manager::index
     */
    public function test_indexing_delay(): void {
        global $USER, $CFG;

        $this->resetAfterTest();

        // Normally the indexing delay is turned off for test scripts because we don't want to have
        // to wait 5 seconds after creating anything to index it and it's not like there will be a
        // race condition (indexing doesn't run at same time as adding). This turns it on.
        $CFG->searchindexingdelayfortestscript = true;

        $this->setAdminUser();

        // Create a course and a forum.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);

        // Skip ahead 5 seconds so everything gets indexed.
        $now = time();
        $now += manager::INDEXING_DELAY;
        $search = \testable_core_search::instance();
        $search->fake_current_time($now);
        $search->index();
        $search->get_engine()->get_and_clear_added_documents();

        // Basic discussion data.
        $basicdata = [
            'course' => $course->id,
            'forum' => $forum->id,
            'userid' => $USER->id,
        ];
        // Discussion so old it's prior to indexing delay (not realistic).
        $generator->get_plugin_generator('mod_forum')->create_discussion(array_merge($basicdata,
            ['timemodified' => $now - manager::INDEXING_DELAY, 'name' => 'Frog']));
        // Discussion just within indexing delay (simulates if it took a while to add to database).
        $generator->get_plugin_generator('mod_forum')->create_discussion(array_merge($basicdata,
            ['timemodified' => $now - (manager::INDEXING_DELAY - 1), 'name' => 'Toad']));
        // Move time along a bit.
        $now += 100;
        $search->fake_current_time($now);
        // Discussion that happened 5 seconds before the new now.
        $generator->get_plugin_generator('mod_forum')->create_discussion(array_merge($basicdata,
            ['timemodified' => $now - (manager::INDEXING_DELAY), 'name' => 'Zombie']));
        // This one only happened 4 seconds before so it shouldn't be indexed yet.
        $generator->get_plugin_generator('mod_forum')->create_discussion(array_merge($basicdata,
            ['timemodified' => $now - (manager::INDEXING_DELAY - 1), 'name' => 'Werewolf']));

        // Reindex and check that it added the middle two discussions.
        $search->index();
        $added = $search->get_engine()->get_and_clear_added_documents();
        $this->assertCount(2, $added);
        $this->assertEquals('Toad', $added[0]->get('title'));
        $this->assertEquals('Zombie', $added[1]->get('title'));

        // Move time forwards a couple of seconds and now the last one will get indexed.
        $now += 2;
        $search->fake_current_time($now);
        $search->index();
        $added = $search->get_engine()->get_and_clear_added_documents();
        $this->assertCount(1, $added);
        $this->assertEquals('Werewolf', $added[0]->get('title'));
    }
}
