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
 * @package    report_learningoutcomes
 * @copyright  2026 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\report_helper;

require('../../config.php');
require_once($CFG->dirroot . '/grade/learningoutcomes/lib.php');
require_once($CFG->libdir  . '/grade/grade_outcome.php');

$courseid = required_param('id', PARAM_INT);

$course  = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('report/learningoutcomes:view', $context);

if (empty($CFG->enableoutcomes)) {
    redirect(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('learningoutcomescoursedisabled', 'grades')
    );
}

$PAGE->set_url('/report/learningoutcomes/index.php', ['id' => $courseid]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('alignmentheading', 'report_learningoutcomes'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();

// Standard course-report selector.
report_helper::print_report_selector(get_string('alignmentheading', 'report_learningoutcomes'));

echo $OUTPUT->heading(get_string('alignmentheading', 'report_learningoutcomes'));

$data = learningoutcomes_get_alignment_data($courseid);

// ── No outcomes yet ──────────────────────────────────────────────────────────
if (empty($data->outcomes)) {
    echo $OUTPUT->notification(
        get_string('nooutcomesdefined', 'report_learningoutcomes'),
        \core\output\notification::NOTIFY_INFO
    );
    $manageurl = new moodle_url('/grade/learningoutcomes/index.php', ['courseid' => $courseid]);
    echo $OUTPUT->single_button($manageurl, get_string('manageoutcomes', 'report_learningoutcomes'), 'get');
    echo $OUTPUT->footer();
    exit;
}

// ── Summary stats ────────────────────────────────────────────────────────────
$totaloutcomes   = count($data->outcomes);
$coveredoutcomes = $totaloutcomes - count($data->uncovered_outcomeids);
$totalactivities = count($data->activities);
$taggedactivities = $totalactivities - count($data->untagged_cmids);

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('alignmentsummary', 'report_learningoutcomes'), ['class' => 'card-title']);

$outcomepct  = $totaloutcomes   > 0 ? round(100 * $coveredoutcomes  / $totaloutcomes)  : 0;
$activitypct = $totalactivities > 0 ? round(100 * $taggedactivities / $totalactivities) : 0;

echo html_writer::tag('p', get_string('outcomescount', 'report_learningoutcomes', (object)[
    'covered' => $coveredoutcomes,
    'total'   => $totaloutcomes,
]));
echo html_writer::tag('p', get_string('activitiescount', 'report_learningoutcomes', (object)[
    'tagged' => $taggedactivities,
    'total'  => $totalactivities,
]));

// Progress bars.
echo html_writer::start_div('mb-2');
echo html_writer::tag('small', get_string('outcomecoverage', 'report_learningoutcomes') . " ({$outcomepct}%)");
echo html_writer::start_div('progress', ['style' => 'height:12px']);
$colour = $outcomepct >= 80 ? 'bg-success' : ($outcomepct >= 50 ? 'bg-warning' : 'bg-danger');
echo html_writer::div('', "progress-bar {$colour}", [
    'role'          => 'progressbar',
    'style'         => "width:{$outcomepct}%",
    'aria-valuenow' => $outcomepct,
    'aria-valuemin' => '0',
    'aria-valuemax' => '100',
]);
echo html_writer::end_div(); // .progress
echo html_writer::end_div(); // .mb-2

echo html_writer::start_div('mb-2');
echo html_writer::tag('small', get_string('activitycoverage', 'report_learningoutcomes') . " ({$activitypct}%)");
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

echo html_writer::end_div(); // .card-body
echo html_writer::end_div(); // .card

// ── Gap 1: Outcomes with no supporting activities ────────────────────────────
$ngapoutcomes = count($data->uncovered_outcomeids);
echo html_writer::tag('h5', get_string('gapoutcomes', 'report_learningoutcomes', $ngapoutcomes), [
    'class' => $ngapoutcomes > 0 ? 'text-danger' : 'text-success',
]);

if ($ngapoutcomes === 0) {
    echo html_writer::tag('p', '✓ ' . get_string('wellcovered', 'report_learningoutcomes'));
} else {
    $table = new html_table();
    $table->head = [
        get_string('outcomefullname', 'grades'),
        get_string('outcomeshortname', 'grades'),
        get_string('learningoutcomestagactivities', 'grades'),
    ];
    $table->attributes['class'] = 'generaltable table-sm';

    foreach ($data->uncovered_outcomeids as $oid) {
        $outcome = $data->outcomes[$oid];
        $tagurl  = new moodle_url('/grade/learningoutcomes/tag_activities.php',
            ['courseid' => $courseid, 'outcomeid' => $oid]);
        $table->data[] = [
            html_writer::tag('span', format_string($outcome->fullname), ['class' => 'text-danger fw-bold']),
            s($outcome->shortname),
            html_writer::link($tagurl, get_string('learningoutcomestagactivities', 'grades'),
                ['class' => 'btn btn-sm btn-warning']),
        ];
    }
    echo html_writer::table($table);
}

// ── Gap 2: Activities not tagged to any outcome ──────────────────────────────
$ngapactivities = count($data->untagged_cmids);
echo html_writer::tag('h5', get_string('gapactivities', 'report_learningoutcomes', $ngapactivities), [
    'class' => 'mt-4 ' . ($ngapactivities > 0 ? 'text-warning' : 'text-success'),
]);

if ($ngapactivities === 0) {
    echo html_writer::tag('p', '✓ ' . get_string('wellcovered', 'report_learningoutcomes'));
} else {
    $table = new html_table();
    $table->head = [
        get_string('activity'),
        get_string('section'),
        get_string('learningoutcomestagactivity', 'grades'),
    ];
    $table->attributes['class'] = 'generaltable table-sm';

    $modinfo = get_fast_modinfo($course);
    foreach ($data->untagged_cmids as $cmid) {
        if (!isset($data->activities[$cmid])) {
            continue;
        }
        $cm  = $data->activities[$cmid];
        $sectionname = $modinfo->get_section_info($cm->sectionnum)->name
            ?? get_section_name($course, $cm->sectionnum);
        $tagurl = new moodle_url('/grade/learningoutcomes/tag_activities.php', ['courseid' => $courseid]);
        $table->data[] = [
            html_writer::tag('span', $cm->get_formatted_name(), ['class' => 'text-warning fw-bold']),
            $sectionname,
            html_writer::link($tagurl, get_string('learningoutcomestagactivities', 'grades'),
                ['class' => 'btn btn-sm btn-outline-warning']),
        ];
    }
    echo html_writer::table($table);
}

// ── Full alignment matrix ────────────────────────────────────────────────────
if (!empty($data->outcomes) && !empty($data->activities)) {
    echo html_writer::tag('h5', get_string('outcomecoverage', 'report_learningoutcomes'), ['class' => 'mt-4']);

    $table = new html_table();
    // Header row: outcome names.
    $header = [get_string('activity')];
    foreach ($data->outcomes as $oid => $outcome) {
        $header[] = html_writer::tag('span', format_string($outcome->shortname),
            ['title' => format_string($outcome->fullname), 'class' => 'text-truncate d-inline-block', 'style' => 'max-width:8rem']);
    }
    $table->head = $header;
    $table->attributes['class'] = 'generaltable table-sm table-bordered';

    foreach ($data->activities as $cmid => $cm) {
        $row = [html_writer::tag('small', $cm->get_formatted_name())];
        foreach ($data->outcomes as $oid => $outcome) {
            $tagged = isset($data->tags[$cmid]) && in_array((int)$oid, $data->tags[$cmid]);
            $row[]  = $tagged ? '✓' : '';
        }
        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

// ── Action buttons ────────────────────────────────────────────────────────────
$manageurl = new moodle_url('/grade/learningoutcomes/index.php', ['courseid' => $courseid]);
$tagurl    = new moodle_url('/grade/learningoutcomes/tag_activities.php', ['courseid' => $courseid]);
echo html_writer::start_div('mt-3 d-flex gap-2');
echo $OUTPUT->single_button($manageurl, get_string('manageoutcomes', 'report_learningoutcomes'), 'get', ['class' => 'btn-secondary']);
echo $OUTPUT->single_button($tagurl, get_string('tagactivities', 'report_learningoutcomes'), 'get');
echo html_writer::end_div();

echo $OUTPUT->footer();
