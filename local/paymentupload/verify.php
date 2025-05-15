<?php
require_once('../../config.php');

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

require_login();

// Check if user has permission to verify payments
$systemcontext = context_system::instance();
require_capability('moodle/site:config', $systemcontext);

$PAGE->set_url('/local/paymentupload/verify.php');
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('paymentverification', 'local_paymentupload'));
$PAGE->set_heading(get_string('paymentverification', 'local_paymentupload'));

// Handle verification actions
if ($id && $action && confirm_sesskey()) {
    $upload = $DB->get_record('local_paymentupload_uploads', ['id' => $id], '*', MUST_EXIST);
    
    if ($action === 'verify') {
        // Enroll student in course
        $course = $DB->get_record('course', ['id' => $upload->courseid], '*', MUST_EXIST);
        
        // Get manual enrollment plugin
        $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        
        if ($manual) {
            $manualenrol = enrol_get_plugin('manual');
            $manualenrol->enrol_user($manual, $upload->userid);
            
            // Update upload record
            $upload->status = 1; // Verified
            $upload->verifiedby = $USER->id;
            $upload->timemodified = time();
            $DB->update_record('local_paymentupload_uploads', $upload);
            
            redirect($PAGE->url, 'Student enrolled successfully!');
        }
    } else if ($action === 'reject') {
        $upload->status = 2; // Rejected
        $upload->verifiedby = $USER->id;
        $upload->timemodified = time();
        $DB->update_record('local_paymentupload_uploads', $upload);
        
        redirect($PAGE->url, 'Payment rejected.');
    }
}

echo $OUTPUT->header();

// Get all pending uploads
$sql = "SELECT pu.*, u.firstname, u.lastname, c.fullname as coursename
        FROM {local_paymentupload_uploads} pu
        JOIN {user} u ON u.id = pu.userid
        JOIN {course} c ON c.id = pu.courseid
        ORDER BY pu.timecreated DESC";

$uploads = $DB->get_records_sql($sql);

if (empty($uploads)) {
    echo html_writer::tag('p', 'No payment uploads found.');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('studentname', 'local_paymentupload'));
    echo html_writer::tag('th', get_string('coursename', 'local_paymentupload'));
    echo html_writer::tag('th', get_string('uploaddate', 'local_paymentupload'));
    echo html_writer::tag('th', get_string('verificationstatus', 'local_paymentupload'));
    echo html_writer::tag('th', 'Actions');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($uploads as $upload) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', fullname($upload));
        echo html_writer::tag('td', $upload->coursename);
        echo html_writer::tag('td', userdate($upload->timecreated));
        
        $status = '';
        switch ($upload->status) {
            case 0:
                $status = get_string('pending', 'local_paymentupload');
                break;
            case 1:
                $status = get_string('verified', 'local_paymentupload');
                break;
            case 2:
                $status = get_string('rejected', 'local_paymentupload');
                break;
        }
        echo html_writer::tag('td', $status);
        
        // Actions
        $actions = '';
        if ($upload->status == 0) {
            // View document link
            $fs = get_file_storage();
            $usercontext = context_user::instance($upload->userid);
            $files = $fs->get_area_files($usercontext->id, 'local_paymentupload', 'payment_documents');
            
            foreach ($files as $file) {
                if ($file->get_filename() === $upload->filename) {
                    $url = moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename()
                    );
                    $actions .= html_writer::link($url, 'View Document', ['target' => '_blank']) . ' | ';
                    break;
                }
            }
            
            $verifyurl = new moodle_url($PAGE->url, ['id' => $upload->id, 'action' => 'verify', 'sesskey' => sesskey()]);
            $rejecturl = new moodle_url($PAGE->url, ['id' => $upload->id, 'action' => 'reject', 'sesskey' => sesskey()]);
            
            $actions .= html_writer::link($verifyurl, 'Verify & Enroll', ['class' => 'btn btn-success btn-sm']);
            $actions .= ' ';
            $actions .= html_writer::link($rejecturl, 'Reject', ['class' => 'btn btn-danger btn-sm']);
        }
        echo html_writer::tag('td', $actions);
        
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo $OUTPUT->footer();
