<?php
require_once('../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once('lib.php');

$courseid = required_param('courseid', PARAM_INT);

require_login();

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

// Check if user is already enrolled
if (is_enrolled($context, $USER)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

$PAGE->set_url('/local/paymentupload/upload.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('uploadpayment', 'local_paymentupload'));
$PAGE->set_heading(get_string('uploadpayment', 'local_paymentupload'));

// Handle form submission
if ($data = data_submitted() && confirm_sesskey()) {
    // Handle file upload
    $fs = get_file_storage();
    $usercontext = context_user::instance($USER->id);

    $fileinfo = array(
        'contextid' => $usercontext->id,
        'component' => 'local_paymentupload',
        'filearea'  => 'payment_documents',
        'itemid'    => rand(), // DW.
        'filepath'  => '/',
        'filename'  => $_FILES['paymentfile']['name']
    );

    // Validate file type
    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
    $file_extension = strtolower(pathinfo($_FILES['paymentfile']['name'], PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_types)) {
        $error = get_string('paymentfiletype', 'local_paymentupload');
    } else if ($_FILES['paymentfile']['size'] > 10485760) { // 10MB
        $error = get_string('paymentmaxsize', 'local_paymentupload');
    } else {
        // Save file
        $file = $fs->create_file_from_pathname($fileinfo, $_FILES['paymentfile']['tmp_name']);

        if ($file) {
            // Save record to database
            $record = new stdClass();
            $record->userid = $USER->id;
            $record->courseid = $courseid;
            $record->filename = $file->get_filename();
            $record->filepath = $file->get_filepath();
            $record->status = 0; // Pending
            $record->timecreated = time();
            $record->timemodified = time();

            $uploadid = $DB->insert_record('local_paymentupload_uploads', $record);

            // Send notification email
            local_paymentupload_send_notification($uploadid);

            redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
                    get_string('uploadsuccess', 'local_paymentupload'));
        }
    }
}

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('uploadpayment', 'local_paymentupload') . ' - ' . $course->fullname);

if (isset($error)) {
    echo $OUTPUT->notification($error, 'error');
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'enctype' => 'multipart/form-data',
    'class' => 'mform'
]);

echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_tag('div', ['class' => 'form-group']);
echo html_writer::tag('label', get_string('paymentdocument', 'local_paymentupload'), ['for' => 'paymentfile']);
echo html_writer::empty_tag('input', [
    'type' => 'file',
    'name' => 'paymentfile',
    'id' => 'paymentfile',
    'accept' => '.pdf,.jpg,.jpeg,.png',
    'required' => 'required'
]);
echo html_writer::tag('small', get_string('paymentfiletype', 'local_paymentupload') . '<br>' .
                               get_string('paymentmaxsize', 'local_paymentupload'), ['class' => 'form-text']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'form-group']);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('uploadpayment', 'local_paymentupload'),
    'class' => 'btn btn-primary'
]);
echo html_writer::end_tag('div');

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
