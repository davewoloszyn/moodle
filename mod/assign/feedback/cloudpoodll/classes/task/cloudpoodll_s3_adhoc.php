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
 * A assignfeedback_cloudpoodll adhoc task
 *
 * @package    assignfeedback_cloudpoodll
 * @copyright  2019 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignfeedback_cloudpoodll\task;

defined('MOODLE_INTERNAL') || die();

use \assignfeedback_cloudpoodll\constants;
use \assignfeedback_cloudpoodll\utils;

/**
 * Assignfeedback_cloudpoodll adhoc task to fetch back transcriptions from Amazon S3
 *
 * @package    assignfeedback_cloudpoodll
 * @since      Moodle 3.1
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cloudpoodll_s3_adhoc extends \core\task\adhoc_task {

    /**
     *  Run the tasks
     */
    public function execute() {
        global $DB;
        $trace = new \text_progress_trace();

        // CD should contain activityid / attemptid and modulecontextid.
        $cd = $this->get_custom_data();
        $feedback = $cd->feedback;
        // $trace->output($cd->somedata).

        if (!$DB->record_exists(constants::M_TABLE, ['id' => $feedback->id])) {
            return false;
        }

        $transcript = utils::fetch_transcriptdata($feedback->filename . '.txt');
        $fulltranscript = false;
        $vttdata = false;
        if ($transcript) {
            $fulltranscript = utils::fetch_transcriptdata($feedback->filename . '.json');
            $vttdata = utils::fetch_transcriptdata($feedback->filename . '.vtt');
        }
        if ($transcript === false) {
            if ($cd->taskcreationtime + (HOURSECS * 24) < time()) {
                $this->do_forever_fail('No transcript could be found', $trace);
                return;
            } else {
                $this->do_retry('Transcript appears to not be ready yet', $trace, $cd);
                return;
            }
        } else {
            // yay we have transcript data, let us save that shall we .
            $feedback->transcript = $transcript;
            if ($fulltranscript) {
                $feedback->fulltranscript = $fulltranscript;
            }
            if ($vttdata) {
                $feedback->vttdata = $vttdata;
            }
            $DB->update_record(constants::M_TABLE, $feedback);
            return;
        }
    }

    protected function do_retry($reason, $trace, $customdata) {
        if($customdata->taskcreationtime + (HOURSECS * 24) < time()){
            //after 24 hours we give up
            $trace->output($reason . ": Its been more than 24 hours. Giving up on this transcript.");
            return;

        }elseif ($customdata->taskcreationtime + (MINSECS * 15) < time()) {
            //15 minute delay
            $delay = (MINSECS * 15);
        }else{
            //30 second delay
            $delay = 30;
        }
        $trace->output($reason . ": will try again next cron after $delay seconds");
        $s3_task = new \assignfeedback_cloudpoodll\task\cloudpoodll_s3_adhoc();
        $s3_task->set_component(constants::M_COMPONENT);
        $s3_task->set_custom_data($customdata);
        //if we do not set the next run time it can extend the current cron job indef with a recurring task
        $s3_task->set_next_run_time(time()+$delay);
        // queue it
        \core\task\manager::queue_adhoc_task($s3_task);
    }

    protected function do_forever_fail($reason, $trace) {
        $trace->output($reason . ": will not retry ");
    }

}

