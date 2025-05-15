<?php
// local/paymentupload/lib.php
defined('MOODLE_INTERNAL') || die();

/**
 * Extends course renderer to add payment upload button
 */
function local_paymentupload_extend_course_content() {
    global $PAGE, $USER, $DB, $COURSE, $OUTPUT;
    
    // Only show on course pages
    if ($PAGE->pagelayout !== 'course' || $COURSE->id == SITEID) {
        return '';
    }
    
    // Check if user is not enrolled in the course
    $context = context_course::instance($COURSE->id);
    if (is_enrolled($context, $USER)) {
        return '';
    }
    
    // Check if user already has a pending upload for this course
    $existing = $DB->get_record('local_paymentupload_uploads', [
        'userid' => $USER->id,
        'courseid' => $COURSE->id,
        'status' => 0
    ]);
    
    if ($existing) {
        return '';
    }
    
    // Add the upload button
    $url = new moodle_url('/local/paymentupload/upload.php', ['courseid' => $COURSE->id]);
    return html_writer::tag('div', 
        html_writer::link($url, get_string('uploadpayment', 'local_paymentupload'), [
            'class' => 'btn btn-primary local-paymentupload-btn'
        ]), 
        ['class' => 'local-paymentupload-container', 'style' => 'text-align: center; margin: 20px 0;']
    );
}

/**
 * Send email notification to admins about new payment upload
 */
function local_paymentupload_send_notification($uploadid) {
    global $DB, $CFG;
    
    $upload = $DB->get_record_sql("
        SELECT pu.*, u.firstname, u.lastname, u.email as useremail, c.fullname as coursename
        FROM {local_paymentupload_uploads} pu
        JOIN {user} u ON u.id = pu.userid
        JOIN {course} c ON c.id = pu.courseid
        WHERE pu.id = ?
    ", [$uploadid]);
    
    if (!$upload) {
        return false;
    }
    
    // Get site admins and teachers
    $admins = get_admins();
    $context = context_course::instance($upload->courseid);
    $teachers = get_users_by_capability($context, 'moodle/course:enrol');
    
    $recipients = array_merge($admins, $teachers);
    
    $subject = get_string('emailsubject', 'local_paymentupload', $upload->coursename);
    
    $a = new stdClass();
    $a->studentname = fullname($upload);
    $a->coursename = $upload->coursename;
    $a->uploaddate = userdate($upload->timecreated);
    $a->link = $CFG->wwwroot . '/local/paymentupload/verify.php?id=' . $uploadid;
    
    $body = get_string('emailbody', 'local_paymentupload', $a);
    
    foreach ($recipients as $recipient) {
        email_to_user($recipient, core_user::get_noreply_user(), $subject, $body);
    }
    
    return true;
}

// Alternative approach using theme override
function local_paymentupload_course_footer() {
    return local_paymentupload_extend_course_content();
}