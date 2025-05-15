<?php
// local/paymentupload/db/hooks.php
defined('MOODLE_INTERNAL') || die();

// Register the hook
$hooks = array(
    array(
        'hookname' => 'before_footer',
        'callback' => 'local_paymentupload_before_footer',
        'file' => '/local/paymentupload/lib.php'
    )
);
