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
/**
 * A schedule task for apple oauth2 reminder cron.
 * @copyright  2023 eabyas
 * @package   auth_oauth2
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apple_expiry_reminder extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_apple_expiry_reminder', 'auth_oauth2');
    }
    /**
     * Run auth oauth2 apple reminder cron.
     */
    public function execute() {
        global $CFG, $DB;
        $issuers = \core\oauth2\api::get_all_issuers(true);
        if (!empty($issuers)) {
            $supportuser = \core_user::get_support_user();
            foreach ($issuers as $issuer) {
                if ($issuer->get('enabled') && $issuer->get('servicetype') === 'apple') {
                    $clientsecret = $issuer->get('clientsecret');
                    $content = explode('.', $clientsecret);
                    // Decoding the configuration set in the client secret Info.
                    $configuration = \Firebase\JWT\JWT::jsonDecode(\Firebase\JWT\JWT::urlsafeB64Decode($content[1]));
                    if (date('d-m-Y', $configuration->exp) == date('d-m-Y', time())) {
                        // Trigger email with message.
                        $stringhelper = new \stdClass();
                        $stringhelper->clientid  = $issuer->get('id');
                        $stringhelper->clientname  = $issuer->get('name');
                        $stringhelper->managelink  = $CFG->wwwroot .'/admin/tool/oauth2/issuers.php';
                        $tousers = get_config('auth_oauth2', 'applereminderemails');
                        $tousers = array_filter(explode(',', $tousers));
                        if (!empty($tousers)) {
                            foreach ($tousers as $useremail) {
                                $touser = \core_user::get_user_by_email($useremail);
                                if (empty($touser)) {
                                    $touser = $this->get_dummy_user();
                                    $touser->email = trim($useremail);
                                }
                                $this->send_user_message($touser, $stringhelper, $supportuser);
                            }
                        } else {
                            $siteadmins = array_filter(explode(',', $CFG->siteadmins));
                            if (!empty($siteadmins)) {
                                foreach ($siteadmins as $userid) {
                                    $touser = \core_user::get_user($userid);
                                    $this->send_user_message($touser, $stringhelper, $supportuser);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    /**
     * Triggers emails to the admin who can recieve the content.
     *
     * @param int       $touser         The user id to whom the email has to be triggered.
     * @param stdClass  $stringhelper   The string helpers for the notification.
     * @param stdClass  $supportuser    Support user information.
     *
     * @return   null
     */
    public function send_user_message($touser, $stringhelper, $supportuser) {
        $site = get_site();
        $stringhelper->tousername  = fullname($touser);
        $stringhelper->editlink  = \html_writer::link(new \moodle_url('/admin/tool/oauth2/issuers.php',
            ['id' => $stringhelper->clientid, 'action' => 'edit']), 'Here');
        $lang = empty($touser->lang) ? get_newuser_language() : $touser->lang;
        $subject = format_string($site->fullname) .': '.
                            (string)new \lang_string('appleclientexpiredsubject', 'auth_oauth2', $stringhelper, $lang);
        $message = (string)new \lang_string('appleclientexpiredmessage', 'auth_oauth2', $stringhelper, $lang);
        // Directly email rather than using the messaging system to ensure its not routed to a popup or jabber.
        if (email_to_user($touser, $supportuser, $subject, $message)) {
            mtrace(get_string('emailsuccessnotice', 'auth_oauth2', $stringhelper));
        } else {
            mtrace(get_string('emailfailednotice', 'auth_oauth2', $stringhelper));
        }
    }
    /**
     * Helper function to return dummy noreply user record.
     *
     * @return stdClass
     */
    private function get_dummy_user() {
        global $CFG;
        $dummyuser = new \stdClass();
        $dummyuser->id = \core_user::NOREPLY_USER;
        $dummyuser->email = $CFG->noreplyaddress;
        $dummyuser->firstname = get_string('noreplyname');
        $dummyuser->username = 'noreply';
        $dummyuser->lastname = '';
        $dummyuser->confirmed = 1;
        $dummyuser->suspended = 0;
        $dummyuser->deleted = 0;
        $dummyuser->picture = 0;
        $dummyuser->auth = 'manual';
        $dummyuser->firstnamephonetic = '';
        $dummyuser->lastnamephonetic = '';
        $dummyuser->middlename = '';
        $dummyuser->alternatename = '';
        $dummyuser->imagealt = '';
        return $dummyuser;
    }
}
