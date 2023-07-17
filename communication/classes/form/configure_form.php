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
 * Configure communication for a given instance - the form definition.
 *
 * @package    core_communication
 * @copyright  2023 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_communication\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * Defines the communication configure form.
 */
class configure_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {

        $mform = $this->_form;
        $instanceid = $this->_customdata['instanceid'];
        $instancetype = $this->_customdata['instancetype'];
        $component = $this->_customdata['component'];

        // Get the instance we are configuring for.
        if ($instancetype == 'coursecommunication') {
            $instance = get_course($instanceid);
        }

        // Add communication plugins to the form.
        $defaultprovider = \core_communication\processor::PROVIDER_NONE;
        $communication = \core_communication\api::load_by_instance(
            $component,
            $instancetype,
            $instanceid);
        $communication->form_definition($mform, $defaultprovider);
        $communication->set_data($instance);

        // Form buttons.
        $buttonarray = [];
        $classarray = ['class' => 'form-submit'];
        $buttonarray[] = &$mform->createElement('submit', 'saveandreturn', get_string('savechanges'), $classarray);
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');

        // Hidden elements.
        $mform->addElement('hidden', 'instanceid', $instanceid);
        $mform->setType('instanceid', PARAM_INT);
        $mform->addElement('hidden', 'instancetype', $instancetype);
        $mform->setType('instancetype', PARAM_TEXT);
        $mform->addElement('hidden', 'component', $component);
        $mform->setType('component', PARAM_TEXT);

        // Finally set the current form data.
        $this->set_data($instance);
    }

    /**
     * Fill in the communication page data depending on provider selected.
     */
    public function definition_after_data() {

        $mform = $this->_form;
        $instanceid = $mform->getElementValue('instanceid');
        $instancetype = $mform->getElementValue('instancetype');
        $component = $mform->getElementValue('component');

        // Add communication plugins to the form with respect to the provider.
        $communication = \core_communication\api::load_by_instance(
            $component,
            $instancetype,
            $instanceid
        );
        $communication->form_definition_for_provider($mform);
    }
}
