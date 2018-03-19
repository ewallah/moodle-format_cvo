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
 * Upgrade scripts for course format "cvo"
 *
 * @package    format_cvo
 * @copyright  2017 cvo_ssh.be
 * @author     Renaat Debleu (www.ewallah.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for format_cvo
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_format_cvo_upgrade($oldversion) {
    global $CFG;
    require_once($CFG->dirroot . '/course/format/cvo/db/upgradelib.php');

    if ($oldversion < 2017070600) {
        // Remove 'numsections' option and hide or delete orphaned sections.
        format_cvo_upgrade_remove_numsections();

        upgrade_plugin_savepoint(true, 2017070600, 'format', 'cvo');
    }

    if ($oldversion < 2018031900) {

        // During upgrade to Moodle 3.3 it could happen that general section (section 0) became 'invisible'.
        // It should always be visible.
        $DB->execute("UPDATE {course_sections} SET visible=1 WHERE visible=0 AND section=0 AND course IN
        (SELECT id FROM {course} WHERE format=?)", ['cvo']);

        upgrade_plugin_savepoint(true, 2018031900, 'format', 'cvo');
    }

    return true;
}
