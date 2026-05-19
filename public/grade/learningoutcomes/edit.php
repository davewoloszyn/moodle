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
 * Create / edit a course-level learning outcome.
 *
 * @package   core_grades
 * @copyright 2026 Moodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->libdir . '/grade/grade_outcome.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/edit_form.php');

$courseid = required_param('courseid', PARAM_INT);
$id       = optional_param('id', 0, PARAM_INT);   // Outcome id (0 = new).

$course  = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('moodle/grade:manage', $context);

if (empty($CFG->enableoutcomes)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('learningoutcomescoursedisabled', 'grades'));
}

$PAGE->set_url('/grade/learningoutcomes/edit.php', ['courseid' => $courseid, 'id' => $id]);
$PAGE->set_pagelayout('incourse');

$editoroptions = [
    'maxfiles'  => EDITOR_UNLIMITED_FILES,
    'maxbytes'  => $CFG->maxbytes,
    'trusttext' => false,
    'context'   => $context,
];

// Load existing outcome or build a new one.
if ($id) {
    $outcome = grade_outcome::fetch(['id' => $id]);
    if (!$outcome) {
        throw new moodle_exception('invalidoutcomeid', 'grades');
    }
    // Cast to stdClass so file_prepare_standard_editor does not create dynamic
    // properties on the typed grade_outcome object (deprecated in PHP 8.2+).
    $outcome = (object)(array)$outcome;
    $outcome = file_prepare_standard_editor($outcome, 'description', $editoroptions,
        $context, 'grade', 'outcome', $outcome->id);
    $PAGE->set_title(get_string('editoutcome', 'grades'));
    $PAGE->set_heading(format_string($course->fullname));
} else {
    $outcome = new stdClass();
    $outcome->id       = 0;
    $outcome->courseid = $courseid;
    $outcome->scaleid  = 0;
    $outcome = file_prepare_standard_editor($outcome, 'description', $editoroptions,
        $context, 'grade', 'outcome', null);
    $PAGE->set_title(get_string('learningoutcomescreate', 'grades'));
    $PAGE->set_heading(format_string($course->fullname));
}

$mform = new learning_outcome_edit_form(null, ['editoroptions' => $editoroptions]);
$mform->set_data($outcome);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/grade/learningoutcomes/index.php', ['courseid' => $courseid]));

} else if ($formdata = $mform->get_data()) {
    $formdata = file_postupdate_standard_editor($formdata, 'description', $editoroptions,
        $context, 'grade', 'outcome', $formdata->id ?: null);

    if ($formdata->id) {
        // Update.
        $existing = grade_outcome::fetch(['id' => $formdata->id]);
        $existing->fullname          = $formdata->fullname;
        $existing->shortname         = $formdata->shortname;
        $existing->description       = $formdata->description;
        $existing->descriptionformat = $formdata->descriptionformat;
        $existing->scaleid           = (!empty($formdata->scaleid) && $formdata->scaleid > 0) ? $formdata->scaleid : null;
        $existing->update('grade/learningoutcomes');
    } else {
        // Create new course-local outcome.
        $new                    = new grade_outcome();
        $new->courseid          = $courseid;
        $new->fullname          = $formdata->fullname;
        $new->shortname         = $formdata->shortname;
        $new->description       = $formdata->description;
        $new->descriptionformat = $formdata->descriptionformat;
        $new->scaleid           = (!empty($formdata->scaleid) && $formdata->scaleid > 0) ? $formdata->scaleid : null;
        $new->insert('grade/learningoutcomes');
    }

    redirect(new moodle_url('/grade/learningoutcomes/index.php', ['courseid' => $courseid]),
        get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($id ? get_string('editoutcome', 'grades') : get_string('learningoutcomescreate', 'grades'));
$mform->display();
echo $OUTPUT->footer();
