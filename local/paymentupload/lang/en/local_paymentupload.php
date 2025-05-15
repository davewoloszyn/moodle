<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Payment Upload';
$string['paymentupload'] = 'Upload Payment Document';
$string['uploadpayment'] = 'Upload Payment';
$string['paymentdocument'] = 'Payment Document (PDF/Image)';
$string['paymentverification'] = 'Payment Verification';
$string['verifyenrollment'] = 'Verify and Enroll';
$string['studentname'] = 'Student Name';
$string['coursename'] = 'Course Name';
$string['uploaddate'] = 'Upload Date';
$string['verificationstatus'] = 'Status';
$string['verified'] = 'Verified and Enrolled';
$string['pending'] = 'Pending Verification';
$string['rejected'] = 'Rejected';
$string['paymentfiletype'] = 'Only PDF, JPG, JPEG, and PNG files are allowed';
$string['paymentmaxsize'] = 'Maximum file size: 10MB';
$string['uploadsuccess'] = 'Payment document uploaded successfully. An admin will verify your payment shortly.';
$string['emailsubject'] = 'New Payment Upload for Course: {$a}';
$string['emailbody'] = 'A student has uploaded a payment document for verification.

Student: {$a->studentname}
Course: {$a->coursename}
Upload Date: {$a->uploaddate}

Please log in to verify the payment and enroll the student if the payment is valid.

Link: {$a->link}';
