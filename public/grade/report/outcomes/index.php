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
 * Learning Outcomes Alignment Report.
 *
 * Shows two-way coverage gaps:
 *   - Outcomes with no supporting activities (shown in red)
 *   - Activities not tagged to any outcome  (shown in amber)
 *   - Summary stats for overall alignment coverage
 *
 * @package   gradereport_outcomes
 * @copyright 2007 Nicolas Connault
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include_once('../../../config.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/edit/outcome/lib.php');
require_once($CFG->libdir  . '/grade/grade_outcome.php');

$courseid = required_param('id', PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);

$PAGE->set_url('/grade/report/outcomes/index.php', ['id' => $courseid]);

if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    throw new \moodle_exception('invalidcourseid');
}



require_capability('gradereport/outcomes:view', $context);

if (empty($CFG->enableoutcomes)) {
    redirect(course_get_url($course), get_string('outcomesdisabled', 'core_grades'));
}

$actionbar = new \core_grades\output\course_outcomes_action_bar($context, $PAGE->url, false);
print_grade_page_head($course->id, 'report', 'outcomes',
    get_string('alignmentheading', 'report_learningoutcomes'),
    false, false, true, null, null, null, $actionbar);

$data = learningoutcomes_get_alignment_data($courseid);

// ── No outcomes yet ──────────────────────────────────────────────────────────
if (empty($data->outcomes)) {
    echo $OUTPUT->notification(
        get_string('nooutcomesdefined', 'report_learningoutcomes'),
        \core\output\notification::NOTIFY_INFO
    );
    $manageurl = new moodle_url('/grade/edit/outcome/course.php', ['id' => $courseid]);
    echo $OUTPUT->single_button($manageurl, get_string('manageoutcomes', 'report_learningoutcomes'), 'get');
    $event = \gradereport_outcomes\event\grade_report_viewed::create([
        'context' => $context,
        'courseid' => $courseid,
    ]);
    $event->trigger();
    echo $OUTPUT->footer();
    exit;
}

// ── Summary stats ────────────────────────────────────────────────────────────
$totaloutcomes    = count($data->outcomes);
$coveredoutcomes  = $totaloutcomes - count($data->uncovered_outcomeids);
$totalactivities  = count($data->activities);
$taggedactivities = $totalactivities - count($data->untagged_cmids);

echo html_writer::start_div('card bg-light my-5 p-2');
echo html_writer::start_div('card-body');
echo html_writer::tag('h3', get_string('alignmentsummary', 'report_learningoutcomes'), ['class' => 'card-title h4']);

$outcomepct  = $totaloutcomes   > 0 ? round(100 * $coveredoutcomes  / $totaloutcomes)  : 0;
$activitypct = $totalactivities > 0 ? round(100 * $taggedactivities / $totalactivities) : 0;

echo html_writer::start_div('my-3');
echo html_writer::tag('p', get_string('outcomescount', 'report_learningoutcomes', (object)[
    'covered' => $coveredoutcomes,
    'total'   => $totaloutcomes,
]), ['class' => 'mb-0']);
echo html_writer::tag('small', get_string('outcomecoverage', 'report_learningoutcomes') . " ({$outcomepct}%)", ['class' => 'text-muted']);
echo html_writer::start_div('progress', ['style' => 'height:12px']);
$colour = $outcomepct >= 80 ? 'bg-success' : ($outcomepct >= 50 ? 'bg-warning' : 'bg-danger');
echo html_writer::div('', "progress-bar {$colour}", [
    'role'          => 'progressbar',
    'style'         => "width:{$outcomepct}%",
    'aria-valuenow' => $outcomepct,
    'aria-valuemin' => '0',
    'aria-valuemax' => '100',
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('my-3');
echo html_writer::tag('p', get_string('activitiescount', 'report_learningoutcomes', (object)[
    'tagged' => $taggedactivities,
    'total'  => $totalactivities,
]), ['class' => 'mb-0']);
echo html_writer::tag('small', get_string('activitycoverage', 'report_learningoutcomes') . " ({$activitypct}%)", ['class' => 'text-muted']);
echo html_writer::start_div('progress', ['style' => 'height:12px']);
$colour2 = $activitypct >= 60 ? 'bg-success' : ($activitypct >= 30 ? 'bg-warning' : 'bg-danger');
echo html_writer::div('', "progress-bar {$colour2}", [
    'role'          => 'progressbar',
    'style'         => "width:{$activitypct}%",
    'aria-valuenow' => $activitypct,
    'aria-valuemin' => '0',
    'aria-valuemax' => '100',
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();

// ── Gap 1: Outcomes with no supporting activities ────────────────────────────
$ngapoutcomes = count($data->uncovered_outcomeids);
echo html_writer::tag('h3', get_string('gapoutcomes', 'report_learningoutcomes', $ngapoutcomes), ['class' => 'mt-5']);

if ($ngapoutcomes === 0) {
    $checkicon = html_writer::tag('i', '', ['class' => 'fa fa-check-circle text-success me-1', 'aria-hidden' => 'true']);
    echo html_writer::tag('p', $checkicon . get_string('wellcovered', 'report_learningoutcomes'));
} else {
    $table = new html_table();
    $table->head = [
        get_string('outcomefullname', 'grades'),
        get_string('outcomeshortname', 'grades'),
        get_string('learningoutcomestagactivities', 'grades'),
    ];
    $table->attributes['class'] = 'generaltable table';
    foreach ($data->uncovered_outcomeids as $oid) {
        $outcome    = $data->outcomes[$oid];
        $tagurl     = new moodle_url('/grade/edit/outcome/tag_activities.php',
            ['courseid' => $courseid, 'outcomeid' => $oid]);
        $outcomeicon = html_writer::span(
            $OUTPUT->pix_icon('i/warning', get_string('warning')),
            'text-danger me-1'
        );
        $table->data[] = [
            $outcomeicon . html_writer::tag('span', format_string($outcome->fullname), ['class' => 'fw-bold']),
            s($outcome->shortname),
            html_writer::link($tagurl, get_string('learningoutcomestagactivities', 'grades'),
                ['class' => 'btn btn-sm btn-secondary']),
        ];
    }
    echo html_writer::table($table);
}

// ── Gap 2: Activities not tagged to any outcome ──────────────────────────────
$ngapactivities = count($data->untagged_cmids);
echo html_writer::tag('h3', get_string('gapactivities', 'report_learningoutcomes', $ngapactivities), ['class' => 'mt-5']);

if ($ngapactivities === 0) {
    $checkicon = html_writer::tag('i', '', ['class' => 'fa fa-check-circle text-success me-1', 'aria-hidden' => 'true']);
    echo html_writer::tag('p', $checkicon . get_string('wellcovered', 'report_learningoutcomes'));
} else {
    $table = new html_table();
    $table->head = [
        get_string('activity'),
        get_string('section'),
        get_string('learningoutcomestagactivity', 'grades'),
    ];
    $table->attributes['class'] = 'generaltable table';
    $modinfo = get_fast_modinfo($course);
    foreach ($data->untagged_cmids as $cmid) {
        if (!isset($data->activities[$cmid])) {
            continue;
        }
        $cm          = $data->activities[$cmid];
        $sectionname = $modinfo->get_section_info($cm->sectionnum)->name
            ?? get_section_name($course, $cm->sectionnum);
        $tagurl = new moodle_url('/grade/edit/outcome/tag_activities.php', ['courseid' => $courseid]);
        $activityicon = html_writer::span(
            $OUTPUT->pix_icon('i/warning', get_string('warning')),
            'text-warning me-1'
        );
        $table->data[] = [
            $activityicon . html_writer::tag('span', $cm->get_formatted_name(), ['class' => 'fw-bold']),
            $sectionname,
            html_writer::link($tagurl, get_string('learningoutcomestagactivities', 'grades'),
                ['class' => 'btn btn-sm btn-secondary']),
        ];
    }
    echo html_writer::table($table);
}

// ── Full alignment matrix ────────────────────────────────────────────────────
if (!empty($data->outcomes) && !empty($data->activities)) {
    echo html_writer::tag('h3', get_string('outcomecoverage', 'report_learningoutcomes'), ['class' => 'mt-5']);
    $table = new html_table();
    $header = [get_string('activity')];
    foreach ($data->outcomes as $oid => $outcome) {
        $header[] = html_writer::tag('span', format_string($outcome->shortname),
            ['title' => format_string($outcome->fullname), 'class' => 'text-truncate d-inline-block', 'style' => 'max-width:8rem']);
    }
    $table->head = $header;
    $table->attributes['class'] = 'generaltable table';
    foreach ($data->activities as $cmid => $cm) {
        $row = [html_writer::tag('small', $cm->get_formatted_name())];
        foreach ($data->outcomes as $oid => $outcome) {
            $tagged = isset($data->tags[$cmid]) && in_array((int)$oid, $data->tags[$cmid]);
            $row[]  = $tagged
                ? html_writer::tag('i', '', ['class' => 'fa fa-check-circle text-success', 'aria-hidden' => 'true'])
                : '';
        }
        $table->data[] = $row;
    }
    echo html_writer::table($table);
}

// ── Action buttons ─────────────────────────────────────────────────────────────
$manageurl = new moodle_url('/grade/edit/outcome/course.php', ['id' => $courseid]);
$tagurl    = new moodle_url('/grade/edit/outcome/tag_activities.php', ['courseid' => $courseid]);
echo html_writer::start_div('mt-3 d-flex gap-2');
$managebtn = new single_button($manageurl, get_string('manageoutcomes', 'report_learningoutcomes'), 'get',
    single_button::BUTTON_PRIMARY);
echo $OUTPUT->render($managebtn);
$tagbtn = new single_button($tagurl, get_string('tagactivities', 'report_learningoutcomes'), 'get',
    single_button::BUTTON_SECONDARY);
echo $OUTPUT->render($tagbtn);
echo html_writer::end_div();

$event = \gradereport_outcomes\event\grade_report_viewed::create([
    'context'  => $context,
    'courseid' => $courseid,
]);
$event->trigger();

echo $OUTPUT->footer();
