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
 * Moodleform for creating/editing a course-level learning outcome.
 * Scale is optional, not required.
 *
 * @package   core_grades
 * @copyright 2026 Moodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/grade/grade_scale.php');

/**
 * Form for creating or editing a course-level learning outcome.
 */
class learning_outcome_edit_form extends moodleform {

    /** @inheritdoc */
    public function definition(): void {
        global $CFG, $COURSE;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('learningoutcomes', 'grades'));

        $mform->addElement('text', 'fullname', get_string('outcomefullname', 'grades'), 'size="60"');
        $mform->addRule('fullname', get_string('required'), 'required');
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addHelpButton('fullname', 'outcomefullname', 'grades');

        $mform->addElement('text', 'shortname', get_string('outcomeshortname', 'grades'), 'size="20"');
        $mform->addRule('shortname', get_string('required'), 'required');
        $mform->setType('shortname', PARAM_NOTAGS);

        $mform->addElement('editor', 'description_editor', get_string('description'), null,
            $this->_customdata['editoroptions']);

        // Scale is optional. A "none" option lets teachers skip it.
        $scaleopts = [0 => get_string('none')];
        $mform->addElement('selectwithlink', 'scaleid',
            get_string('learningoutcomesscaleoptional', 'grades'),
            $scaleopts, null,
            ['link' => $CFG->wwwroot . '/grade/edit/scale/edit.php?courseid=' . $COURSE->id,
             'label' => get_string('scalescustomcreate')]);
        $mform->addHelpButton('scaleid', 'learningoutcomesscaleoptional', 'grades');
        $mform->setDefault('scaleid', 0);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $COURSE->id);
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons();
    }

    /** @inheritdoc */
    public function definition_after_data(): void {
        $mform = $this->_form;

        $courseid = (int)$mform->getElementValue('courseid');
        $options = [];

        if ($courseid && $scales = grade_scale::fetch_all_local($courseid)) {
            //$options[-1] = '--' . get_string('scalescustom');
            foreach ($scales as $scale) {
                $options[$scale->id] = $scale->get_name();
            }
        }
        if ($global = grade_scale::fetch_all_global()) {
            //$options[-2] = '--' . get_string('scalesstandard');
            foreach ($global as $scale) {
                $options[$scale->id] = $scale->get_name();
            }
        }
        $mform->getElement('scaleid')->load($options);

        // Freeze scaleid if outcome is already in use (keep grade integrity).
        $id = (int)$mform->getElementValue('id');
        if ($id) {
            $outcome = grade_outcome::fetch(['id' => $id]);
            if ($outcome && $outcome->get_item_uses_count()) {
                $mform->hardFreeze('scaleid');
            }
        }
    }

    /** @inheritdoc */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);

        // If a scale is selected, validate it's not a custom (course-local) scale
        // when the outcome is being marked as standard/global.
        if (!empty($data['scaleid']) && $data['scaleid'] > 0) {
            $scale = grade_scale::fetch(['id' => $data['scaleid']]);
            if ($scale && !empty($scale->courseid) && !empty($data['standard'])) {
                $errors['scaleid'] = get_string('cannotuselocalscopeinglobal', 'grades');
            }
        }

        // Prevent duplicate short names within the same course or among global outcomes.
        if (!empty($data['shortname'])) {
            $courseid = (int)$data['courseid'];
            $currentid = (int)($data['id'] ?? 0);

            // Check course-local outcomes.
            $sql = 'SELECT id FROM {grade_outcomes} WHERE shortname = :shortname AND courseid = :courseid';
            $params = ['shortname' => $data['shortname'], 'courseid' => $courseid];
            if ($currentid) {
                $sql .= ' AND id <> :currentid';
                $params['currentid'] = $currentid;
            }

            // Also check global outcomes (courseid IS NULL).
            $sqlglobal = 'SELECT id FROM {grade_outcomes} WHERE shortname = :shortname AND courseid IS NULL';
            $globalparams = ['shortname' => $data['shortname']];
            if ($currentid) {
                $sqlglobal .= ' AND id <> :currentid';
                $globalparams['currentid'] = $currentid;
            }

            if ($DB->record_exists_sql($sql, $params) || $DB->record_exists_sql($sqlglobal, $globalparams)) {
                $errors['shortname'] = get_string('shortnametaken', 'grades', $data['shortname']);
            }
        }

        return $errors;
    }
}
