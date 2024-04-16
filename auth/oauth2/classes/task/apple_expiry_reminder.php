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

namespace auth_oauth2\task;

use core\oauth2\api;
use core\task\scheduled_task;
use core_user;
use html_writer;
use lang_string;
use moodle_url;
use stdClass;
use \Firebase\JWT\JWT;

/**
 * A scheduled task for the Apple oauth2 expiry reminder.
 *
 * @package    auth_oauth2
 * @copyright  2023 eabyas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apple_expiry_reminder extends scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskappleexpiryreminder', 'auth_oauth2');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;

        $issuers = api::get_all_issuers(true);
        $siteadmins = explode(',', $CFG->siteadmins);

        if (!empty($issuers) && !empty($siteadmins)) {

            foreach ($issuers as $issuer) {

                if ($issuer->get('enabled') && $issuer->get('servicetype') === 'apple') {
                    $clientsecret = $issuer->get('clientsecret');
                    $content = explode('.', $clientsecret);
                    // Decoding the configuration set in the client secret info.
                    $configuration = JWT::jsonDecode(JWT::urlsafeB64Decode($content[1]));

                    // Send email reminder on date of expiry
                    if (date('d-m-Y', $configuration->exp) == date('d-m-Y', time())) {
                        $stringhelper = new stdClass();
                        $stringhelper->clientid  = $issuer->get('id');
                        $stringhelper->clientname  = $issuer->get('name');
                        $stringhelper->expiry  = userdate($configuration->exp, get_string('strftimedatetimeshort'));
                        $stringhelper->managelink  = $CFG->wwwroot . '/admin/tool/oauth2/issuers.php';

                        // Send message to each of our site admins.
                        foreach ($siteadmins as $userid) {
                            $touser = core_user::get_user($userid);
                            if (!empty($touser)) {
                                $this->send_user_message($touser, $stringhelper);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Sends emails to the users to warn of service expiry.
     *
     * @param stdClass $touser The user to whom the email should be sent.
     * @param stdClass $stringhelper The string helpers for the message.
     */
    public function send_user_message(stdClass $touser, stdClass $stringhelper): void {
        $lang = empty($touser->lang) ? get_newuser_language() : $touser->lang;
        $stringhelper->tousername = fullname($touser);

        // Prepare link to edit Apple OAuth 2 client.
        $url = new moodle_url('/admin/tool/oauth2/issuers.php', [
            'id' => $stringhelper->clientid,
            'action' => 'edit',
        ]);
        $text = new lang_string('edita', 'moodle', $stringhelper->clientname, $lang);
        $stringhelper->editlink = html_writer::link($url, $text);

        // Build email message.
        $subject = format_string(get_site()->fullname) . ': ' .
                new lang_string('appleexpiryremindersubject', 'auth_oauth2', $stringhelper, $lang);
        $message = new lang_string('appleexpiryreminder', 'auth_oauth2', $stringhelper, $lang);

        // Directly email rather than using the messaging system to ensure it's not routed to a popup or jabber.
        if (email_to_user($touser, core_user::get_support_user(), $subject, $message)) {
            mtrace("Apple expiry reminder successfully sent to {$stringhelper->tousername}");
        } else {
            mtrace("Error sending Apple expiry reminder to {$stringhelper->tousername}");
        }
    }
}
