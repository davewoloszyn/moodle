<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/formslib.php");

class paymentupload_form extends \moodleform {

    public function definition(): void {
        global $USER;

        $mform = $this->_form;
        $contextid = context_user::instance($USER->id)->id;
        $component = 'local_paymentupload';
        $filearea = 'paymentfiles';

        $draftitemid = file_get_submitted_draft_itemid('userfile');
        file_prepare_draft_area(
            $draftitemid,
            $contextid,
            $component,
            $filearea,
            0,
        );

        // Use the filepicker for uploading content. You can set the accepted file types and sizes here.
        $mform->addElement('filepicker', 'userfile', get_string('paymentdocument', 'local_paymentupload'), null, [
            'maxbytes' => 10485760,
            'accepted_types' => ['pdf', 'jpg', 'jpeg', 'png'],
        ]);

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons();
    }
}
