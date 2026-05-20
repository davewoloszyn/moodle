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
 * Activity tagging page.
 *
 * Teachers can tag all course activities to learning outcomes (or tag a
 * single activity when outcomeid is supplied).
 *
 * @package   core_grades
 * @copyright 2026 Moodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/grade/grade_outcome.php');
require_once(__DIR__ . '/lib.php');

$courseid  = required_param('courseid', PARAM_INT);
$outcomeid = optional_param('outcomeid', 0, PARAM_INT);

$course  = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('moodle/grade:manage', $context);

if (empty($CFG->enableoutcomes)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('learningoutcomescoursedisabled', 'grades'));
}

$PAGE->set_url('/grade/edit/outcome/tag_activities.php', ['courseid' => $courseid, 'outcomeid' => $outcomeid]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('learningoutcomestagactivitiesheading', 'grades'));
$PAGE->set_heading(format_string($course->fullname));

$outcomes = learningoutcomes_get_course_outcomes($courseid);
if ($outcomeid && isset($outcomes[$outcomeid])) {
    $outcomes = [$outcomeid => $outcomes[$outcomeid]];
}

$modinfo = get_fast_modinfo($course);
$allcms  = [];
foreach ($modinfo->get_cms() as $cm) {
    if (!$cm->deletioninprogress) {
        $allcms[$cm->id] = $cm;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $savecount = 0;
    foreach ($outcomes as $oid => $outcome) {
        foreach ($allcms as $cmid => $cm) {
            $key = "outcome_{$oid}_{$cmid}";
            $value = !empty($_POST[$key]);
            $exists = $DB->record_exists('grade_outcomes_activity', ['outcomeid' => $oid, 'cmid' => $cmid]);
            if ($value && !$exists) {
                learningoutcomes_tag_activity((int)$oid, $courseid, (int)$cmid);
                $savecount++;
            } else if (!$value && $exists) {
                learningoutcomes_untag_activity((int)$oid, (int)$cmid);
            }
        }
    }

    redirect(new moodle_url('/grade/edit/outcome/tag_activities.php',
        ['courseid' => $courseid, 'outcomeid' => $outcomeid]),
        get_string('changessaved') . " ({$savecount} tag(s) saved)", null, \core\output\notification::NOTIFY_SUCCESS);
}

$currenttags = learningoutcomes_get_course_tags($courseid);
$tagset = [];
foreach ($currenttags as $cmid => $oids) {
    foreach ($oids as $oid) {
        $tagset["{$oid}_{$cmid}"] = true;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('learningoutcomestagactivitiesheading', 'grades'));

if (empty($outcomes)) {
    echo $OUTPUT->notification(get_string('learningoutcomesnone', 'grades'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->single_button(
        new moodle_url('/grade/edit/outcome/course.php', ['id' => $courseid]),
        get_string('back'),
        'get'
    );
    echo $OUTPUT->footer();
    exit;
}

if (empty($allcms)) {
    echo $OUTPUT->notification(get_string('noactivities', 'moodle'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$formurl = new moodle_url('/grade/edit/outcome/tag_activities.php',
    ['courseid' => $courseid, 'outcomeid' => $outcomeid]);

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $formurl->out(false),
    'class'  => 'learning-outcomes-tag-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

foreach ($outcomes as $oid => $outcome) {
    echo $OUTPUT->heading(format_string($outcome->fullname), 4, 'mt-4');
    if (!empty($outcome->description)) {
        echo html_writer::div(format_text($outcome->description, $outcome->descriptionformat), 'text-muted small mb-2');
    }

    $table = new html_table();
    $table->head = [get_string('activity'), get_string('section'), get_string('learningoutcomestagactivity', 'grades')];
    $table->attributes['class'] = 'generaltable table';

    foreach ($allcms as $cmid => $cm) {
        $checked = isset($tagset["{$oid}_{$cmid}"]);
        $sectionname = $modinfo->get_section_info($cm->sectionnum)->name ?? get_section_name($course, $cm->sectionnum);

        $checkbox = html_writer::empty_tag('input', [
            'type'  => 'checkbox',
            'name'  => "outcome_{$oid}_{$cmid}",
            'id'    => "outcome_{$oid}_{$cmid}",
            'value' => '1',
        ] + ($checked ? ['checked' => 'checked'] : []));

        $table->data[] = [
            $cm->get_formatted_name(),
            $sectionname,
            $checkbox,
        ];
    }

    echo html_writer::table($table);
}

echo html_writer::start_div('mt-3 d-flex gap-2');
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('savechanges'),
    'class' => 'btn btn-primary',
]);
$cancelurl = new moodle_url('/grade/edit/outcome/course.php', ['id' => $courseid]);
echo html_writer::link($cancelurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo $OUTPUT->footer();
