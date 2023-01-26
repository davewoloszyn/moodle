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

namespace core_communication;

/**
 * Class course_communication to manage communication elements, task creation etc.
 *
 * @package    core_communication
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class communication_manager {

    /**
     * @var \stdClass $course The course object
     */
    private \stdClass $course;

    /**
     * @var course_communication $coursecommunication The course communication object
     */
    private course_communication $coursecommunication;

    /**
     * Course communication manager constructor to load the course communication information from course_communication table.
     */
    public function __construct(\stdClass $course = null) {
        if ($course !== null) {
            $this->course = $course;
        }
        if (!empty($this->course->id)) {
            $this->coursecommunication = new course_communication($course);
            if ($this->coursecommunication->record_exist()) {
                $this->set_data($this->course);
            }
        }
    }

    /**
     * Get the available communication providers.
     * It will only supply the enabled ones and also the ones implementing the plugin entrypoint.
     *
     * @return array
     */
    public function get_available_communication_providers(): array {
        $plugintype = 'communication';
        $plugins = \core_component::get_plugin_list($plugintype);
        foreach ($plugins as $pluginname => $plugin) {
            if (!\core\plugininfo\communication::is_plugin_enabled($plugintype . '_' . $pluginname)) {
                unset($plugins[$pluginname]);
            }
        }
        return $plugins;
    }

    /**
     * Get the list of plugins for form selection.
     *
     * @return array
     */
    public function get_communication_plugin_list_for_form(): array {
        $selection = [];
        $communicationplugins = $this->get_available_communication_providers();
        foreach ($communicationplugins as $pluginname => $notusing) {
            $selection['communication_' . $pluginname] = get_string('pluginname', 'communication_'. $pluginname);
        }
        return $selection;
    }

    /**
     * Define the form elements for the communication api.
     *
     * @param \MoodleQuickForm $mform The form element
     * @return void
     */
    public function form_definition(\MoodleQuickForm $mform): void {
        $mform->addElement('header', 'communication', get_string('communication', 'communication'));
        $mform->addElement('selectyesno', 'enablecommunication',
            get_string('enablecommunication', 'communication'));
        $mform->setDefault('enablecommunication', get_config('communication_matrix', 'matrixcreateroom'));
        $mform->addHelpButton('enablecommunication', 'enablecommunication', 'communication');

        // List the communication providers if enabled/selected yes.
        $communicationproviders = $this->get_communication_plugin_list_for_form();
        $mform->addElement('select', 'selectedcommunication',
            get_string('seleccommunicationprovider', 'communication'), $communicationproviders);
        $mform->hideIf('selectedcommunication', 'enablecommunication', 'eq', 0);

        // Room name for the communication provider.
        $mform->addElement('text', 'communicationroomname',
            get_string('communicationroomname', 'communication'), 'maxlength="100" size="20"');
        $mform->addHelpButton('communicationroomname', 'communicationroomname', 'communication');
        $mform->setType('communicationroomname', PARAM_TEXT);
        $mform->hideIf('communicationroomname', 'enablecommunication', 'eq', 0);

        // Room description for the communication provider.
        $mform->addElement('text', 'communicationroomdesc',
            get_string('communicationroomdesc', 'communication'), 'maxlength="255" size="20"');
        $mform->addHelpButton('communicationroomdesc', 'communicationroomdesc', 'communication');
        $mform->setType('communicationroomdesc', PARAM_TEXT);
        $mform->hideIf('communicationroomdesc', 'enablecommunication', 'eq', 0);
    }

    /**
     * Set the form data.
     *
     * @param \stdClass $course The course object
     * @return void
     */
    public function set_data(\stdClass $course): void {
        if (!empty($course->id)) {
            $course->enablecommunication = $this->coursecommunication->get_communication_status();
            $course->selectedcommunication = $this->coursecommunication->get_provider();
            $course->communicationroomname = $this->coursecommunication->get_roomname();
            $course->communicationroomdesc = $this->coursecommunication->get_room_description();
        }
    }

    /**
     * Save the data from the form.
     *
     * @return void
     */
    public function save_form_data(): void {
        if (!empty($this->course->id)) {

            $this->coursecommunication->set_status($this->course->enablecommunication);
            $this->coursecommunication->set_provider($this->course->selectedcommunication);

            if (!empty($this->course->communicationroomname)) {
                $this->coursecommunication->set_roomname($this->course->communicationroomname);
            }

            if (!empty($this->course->communicationroomdesc)) {
                $this->coursecommunication->set_room_description($this->course->communicationroomdesc);
            }
        }
    }

    /**
     * Create a communication ad-hoc task for create operation.
     *
     * @return void
     */
    public function create(): void {
        $this->save_form_data();
        $this->coursecommunication->create();
        // TODO move this to adhoc task.
        $communicationroom = new communication($this->coursecommunication->get_provider(), $this->course->id);
        $communicationroom->set_room_options($this->coursecommunication->get_roomname(),
            $this->coursecommunication->get_room_description());
        $communicationroom->create();
    }

    /**
     * Create a communication ad-hoc task for update operation.
     *
     * @return void
     */
    public function update(): void {
        $this->save_form_data();
        $this->coursecommunication->update();
        // TODO move this to adhoc task.
        $communicationroom = new communication($this->coursecommunication->get_provider(), $this->course->id);
        $communicationroom->set_room_options($this->coursecommunication->get_roomname(),
            $this->coursecommunication->get_room_description());
        $communicationroom->update();
        if ($this->course->visible !== $this->course->visibleold) {
            // TODO check the usage of this bit and move to a working location.
            $communicationroom->status();
        }

    }

    /**
     * Create a communication ad-hoc task for delete operation.
     *
     * @return void
     */
    public function delete(): void {
        // TODO move this to adhoc task.
        if ($this->coursecommunication->record_exist()) {
            $communicationroom = new communication($this->coursecommunication->get_provider(), $this->course->id);
            $communicationroom->delete();

            $this->coursecommunication->delete();
        }
    }

}
