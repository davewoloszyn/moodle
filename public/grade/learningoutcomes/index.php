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
 * Compatibility redirect for legacy learning outcomes index route.
 *
 * @package   core_grades
 * @copyright 2026 Moodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$params = ['id' => $courseid];

$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$addstandard = optional_param('addstandard', 0, PARAM_BOOL);
$addoutcomes = optional_param_array('addoutcomes', [], PARAM_INT);
$sesskey = optional_param('sesskey', '', PARAM_RAW);

if ($delete) {
    $params['delete'] = $delete;
}
if ($confirm) {
    $params['confirm'] = 1;
}
if ($addstandard) {
    $params['addstandard'] = 1;
}
if (!empty($addoutcomes)) {
    $params['addoutcomes'] = $addoutcomes;
}
if ($sesskey !== '') {
    $params['sesskey'] = $sesskey;
}

redirect(new moodle_url('/grade/edit/outcome/course.php', $params));
