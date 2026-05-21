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
 * A page for selecting outcomes for use in a course
 *
 * @package   core_grades
 * @copyright 2007 Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once '../../../config.php';
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->libdir.'/gradelib.php';
require_once $CFG->libdir . '/grade/constants.php';
require_once $CFG->libdir . '/grade/grade_outcome.php';
require_once $CFG->dirroot . '/grade/edit/outcome/lib.php';

$courseid = required_param('id', PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$addstandard = optional_param('addstandard', 0, PARAM_BOOL);
$addoutcomes = optional_param_array('addoutcomes', [], PARAM_INT);

$PAGE->set_url('/grade/edit/outcome/course.php', array('id'=>$courseid));

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

/// Make sure they can even access this course
require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/grade:manage', $context);

if (empty($CFG->enableoutcomes)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('learningoutcomescoursedisabled', 'grades'));
}

/// return tracking object
$gpr = new grade_plugin_return(array('type'=>'edit', 'plugin'=>'outcomes', 'courseid'=>$courseid));

if ($addstandard && confirm_sesskey()) {
    require_capability('moodle/grade:manageoutcomes', $context);

    $addedcount = 0;
    foreach ($addoutcomes as $outcomeid) {
        if (learningoutcomes_add_global_outcome_to_course($courseid, (int)$outcomeid)) {
            $addedcount++;
        }
    }

    redirect(
        new moodle_url('/grade/edit/outcome/course.php', ['id' => $courseid]),
        $addedcount ? get_string('changessaved') : get_string('nothingtodisplay'),
        null,
        $addedcount ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_INFO
    );
}

if ($delete && $confirm && confirm_sesskey()) {
    $outcome = grade_outcome::fetch(['id' => $delete]);
    if ($outcome && ((int)$outcome->courseid === (int)$courseid || empty($outcome->courseid))) {
        $incourseitemuses = $DB->count_records_select('grade_items', 'courseid = ? AND outcomeid = ?',
            [$courseid, $outcome->id]);
        if ($incourseitemuses > 0) {
            redirect(
                new moodle_url('/grade/edit/outcome/course.php', ['id' => $courseid]),
                get_string('learningoutcomesdeleteinuse', 'grades', format_string($outcome->fullname)),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        if ((int)$outcome->courseid === (int)$courseid) {
            learningoutcomes_delete_outcome_tags((int)$outcome->id);
            $outcome->delete('grade/learningoutcomes');
        } else {
            // Global outcome: detach only from this course and remove this course's tags.
            $DB->delete_records('grade_outcomes_activity', ['outcomeid' => $outcome->id, 'courseid' => $courseid]);
            $DB->delete_records('grade_outcomes_courses', ['outcomeid' => $outcome->id, 'courseid' => $courseid]);
        }

        redirect(
            new moodle_url('/grade/edit/outcome/course.php', ['id' => $courseid]),
            get_string('deleted'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$actionbar = new \core_grades\output\course_outcomes_action_bar($context, $PAGE->url);
// Print header.
print_grade_page_head($COURSE->id, 'outcome', 'course', false, false, false,
    true, null, null, null, $actionbar);

if ($delete && !$confirm) {
    $outcome = grade_outcome::fetch(['id' => $delete]);
    if ($outcome) {
        $confirmurl = new moodle_url('/grade/edit/outcome/course.php', [
            'id' => $courseid,
            'delete' => $delete,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);
        $cancelurl = new moodle_url('/grade/edit/outcome/course.php', ['id' => $courseid]);
        $confirmmessage = ((int)$outcome->courseid === (int)$courseid)
            ? get_string('learningoutcomesdeleteconfirm', 'grades', format_string($outcome->fullname))
            : get_string('learningoutcomesremovefromcourseconfirm', 'grades', format_string($outcome->fullname));
        echo $OUTPUT->confirm(
            $confirmmessage,
            $confirmurl,
            $cancelurl
        );
        echo $OUTPUT->footer();
        exit;
    }
}

$minimum = learningoutcomes_check_minimum($courseid);
if ($minimum && !$minimum->passes) {
    if ($minimum->mode === 'hard') {
        echo $OUTPUT->notification(get_string('learningoutcomesnudgehard', 'grades', $minimum),
            \core\output\notification::NOTIFY_ERROR);
    } else {
        echo $OUTPUT->notification(get_string('learningoutcomesnudgebelow', 'grades', $minimum),
            \core\output\notification::NOTIFY_WARNING);
    }
}

$outcomes = learningoutcomes_get_course_outcomes($courseid);
$tags = learningoutcomes_get_course_tags($courseid);
$gradestats = learningoutcomes_get_course_grade_stats($courseid);
$availableglobaloutcomes = learningoutcomes_get_available_global_outcomes($courseid);

if (empty($outcomes)) {
    echo $OUTPUT->notification(get_string('learningoutcomesnone', 'grades'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [
        get_string('outcomefullname', 'grades'),
        get_string('outcomeshortname', 'grades'),
        get_string('learningoutcomesoutcometype', 'grades'),
        get_string('average', 'grades'),
        get_string('numberofgrades', 'grades'),
        get_string('courseavg', 'grades'),
        get_string('activities'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable table';

    foreach ($outcomes as $outcome) {
        $taggedcount = 0;
        foreach ($tags as $outcomeids) {
            if (in_array((int)$outcome->id, $outcomeids, true)) {
                $taggedcount++;
            }
        }
        $incourseitemuses = $DB->count_records_select('grade_items', 'courseid = ? AND outcomeid = ?',
            [$courseid, $outcome->id]);

        $editurl = new moodle_url('/grade/edit/outcome/edit.php', ['courseid' => $courseid, 'id' => $outcome->id]);
        $deleteurl = new moodle_url('/grade/edit/outcome/course.php', [
            'id' => $courseid,
            'delete' => $outcome->id,
            'sesskey' => sesskey(),
        ]);
        $tagurl = new moodle_url('/grade/edit/outcome/tag_activities.php', ['courseid' => $courseid, 'outcomeid' => $outcome->id]);

        $actions = $OUTPUT->action_icon($editurl, new pix_icon('t/edit', get_string('edit'))) . ' ';
        if ((int)$outcome->courseid === (int)$courseid && $incourseitemuses === 0) {
            $actions .= $OUTPUT->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete')),
                new confirm_action(get_string('learningoutcomesdeleteconfirm', 'grades', format_string($outcome->fullname))));
            $actions .= ' ';
        } else if (empty($outcome->courseid) && $incourseitemuses === 0) {
            $actions .= $OUTPUT->action_icon($deleteurl,
                new pix_icon('t/delete', get_string('learningoutcomesremovefromcourse', 'grades')),
                new confirm_action(get_string('learningoutcomesremovefromcourseconfirm', 'grades',
                    format_string($outcome->fullname))));
            $actions .= ' ';
        }
        $actions .= $OUTPUT->action_icon($tagurl,
            new pix_icon('t/tags', get_string('learningoutcomestagactivities', 'grades')));

        $outcometype = empty($outcome->courseid)
            ? get_string('learningoutcomesoutcometypestandard', 'grades')
            : get_string('learningoutcomesoutcometypecourse', 'grades');
        $stat = $gradestats[$outcome->id] ?? (object) [
            'averagedisplay' => '-',
            'gradecount' => 0,
            'courseaveragedisplay' => '-',
        ];

        $table->data[] = [
            format_string($outcome->fullname),
            s($outcome->shortname),
            $outcometype,
            $stat->averagedisplay,
            $stat->gradecount,
            $stat->courseaveragedisplay,
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

    $formurl = new moodle_url('/grade/edit/outcome/course.php', ['id' => $courseid]);
    echo html_writer::start_tag('form', [
        'action' => $formurl->out(false),
        'method' => 'post',
        'class' => 'mt-3',
    ]);
    echo html_writer::start_div('row mb-3');
    echo html_writer::tag('label', get_string('outcomesstandard', 'grades'), [
        'for' => 'addoutcomes',
        'class' => 'col-md-3 col-form-label pt-0',
    ]);
    echo html_writer::start_div('col-md-9');
    echo html_writer::start_div('d-flex flex-column gap-2', ['id' => 'addoutcomes']);
    foreach ($options as $outcomeid => $outcomename) {
        $checkboxid = 'addoutcome_' . $outcomeid;
        echo html_writer::start_div('form-check');
        echo html_writer::checkbox('addoutcomes[]', $outcomeid, false, $outcomename, [
            'class' => 'form-check-input',
            'id' => $checkboxid,
        ]);
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::end_div();
    echo html_writer::start_div('row');
    echo html_writer::start_div('offset-md-3 col-md-9');
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'addstandard', 'value' => 1]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => get_string('learningoutcomesaddtolearningoutcomes', 'grades'),
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();

