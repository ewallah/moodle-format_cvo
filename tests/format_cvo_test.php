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
 * format_cvo related unit tests
 *
 * @package    format_cvo
 * @copyright  2018 cvo-ssh.be
 * @author     Renaat Debleu <info@eWallah.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * format_cvo related unit tests
 *
 * @package    format_cvo
 * @copyright  2018 cvo-ssh.be
 * @author     Renaat Debleu <info@eWallah.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_cvo_testcase extends advanced_testcase {

    /**
     * Tests for format_cvo::get_section_name method with modified section names.
     */
    public function test_get_section_name_customised() {
        global $DB;
        $this->resetAfterTest(true);

        // Generate a course with 5 sections.
        $generator = $this->getDataGenerator();
        $numsections = 5;
        $course = $generator->create_course(['numsections' => $numsections, 'format' => 'cvo'], ['createsections' => true]);

        // Get section names for course.
        $coursesections = $DB->get_records('course_sections', ['course' => $course->id]);

        // Modify section names.
        $customname = "Custom Section";
        foreach ($coursesections as $section) {
            $section->name = "$customname $section->section";
            $DB->update_record('course_sections', $section);
        }

        // Requery updated section names then test get_section_name.
        $coursesections = $DB->get_records('course_sections', ['course' => $course->id]);
        $courseformat = course_get_format($course);
        foreach ($coursesections as $section) {
            // Assert that with modified section names, get_section_name returns the modified section name.
            $this->assertEquals($section->name, $courseformat->get_section_name($section));
        }
    }

    /**
     * Tests for format_cvo::get_default_section_name.
     */
    public function test_get_default_section_name() {
        global $DB;
        $this->resetAfterTest(true);

        // Generate a course with 5 sections.
        $generator = $this->getDataGenerator();
        $numsections = 5;
        $course = $generator->create_course(['numsections' => $numsections, 'format' => 'cvo'], ['createsections' => true]);

        // Get section names for course.
        $coursesections = $DB->get_records('course_sections', ['course' => $course->id]);

        // Test get_default_section_name with default section names.
        $courseformat = course_get_format($course);
        foreach ($coursesections as $section) {
            if ($section->section == 0) {
                $sectionname = get_string('section0name', 'format_cvo');
                $this->assertEquals($sectionname, 'General');
            } else {
                $sectionname = get_string('sectionname', 'format_cvo') . ' ' . $section->section;
                $this->assertEquals($sectionname, $courseformat->get_default_section_name($section));
            }
        }
    }

    /**
     * Test web service updating section name
     */
    public function test_update_inplace_editable() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/external/externallib.php');

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course(['numsections' => 5, 'format' => 'cvo'], ['createsections' => true]);
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 2]);

        // Call webservice without necessary permissions.
        try {
            core_external::update_inplace_editable('format_cvo', 'sectionname', $section->id, 'New section name');
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Course or activity not accessible. (Not enrolled)',
                    $e->getMessage());
        }

        // Change to teacher and make sure that section name can be updated using web service update_inplace_editable().
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $teacherrole->id);

        $res = core_external::update_inplace_editable('format_cvo', 'sectionname', $section->id, 'New section name');
        $res = external_api::clean_returnvalue(core_external::update_inplace_editable_returns(), $res);
        $this->assertEquals('New section name', $res['value']);
        $this->assertEquals('New section name', $DB->get_field('course_sections', 'name', ['id' => $section->id]));
    }

    /**
     * Test callback updating section name
     */
    public function test_inplace_editable() {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['numsections' => 5, 'format' => 'cvo'], ['createsections' => true]);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $teacherrole->id);
        $this->setUser($user);

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 2]);

        // Call callback format_cvo_inplace_editable() directly.
        $tmpl = component_callback('format_cvo', 'inplace_editable', ['sectionname', $section->id, 'Rename me again']);
        $this->assertInstanceOf('core\output\inplace_editable', $tmpl);
        $res = $tmpl->export_for_template($PAGE->get_renderer('core'));
        $this->assertEquals('Rename me again', $res['value']);
        $this->assertEquals('Rename me again', $DB->get_field('course_sections', 'name', ['id' => $section->id]));

        // Try updating using callback from mismatching course format.
        try {
            $tmpl = component_callback('format_weeks', 'inplace_editable', ['sectionname', $section->id, 'New name']);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals(1, preg_match('/^Can\'t find data record in database/', $e->getMessage()));
        }
    }

    /**
     * Test get_default_course_enddate.
     *
     * @return void
     */
    public function test_default_course_enddate() {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');

        $this->setTimezone('UTC');
        $startdate = 1445644800;
        $params = ['format' => 'cvo', 'numsections' => 5, 'startdate' => $startdate];
        $course = $this->getDataGenerator()->create_course($params);
        $category = $DB->get_record('course_categories', ['id' => $course->category]);

        $args = [
            'course' => $course,
            'category' => $category,
            'editoroptions' => [
                'context' => context_course::instance($course->id),
                'subdirs' => 0
            ],
            'returnto' => new moodle_url('/'),
            'returnurl' => new moodle_url('/'),
        ];

        $courseform = new testable_course_edit_form(null, $args);
        $courseform->definition_after_data();
        $enddate = strtotime(date("Y-m-d", $startdate) . " +1 year");
        $weeksformat = course_get_format($course->id);
        $enddata = $weeksformat->get_default_course_enddate($courseform->get_quick_form());
        $this->assertEquals($enddate, $enddata);
    }

    /**
     * Test privacy.
     */
    public function test_privacy() {
        $privacy = new format_cvo\privacy\provider();
        $this->assertEquals($privacy->get_reason(), 'privacy:metadata');
    }

    /**
     * Test upgrade.
     */
    public function test_upgrade() {
        global $CFG, $DB;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/course/format/cvo/db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 5, 'format' => 'cvo'], ['createsections' => true]);
        try {
            $this->assertTrue(xmldb_format_cvo_upgrade(time()));
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals(1, preg_match('/^Cannot downgrade/', $e->getMessage()));
        }
        $this->assertEquals(5, $DB->count_records('course_sections', ['course' => $course->id]));
        format_cvo_upgrade_remove_numsections();
        $this->assertEquals(5, $DB->count_records('course_sections', ['course' => $course->id]));
        format_cvo_upgrade_hide_extra_sections($course->id, 50);
        $this->assertEquals(5, $DB->count_records('course_sections', ['course' => $course->id]));
    }

    /**
     * Test renderer.
     */
    public function test_renderer() {
        global $CFG, $USER;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/course/format/cvo/renderer.php');
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 5, 'format' => 'cvo'], ['createsections' => true]);
        $user = $generator->create_user();
        $page = $generator->get_plugin_generator('mod_page')->create_instance(['course' => $course]);
        $forum = $generator->get_plugin_generator('mod_forum')->create_instance(['course' => $course]);
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->forum = $forum->id;
        $generator->get_plugin_generator('mod_forum')->create_discussion($record);
        $page = new moodle_page();
        $page->set_context(context_course::instance($course->id));
        $page->set_course($course);
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->set_url('/course/format.php?id=' . $course->id);
        $renderer = new \format_cvo_renderer($page, null);
        ob_start();
        $renderer->print_single_section_page($course, null, null, null, null, 1);
        $renderer->print_multiple_section_page($course, null, null, null, null, null);
        ob_end_clean();
        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info(1);
        $this->assertStringContainsString('Topic 1', $renderer->section_title($section, $course));
        $section = $modinfo->get_section_info(2);
        $this->assertStringContainsString('Topic 2', $renderer->section_title_without_link($section, $course));
        set_section_visible($course->id, 2, 0);
        $USER->editing = true;
        ob_start();
        $renderer->print_single_section_page($course, null, null, null, null, 2);
        $renderer->print_multiple_section_page($course, null, null, null, null, null);
        ob_end_clean();
    }
}
