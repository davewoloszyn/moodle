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
 * Course-level Learning Outcomes management page.
 *
 * Teachers can view, add, edit and delete learning outcomes for their course,
 * and navigate to the activity-tagging page.
 *
 * @package   core_grades
 * @copyright 2026 Moodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->libdir . '/grade/grade_outcome.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$delete   = optional_param('delete', 0, PARAM_INT);
$confirm  = optional_param('confirm', 0, PARAM_BOOL);
$addstandard = optional_param('addstandard', 0, PARAM_BOOL);
$addoutcomes = optional_param_array('addoutcomes', [], PARAM_INT);

$course  = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('moodle/grade:manage', $context);

if (empty($CFG->enableoutcomes)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('learningoutcomescoursedisabled', 'grades'));
}

if ($addstandard && confirm_sesskey()) {
    require_capability('moodle/grade:manageoutcomes', $context);

    $addedcount = 0;
    foreach ($addoutcomes as $outcomeid) {
        if (learningoutcomes_add_global_outcome_to_course($courseid, (int)$outcomeid)) {
            $addedcount++;
        }
    }

    redirect(
        new moodle_url('/grade/learningoutcomes/index.php', ['courseid' => $courseid]),
        $addedcount ? get_string('changessaved') : get_string('nothingtodisplay'),
        null,
        $addedcount ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_INFO
    );
}

$PAGE->set_url('/grade/learningoutcomes/index.php', ['courseid' => $courseid]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('learningoutcomespageheading', 'grades', format_string($course->fullname)));
$PAGE->set_heading(format_string($course->fullname));

// Handle deletion with CSRF protection.
if ($delete && $confirm && confirm_sesskey()) {
    $outcome = grade_outcome::fetch(['id' => $delete]);
    if ($outcome && ($outcome->courseid == $courseid || !$outcome->courseid)) {
        if ($outcome->get_item_uses_count() > 0) {
            redirect(
                new moodle_url('/grade/learningoutcomes/index.php', ['courseid' => $courseid]),
                get_string('learningoutcomesdeleteinuse', 'grades', format_string($outcome->fullname)),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        // Remove all activity tags.
        learningoutcomes_delete_outcome_tags((int)$outcome->id);
        // Remove the outcome itself (or just disassociate from course if global).
        if ($outcome->courseid == $courseid) {
            $outcome->delete('grade/learningoutcomes');
        } else {
            $DB->delete_records('grade_outcomes_courses', ['outcomeid' => $outcome->id, 'courseid' => $courseid]);
        }
        redirect(new moodle_url('/grade/learningoutcomes/index.php', ['courseid' => $courseid]),
            get_string('deleted'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Confirmation page for deletion.
if ($delete && !$confirm) {
    $outcome = grade_outcome::fetch(['id' => $delete]);
    if ($outcome) {
        echo $OUTPUT->header();
        $confirmurl = new moodle_url('/grade/learningoutcomes/index.php', [
            'courseid' => $courseid,
            'delete'   => $delete,
            'confirm'  => 1,
            'sesskey'  => sesskey(),
        ]);
        $cancelurl = new moodle_url('/grade/learningoutcomes/index.php', ['courseid' => $courseid]);
        echo $OUTPUT->confirm(
            get_string('learningoutcomesdeleteconfirm', 'grades', format_string($outcome->fullname)),
            $confirmurl,
            $cancelurl
        );
        echo $OUTPUT->footer();
        exit;
    }
}

// ── Main page output ────────────────────────────────────────────────────────

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('learningoutcomes', 'grades'));

// Minimum-outcomes nudge.
$minimum = learningoutcomes_check_minimum($courseid);
if ($minimum && !$minimum->passes) {
    if ($minimum->mode === 'hard') {
        $nudgemsg = get_string('learningoutcomesnudgehard', 'grades', $minimum);
        echo $OUTPUT->notification($nudgemsg, \core\output\notification::NOTIFY_ERROR);
    } else {
        $nudgemsg = get_string('learningoutcomesnudgebelow', 'grades', $minimum);
        echo $OUTPUT->notification($nudgemsg, \core\output\notification::NOTIFY_WARNING);
    }
}

$outcomes = learningoutcomes_get_course_outcomes($courseid);
$tags     = learningoutcomes_get_course_tags($courseid);
$availableglobaloutcomes = learningoutcomes_get_available_global_outcomes($courseid);

if (empty($outcomes)) {
    echo $OUTPUT->notification(get_string('learningoutcomesnone', 'grades'), \core\output\notification::NOTIFY_INFO);
} else {
    // Build summary table.
    $table = new html_table();
    $table->head = [
        get_string('outcomefullname', 'grades'),
        get_string('outcomeshortname', 'grades'),
        get_string('activities'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($outcomes as $outcome) {
        // Count activities tagged to this outcome.
        $taggedcount = 0;
        foreach ($tags as $cmid => $outcomeids) {
            if (in_array((int)$outcome->id, $outcomeids)) {
                $taggedcount++;
            }
        }

        $editurl   = new moodle_url('/grade/learningoutcomes/edit.php',
            ['courseid' => $courseid, 'id' => $outcome->id]);
        $deleteurl = new moodle_url('/grade/learningoutcomes/index.php',
            ['courseid' => $courseid, 'delete' => $outcome->id, 'sesskey' => sesskey()]);
        $tagurl    = new moodle_url('/grade/learningoutcomes/tag_activities.php',
            ['courseid' => $courseid, 'outcomeid' => $outcome->id]);

        $actions = $OUTPUT->action_icon($editurl, new pix_icon('t/edit', get_string('edit'))) . ' ';
        // Only allow deleting course-local outcomes from this page.
        if ($outcome->courseid == $courseid && $outcome->get_item_uses_count() == 0) {
            $actions .= $OUTPUT->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete')),
                new confirm_action(get_string('learningoutcomesdeleteconfirm', 'grades',
                    format_string($outcome->fullname))));
            $actions .= ' ';
        }
        $actions .= html_writer::link($tagurl, get_string('learningoutcomestagactivities', 'grades'),
            ['class' => 'btn btn-sm btn-outline-secondary ms-1']);

        $table->data[] = [
            format_string($outcome->fullname),
            s($outcome->shortname),
            $taggedcount,
            $actions,
        ];
    }
    echo html_writer::table($table);
}

if (has_capability('moodle/grade:manageoutcomes', $context) && !empty($availableglobaloutcomes)) {
    echo $OUTPUT->heading(get_string('outcomesstandardavailable', 'grades'), 3, 'main mt-4');

    $options = [];
    foreach ($availableglobaloutcomes as $outcome) {
        $options[$outcome->id] = format_string($outcome->fullname);
    }

    $formurl = new moodle_url('/grade/learningoutcomes/index.php', ['courseid' => $courseid]);
    echo html_writer::start_tag('form', [
        'action' => $formurl->out(false),
        'method' => 'post',
        'class' => 'mt-3',
    ]);
    echo html_writer::start_div('row g-3 align-items-end');
    echo html_writer::start_div('col-md-8 col-lg-6');
    echo html_writer::label(get_string('outcomesstandardavailable', 'grades'), 'addoutcomes');
    echo html_writer::select($options, 'addoutcomes[]', [], null, [
        'id' => 'addoutcomes',
        'class' => 'form-select',
        'multiple' => 'multiple',
        'size' => min(10, max(4, count($options))),
    ]);
    echo html_writer::end_div();
    echo html_writer::start_div('col-auto');
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'addstandard', 'value' => 1]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-secondary',
        'value' => get_string('add'),
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
}

// Action buttons.
$addurl = new moodle_url('/grade/learningoutcomes/edit.php', ['courseid' => $courseid]);
$tagallurl = new moodle_url('/grade/learningoutcomes/tag_activities.php', ['courseid' => $courseid]);
$reporturl = new moodle_url('/report/learningoutcomes/index.php', ['id' => $courseid]);

echo html_writer::start_div('mt-3 d-flex gap-2 flex-wrap');
echo $OUTPUT->single_button($addurl, get_string('learningoutcomescreate', 'grades'), 'get');
echo $OUTPUT->single_button($tagallurl, get_string('learningoutcomestagactivities', 'grades'), 'get', ['class' => 'btn-secondary']);
echo $OUTPUT->single_button($reporturl, get_string('learningoutcomesalignmentreport', 'grades'), 'get', ['class' => 'btn-secondary']);
echo html_writer::end_div();

echo $OUTPUT->footer();
