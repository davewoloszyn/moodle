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

// Change the context here for the user.
$context = context_user::instance($USER->id);
$PAGE->set_context($context);

$PAGE->set_url('/local/paymentupload/upload.php', ['courseid' => $courseid]);
$PAGE->set_title(get_string('uploadpayment', 'local_paymentupload'));
$PAGE->set_heading(get_string('uploadpayment', 'local_paymentupload'));

require_once(__DIR__ . '/paymentupload_form.php');

// This class extends moodleform and makes form creation very easy.
// This form has the filepicker inside it which allows for uploading.
$form = new paymentupload_form(null, ['courseid' => $courseid]);

// Handle form submission.
if ($data = $form->get_data()) {

    // Get the draft item ID from the submitted form data.
    $draftitemid = file_get_submitted_draft_itemid('userfile');

    // Save the file to a permanent file area (e.g. user draft area to your plugin's file area).
    file_save_draft_area_files(
        $draftitemid,             // draft item ID
        $context->id,             // context ID (e.g., user in this case)
        'local_paymentupload',    // component
        'paymentfiles',           // filearea
        $USER->id,                // itemid (often the user ID or 0 if not tied to a specific entity)
        ['subdirs' => 0, 'maxbytes' => 10485760, 'accepted_types' => ['pdf', 'jpg', 'jpeg', 'png']]
    );

    // Now retrieve the stored file from the permanent area
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'local_paymentupload', 'paymentfiles', $USER->id, 'itemid, filepath, filename', false);
    $uploadid = null;

    foreach ($files as $file) {
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
        local_paymentupload_send_notification($uploadid);
    }

    if (!empty($uploadid)) {
        $message = get_string('uploadsuccess', 'local_paymentupload');
    } else {
        $message = get_string('uploaderror', 'local_paymentupload');
    }

    redirect(new moodle_url('/course/view.php', ['id' => $courseid]), $message);

}

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('uploadpayment', 'local_paymentupload') . ' - ' . $course->fullname);

$form->display();

if (isset($error)) {
    echo $OUTPUT->notification($error, 'error');
}

echo $OUTPUT->footer();
