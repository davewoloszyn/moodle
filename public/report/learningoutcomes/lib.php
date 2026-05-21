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
 * Callbacks for report_learningoutcomes (adds link to the course reports menu).
 *
 * @package    report_learningoutcomes
 * @copyright  2026 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the report link to the navigation block.
 *
 * @param navigation_node $navigation
 * @param stdClass        $course
 * @param context         $context
 */
function report_learningoutcomes_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context $context
): void {
    global $CFG;
    if (empty($CFG->enableoutcomes)) {
        return;
    }
    // Alignment report – visible to anyone with view capability.
    if (has_capability('gradereport/outcomes:view', $context)) {
        $url  = new moodle_url('/grade/report/outcomes/index.php', ['id' => $course->id]);
        $name = get_string('pluginname', 'report_learningoutcomes');
        $navigation->add($name, $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
    // Manage / tag outcomes – visible to teachers who can manage grades.
    if (has_capability('moodle/grade:manage', $context)) {
        $url  = new moodle_url('/grade/edit/outcome/course.php', ['id' => $course->id]);
        $name = get_string('learningoutcomesmanage', 'grades');
        $navigation->add($name, $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/outcomes', ''));
    }
}
