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
 * Configure communication for a given instance.
 *
 * @package    core_communication
 * @copyright  2023 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once('lib.php');

$instanceid = required_param('instanceid', PARAM_INT);
$instancetype = required_param('instancetype', PARAM_TEXT);
$component = required_param('component', PARAM_TEXT);

$pageparams = [
    'instanceid' => $instanceid,
    'instancetype' => $instancetype,
    'component' => $component,
];

// Must be logged in and not a guest.
if (!isloggedin() || isguestuser()) {
    throw new \moodle_exception('noguest');
}

// Requires communication to be enabled.
if (!core_communication\api::is_available()) {
    throw new \moodle_exception('communicationdisabled', 'communication');
}

// Attempt to load the communication instance with the provided params.
$communication = \core_communication\api::load_by_instance($component, $instancetype, $instanceid);

// No communication, no way this from can be used.
if (!$communication) {
    throw new \moodle_exception('nocommunicationinstance', 'communication');
}

// Let's get variables according to the instance type.
switch ($instancetype) {

    case 'coursecommunication':

        $context = context_course::instance($instanceid);
        require_capability('moodle/communication:configurerooms', $context);
        require_login($instanceid);

        if (!$instance = $DB->get_record('course', ['id' => $instanceid])) {
            throw new \moodle_exception('invalidcourseid');
        }

        // Instance specific params.
        $heading = $instance->fullname;
        $pagelayout = 'course';
        $backtourl = new moodle_url('/course/view.php', ['id' => $instanceid]);
        break;

    default:

        // There is no acceptable instance type.
        throw new \moodle_exception('nocommunicationinstance', 'communication');
        break;
}

// Set up the page.
$PAGE->set_context($context);
$PAGE->set_url('/communication/configure.php', $pageparams);
$PAGE->set_title(get_string('communication', 'communication'));
$PAGE->set_heading($heading);
$PAGE->add_body_class('limitedwidth');

// Get our form definitions.
$form = new \core_communication\form\configure_form(null, $pageparams);

if ($form->is_cancelled()) {

    redirect($backtourl);

} else if ($data = $form->get_data()) {

    // Hande the form data depending on our instance type.
    switch ($instancetype) {

        case 'coursecommunication':

            $data->id = $data->instanceid; // For correct use in update_course.
            update_course($data);
            redirect($backtourl);
            break;

        default:

            redirect($backtourl);
            break;
    }
}

// Display the page contents.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('communication', 'communication'), 2);
$form->display();
echo $OUTPUT->footer();
