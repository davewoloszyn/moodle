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
 * Shared functions for the Learning Outcomes management interface.
 *
 * @package   core_grades
 * @copyright 2026 Moodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Determine whether Learning Outcomes are enabled for a given course.
 *
 * Respects the three-tier hierarchy:
 *   1. Site master switch (enableoutcomes config)
 *   2. Course-level override (course.enablelearningoutcomes: 1=on, 0=off, null/-1=inherit)
 *   3. Site default for new courses (learningoutcomes_defaultoncreation)
 *
 * @param stdClass $course The course record (must include enablelearningoutcomes).
 * @return bool
 */
function learningoutcomes_enabled_for_course(stdClass $course): bool {
    global $CFG;

    // Site master switch must be on.
    if (empty($CFG->enableoutcomes)) {
        return false;
    }

    // Course-level override.
    if (isset($course->enablelearningoutcomes) && (int)$course->enablelearningoutcomes === 1) {
        return true;
    }
    if (isset($course->enablelearningoutcomes) && (int)$course->enablelearningoutcomes === 0) {
        return false;
    }

    // Null / -1 = inherit from site default.
    return !empty($CFG->learningoutcomes_defaultoncreation);
}

/**
 * Return all learning outcomes available for a course (local + global).
 *
 * @param int $courseid
 * @return grade_outcome[] Keyed by outcome id.
 */
function learningoutcomes_get_course_outcomes(int $courseid): array {
    global $CFG;

    require_once($CFG->libdir . '/grade/grade_outcome.php');

    $outcomes = [];

    // Course-local outcomes.
    if ($local = grade_outcome::fetch_all_local($courseid)) {
        foreach ($local as $o) {
            $outcomes[$o->id] = $o;
        }
    }

    // Global outcomes made available to this course.
    if ($available = grade_outcome::fetch_all_available($courseid)) {
        foreach ($available as $o) {
            if (!isset($outcomes[$o->id])) {
                $outcomes[$o->id] = $o;
            }
        }
    }

    return $outcomes;
}

/**
 * Return global outcomes that are not yet available in a course.
 *
 * @param int $courseid
 * @return grade_outcome[] keyed by outcome id.
 */
function learningoutcomes_get_available_global_outcomes(int $courseid): array {
    global $CFG, $DB;

    require_once($CFG->libdir . '/grade/grade_outcome.php');

    $available = [];
    $linkedids = $DB->get_fieldset_select('grade_outcomes_courses', 'outcomeid', 'courseid = ?', [$courseid]);
    $linkedids = array_map('intval', $linkedids);

    if ($globaloutcomes = grade_outcome::fetch_all_global()) {
        foreach ($globaloutcomes as $outcome) {
            if (!in_array((int)$outcome->id, $linkedids, true)) {
                $available[$outcome->id] = $outcome;
            }
        }
    }

    return $available;
}

/**
 * Add a global outcome to the course pool, if eligible.
 *
 * @param int $courseid
 * @param int $outcomeid
 * @return bool true if a row was inserted.
 */
function learningoutcomes_add_global_outcome_to_course(int $courseid, int $outcomeid): bool {
    global $CFG, $DB;

    require_once($CFG->libdir . '/grade/grade_outcome.php');

    $outcome = grade_outcome::fetch(['id' => $outcomeid]);
    if (!$outcome || !empty($outcome->courseid)) {
        return false;
    }

    if ($DB->record_exists('grade_outcomes_courses', ['courseid' => $courseid, 'outcomeid' => $outcomeid])) {
        return false;
    }

    $record = new stdClass();
    $record->courseid = $courseid;
    $record->outcomeid = $outcomeid;
    $DB->insert_record('grade_outcomes_courses', $record);

    return true;
}

/**
 * Return the set of outcome IDs that a given course module has been tagged with.
 *
 * @param int $cmid
 * @return int[]
 */
function learningoutcomes_get_cm_outcome_ids(int $cmid): array {
    global $DB;
    return $DB->get_fieldset_select('grade_outcomes_activity', 'outcomeid', 'cmid = ?', [$cmid]);
}

/**
 * Return a map of cmid => [outcomeid, ...] for a whole course.
 *
 * @param int $courseid
 * @return array cmid => int[]
 */
function learningoutcomes_get_course_tags(int $courseid): array {
    global $DB;

    $rows = $DB->get_records('grade_outcomes_activity', ['courseid' => $courseid], '', 'id, cmid, outcomeid');
    $map = [];
    foreach ($rows as $row) {
        $map[$row->cmid][] = (int)$row->outcomeid;
    }
    return $map;
}

/**
 * Tag a course module with a learning outcome (idempotent).
 *
 * @param int $outcomeid
 * @param int $courseid
 * @param int $cmid
 */
function learningoutcomes_tag_activity(int $outcomeid, int $courseid, int $cmid): void {
    global $DB, $USER;

    if (!$DB->record_exists('grade_outcomes_activity', ['outcomeid' => $outcomeid, 'cmid' => $cmid])) {
        $record = new stdClass();
        $record->outcomeid = $outcomeid;
        $record->courseid = $courseid;
        $record->cmid = $cmid;
        $record->timecreated = time();
        $record->usermodified = $USER->id;
        $DB->insert_record('grade_outcomes_activity', $record);
    }
}

/**
 * Remove a tag between a course module and a learning outcome.
 *
 * @param int $outcomeid
 * @param int $cmid
 */
function learningoutcomes_untag_activity(int $outcomeid, int $cmid): void {
    global $DB;
    $DB->delete_records('grade_outcomes_activity', ['outcomeid' => $outcomeid, 'cmid' => $cmid]);
}

/**
 * Remove all activity tags for an outcome (called on outcome deletion).
 *
 * @param int $outcomeid
 */
function learningoutcomes_delete_outcome_tags(int $outcomeid): void {
    global $DB;
    $DB->delete_records('grade_outcomes_activity', ['outcomeid' => $outcomeid]);
}

/**
 * Count the number of outcomes defined for a course.
 *
 * @param int $courseid
 * @return int
 */
function learningoutcomes_count_course_outcomes(int $courseid): int {
    global $DB;
    // Local outcomes: those with courseid = $courseid.
    $local = $DB->count_records('grade_outcomes', ['courseid' => $courseid]);
    // Global outcomes associated via grade_outcomes_courses.
    $global = $DB->count_records_sql(
        'SELECT COUNT(goc.id)
           FROM {grade_outcomes_courses} goc
           JOIN {grade_outcomes} go ON go.id = goc.outcomeid
          WHERE goc.courseid = ? AND go.courseid IS NULL',
        [$courseid]
    );
    return $local + $global;
}

/**
 * Return grade summary stats for each course outcome.
 *
 * Average is a weighted average across all grades tied to the outcome.
 * Course average matches the legacy report and averages each tagged grade item's average equally.
 *
 * @param int $courseid
 * @return array keyed by outcome id, each value containing:
 *   - averagedisplay: string
 *   - gradecount: int
 *   - courseaveragedisplay: string
 */
function learningoutcomes_get_course_grade_stats(int $courseid): array {
    global $CFG, $DB;

    require_once($CFG->libdir . '/grade/grade_scale.php');

    $stats = [];
    $outcomes = learningoutcomes_get_course_outcomes($courseid);
    foreach ($outcomes as $outcomeid => $outcome) {
        $stats[$outcomeid] = (object) [
            'averagedisplay' => '-',
            'gradecount' => 0,
            'courseaveragedisplay' => '-',
        ];
    }

    if (empty($outcomes)) {
        return $stats;
    }

    $context = context_course::instance($courseid);
    $defaultgradeshowactiveenrol = !empty($CFG->grade_report_showonlyactiveenrol);
    $showonlyactiveenrol = get_user_preferences('grade_report_showonlyactiveenrol', $defaultgradeshowactiveenrol);
    $showonlyactiveenrol = $showonlyactiveenrol || !has_capability('moodle/course:viewsuspendedusers', $context);

    $params = ['courseid' => $courseid];
    $gradesql = '';
    if ($showonlyactiveenrol) {
        $suspendedusers = get_suspended_userids($context);
        if (!empty($suspendedusers)) {
            list($notinusers, $userparams) = $DB->get_in_or_equal($suspendedusers, SQL_PARAMS_NAMED, 'sus', false);
            $gradesql = " AND gg.userid {$notinusers}";
            $params += $userparams;
        }
    }

    $sql = "SELECT gi.id,
                   gi.outcomeid,
                   AVG(gg.finalgrade) AS avggrade,
                   COUNT(gg.finalgrade) AS gradecount
              FROM {grade_items} gi
         LEFT JOIN {grade_grades} gg
                ON gg.itemid = gi.id{$gradesql}
             WHERE gi.courseid = :courseid
               AND gi.outcomeid IS NOT NULL
          GROUP BY gi.id, gi.outcomeid";
    $rows = $DB->get_records_sql($sql, $params);

    $totals = [];
    foreach ($rows as $row) {
        $outcomeid = (int) $row->outcomeid;
        if (!isset($outcomes[$outcomeid])) {
            continue;
        }
        if (!isset($totals[$outcomeid])) {
            $totals[$outcomeid] = (object) [
                'sumitemaverages' => 0.0,
                'itemaveragecount' => 0,
                'weightedgradesum' => 0.0,
                'gradecount' => 0,
            ];
        }

        $gradecount = (int) $row->gradecount;
        if ($gradecount <= 0 || $row->avggrade === null) {
            continue;
        }

        $avggrade = round((float) $row->avggrade, 2);
        $totals[$outcomeid]->sumitemaverages += $avggrade;
        $totals[$outcomeid]->itemaveragecount++;
        $totals[$outcomeid]->weightedgradesum += $avggrade * $gradecount;
        $totals[$outcomeid]->gradecount += $gradecount;
    }

    foreach ($totals as $outcomeid => $total) {
        $stats[$outcomeid]->gradecount = $total->gradecount;

        if (empty($outcomes[$outcomeid]->scaleid)) {
            continue;
        }

        $scale = grade_scale::fetch(['id' => $outcomes[$outcomeid]->scaleid]);
        if (!$scale) {
            continue;
        }

        if ($total->gradecount > 0) {
            $average = $total->weightedgradesum / $total->gradecount;
            $stats[$outcomeid]->averagedisplay = s($scale->get_nearest_item($average)) .
                ' (' . round($average, 2) . ')';
        }

        if ($total->itemaveragecount > 0) {
            $courseaverage = $total->sumitemaverages / $total->itemaveragecount;
            $stats[$outcomeid]->courseaveragedisplay = s($scale->get_nearest_item($courseaverage)) .
                ' (' . round($courseaverage, 2) . ')';
        }
    }

    return $stats;
}

/**
 * Return alignment report data for a course.
 *
 * @param int $courseid
 * @return stdClass with properties:
 *   - outcomes: grade_outcome[] keyed by id
 *   - activities: cm_info[] keyed by cmid (non-decorative only)
 *   - tags: cmid => int[] (outcome ids for each cm)
 *   - outcome_cmids: outcomeid => int[] (cmids for each outcome)
 *   - untagged_cmids: int[]
 *   - uncovered_outcomeids: int[]
 */
function learningoutcomes_get_alignment_data(int $courseid): stdClass {
    global $CFG;
    require_once($CFG->libdir . '/grade/grade_outcome.php');

    $data = new stdClass();
    $data->outcomes = learningoutcomes_get_course_outcomes($courseid);
    $data->tags = learningoutcomes_get_course_tags($courseid);

    // Build outcome -> cmid map.
    $data->outcome_cmids = [];
    foreach ($data->outcomes as $oid => $outcome) {
        $data->outcome_cmids[$oid] = [];
    }
    foreach ($data->tags as $cmid => $outcomeids) {
        foreach ($outcomeids as $oid) {
            if (isset($data->outcome_cmids[$oid])) {
                $data->outcome_cmids[$oid][] = $cmid;
            }
        }
    }

    // Get course modules (exclude labels / decorative items).
    $modinfo = get_fast_modinfo($courseid);
    $data->activities = [];
    foreach ($modinfo->get_cms() as $cm) {
        if (!$cm->deletioninprogress && $cm->visible) {
            $data->activities[$cm->id] = $cm;
        }
    }

    // Activities not tagged to any outcome.
    $data->untagged_cmids = [];
    foreach ($data->activities as $cmid => $cm) {
        if (empty($data->tags[$cmid])) {
            $data->untagged_cmids[] = $cmid;
        }
    }

    // Outcomes with no activities tagged.
    $data->uncovered_outcomeids = [];
    foreach ($data->outcome_cmids as $oid => $cmids) {
        if (empty($cmids)) {
            $data->uncovered_outcomeids[] = $oid;
        }
    }

    return $data;
}

/**
 * Check whether the course meets the minimum outcomes requirement.
 * Returns null if the minimum is not configured or is 0.
 *
 * @param int $courseid
 * @return stdClass|null with: count, min, remaining, passes, mode ('soft'|'hard')
 */
function learningoutcomes_check_minimum(int $courseid): ?stdClass {
    global $CFG;

    $min = (int) ($CFG->learningoutcomes_minoutcomes ?? 0);
    if ($min <= 0) {
        return null;
    }

    $count = learningoutcomes_count_course_outcomes($courseid);
    $result = new stdClass();
    $result->count = $count;
    $result->min = $min;
    $result->remaining = max(0, $min - $count);
    $result->passes = ($count >= $min);
    $result->mode = $CFG->learningoutcomes_enforcement ?? 'soft';
    return $result;
}

/**
 * Render the student-facing learning outcomes panel for a course.
 * Returns an HTML string suitable for insertion above the course sections.
 *
 * @param stdClass $course
 * @return string HTML
 */
function learningoutcomes_render_course_panel(stdClass $course): string {
    global $OUTPUT;

    if (!learningoutcomes_enabled_for_course($course)) {
        return '';
    }

    $outcomes = learningoutcomes_get_course_outcomes((int)$course->id);
    if (empty($outcomes)) {
        return '';
    }

    $items = [];
    foreach ($outcomes as $outcome) {
        $items[] = [
            'fullname' => format_string($outcome->fullname),
            'description' => !empty($outcome->description)
                ? format_text($outcome->description, $outcome->descriptionformat)
                : '',
        ];
    }

    $context = [
        'heading' => get_string('learningoutcomes', 'grades'),
        'outcomes' => $items,
    ];

    return $OUTPUT->render_from_template('core_grades/learning_outcomes_course_panel', $context);
}

/**
 * Render the student-facing learning outcomes tags for an activity (course module).
 *
 * @param int $cmid
 * @param int $courseid
 * @return string HTML
 */
function learningoutcomes_render_activity_panel(int $cmid, int $courseid): string {
    global $OUTPUT, $CFG;

    if (empty($CFG->enableoutcomes)) {
        return '';
    }

    // Load course to check enabled status.
    $course = get_course($courseid);
    if (!learningoutcomes_enabled_for_course($course)) {
        return '';
    }

    $outcomeids = learningoutcomes_get_cm_outcome_ids($cmid);
    if (empty($outcomeids)) {
        return '';
    }

    require_once($CFG->libdir . '/grade/grade_outcome.php');
    $items = [];
    foreach ($outcomeids as $oid) {
        $outcome = grade_outcome::fetch(['id' => $oid]);
        if ($outcome) {
            $items[] = [
                'fullname' => format_string($outcome->fullname),
                'description' => !empty($outcome->description)
                    ? format_text($outcome->description, $outcome->descriptionformat)
                    : '',
            ];
        }
    }

    if (empty($items)) {
        return '';
    }

    $context = [
        'heading' => get_string('learningoutcomes', 'grades'),
        'outcomes' => $items,
    ];

    return $OUTPUT->render_from_template('core_grades/learning_outcomes_activity_panel', $context);
}
