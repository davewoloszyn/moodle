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
 * Allows the user to edit theme related items that affect only them.
 *
 * @package    core_user
 * @copyright  2024 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once($CFG->libdir.'/gdlib.php');
require_once($CFG->dirroot.'/user/theme_form.php');
require_once($CFG->dirroot.'/user/editlib.php');
require_once($CFG->dirroot.'/user/profile/lib.php');
require_once($CFG->dirroot.'/user/lib.php');

$userid = optional_param('id', $USER->id, PARAM_INT);    // User id.
$courseid = optional_param('course', SITEID, PARAM_INT);   // Course id (defaults to Site).

$PAGE->set_url('/user/theme.php', ['id' => $userid]);

$redirect = new moodle_url("/user/theme.php", ['userid' => $user->id]);

list($user, $course) = useredit_setup_preference_page($userid, $courseid);

// Load user preferences.
useredit_load_preferences($user);


// Create form.
$form = new user_edit_theme_form(null, ['user' => $user]);

// Set data for form.
//$user->preference_darkmode = get_user_preferences('darkmode', null, $user->id);
$form->set_data($user);

if ($form->is_cancelled()) {
    redirect($redirect);
} else if ($data = $form->get_data()) {

    set_user_preference('theme_darkmode', $data->preference_theme_darkmode);

    // Update the user's theme.
    if (isset($data->theme)) {
        $updateuser = new stdClass();
        $updateuser->id   = $data->id;
        $updateuser->theme = $data->theme;
        user_update_user($updateuser, false);
    }
    \core\session\manager::gc(); // Remove stale sessions.
    redirect($redirect, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Display page header.
$heading = get_string('themepreferences');
$userfullname = fullname($user, true);

$PAGE->navbar->includesettingsbase = true;

$PAGE->set_title("$course->shortname: $heading");
$PAGE->set_heading($userfullname);
//$PAGE->set_cacheable(false);

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

// Display the form.
$form->display();

// Display the footer.
echo $OUTPUT->footer();
