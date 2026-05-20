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

namespace core_grades\output;

use moodle_url;

/**
 * Renderable class for the action bar elements in the gradebook course outcomes page.
 *
 * @package    core_grades
 * @copyright  2021 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_outcomes_action_bar extends action_bar {

    /**
     * Returns the template for the action bar.
     *
     * @return string
     */
    public function get_template(): string {
        return 'core_grades/course_outcomes_action_bar';
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        if ($this->context->contextlevel !== CONTEXT_COURSE) {
            return [];
        }
        $courseid = $this->context->instanceid;
        // Get the data used to output the general navigation selector.
        $generalnavselector = new general_action_bar($this->context,
            new moodle_url('/grade/edit/outcome/course.php', ['id' => $courseid]), 'outcome', 'course');
        $data = $generalnavselector->export_for_template($output);

        if (has_capability('moodle/grade:manageoutcomes', $this->context)) {
            // Add a button to the action bar with a link to the 'add new learning outcome' page.
            $addoutcomelink = new moodle_url('/grade/edit/outcome/edit.php', ['courseid' => $courseid]);
            $addoutcomebutton = new \single_button($addoutcomelink,
                get_string('learningoutcomescreate', 'grades'),
                'get', \single_button::BUTTON_PRIMARY);
            $data['addoutcomebutton'] = $addoutcomebutton->export_for_template($output);

            // Build actions dropdown (matches Moodle action-menu pattern, e.g. assignment grading page).
            $menu = new \action_menu();
            $menu->set_menu_trigger(get_string('actions'), 'btn btn-outline-secondary');

            // Add a menu action with a link to the 'tag activities' page.
            $tagactivitieslink = new moodle_url('/grade/edit/outcome/tag_activities.php', ['courseid' => $courseid]);
            $menu->add(new \action_menu_link_secondary($tagactivitieslink, null,
                get_string('learningoutcomestagactivities', 'grades')));

            // Add a menu action with a link to the alignment report page.
            $alignmentreportlink = new moodle_url('/report/learningoutcomes/index.php', ['id' => $courseid]);
            $menu->add(new \action_menu_link_secondary($alignmentreportlink, null,
                get_string('learningoutcomesalignmentreport', 'grades')));

            $divider = new \action_menu_filler();
            $divider->primary = false;
            $menu->add($divider);

            // Add a menu action with a link to import outcomes.
            $importoutcomeslink = new moodle_url('/grade/edit/outcome/import.php', ['courseid' => $courseid]);
            $menu->add(new \action_menu_link_secondary($importoutcomeslink, null,
                get_string('importoutcomes', 'grades')));

            // Add a menu action with a link to export all outcomes.
            $exportoutcomeslink = new moodle_url('/grade/edit/outcome/export.php',
                ['id' => $courseid, 'sesskey' => sesskey()]);
            $menu->add(new \action_menu_link_secondary($exportoutcomeslink, null,
                get_string('exportalloutcomes', 'grades')));

            $data['actionsmenu'] = $output->render($menu);
        }

        return $data;
    }
}
