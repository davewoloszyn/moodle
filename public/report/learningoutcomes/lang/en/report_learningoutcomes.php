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
 * Lang strings for report_learningoutcomes.
 *
 * @package    report_learningoutcomes
 * @copyright  2026 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname']                = 'Learning outcomes alignment';
$string['learningoutcomes:view']     = 'View learning outcomes alignment report';
$string['alignmentheading']          = 'Course alignment report';
$string['outcomecoverage']           = 'Outcome coverage';
$string['activitycoverage']          = 'Activity coverage';
$string['outcomeswithnoactivities']  = 'Outcomes with no supporting activities';
$string['activitieswithnooutcomes']  = 'Activities with no outcome tag';
$string['wellcovered']               = 'Well-covered';
$string['alignmentsummary']          = 'Alignment summary';
$string['nooutcomesdefined']         = 'No learning outcomes have been defined for this course yet.';
$string['noactivities']              = 'No activities have been added to this course yet.';
$string['outcomescount']             = '{$a->covered} of {$a->total} outcomes have at least one activity tagged.';
$string['activitiescount']           = '{$a->tagged} of {$a->total} activities are tagged to at least one outcome.';
$string['gapoutcomes']               = 'Outcome alignment gaps ({$a})';
$string['gapactivities']             = 'Untagged activities ({$a})';
$string['manageoutcomes']            = 'Manage learning outcomes';
$string['tagactivities']             = 'Tag activities';
$string['privacy:metadata']          = 'The learning outcomes alignment report does not store any personal data.';
