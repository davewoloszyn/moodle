<?php

defined('MOODLE_INTERNAL') || die();

class block_paymentupload extends block_base {
    public function init() {
        $this->title = get_string('uploadpayment', 'local_paymentupload');
    }
    
    public function get_content() {
        global $USER, $DB, $COURSE;
        
        if ($this->content !== null) {
            return $this->content;
        }
        
        $this->content = new stdClass();
        $this->content->text = '';
        
        // Check if user is not enrolled
        $context = context_course::instance($COURSE->id);
        if (!is_enrolled($context, $USER)) {
            // Check if no pending upload
            $existing = $DB->get_record('local_paymentupload_uploads', [
                'userid' => $USER->id,
                'courseid' => $COURSE->id,
                'status' => 0
            ]);
            
            if (!$existing) {
                $url = new moodle_url('/local/paymentupload/upload.php', ['courseid' => $COURSE->id]);
                $this->content->text = html_writer::link($url, 
                    get_string('uploadpayment', 'local_paymentupload'), 
                    ['class' => 'btn btn-primary']
                );
            }
        }
        
        return $this->content;
    }
}