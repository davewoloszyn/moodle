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

namespace core\oauth2;

use Firebase\JWT\JWT;

/**
 * External core oauth2 API tests.
 *
 * @package    core
 * @copyright  2023 eabyas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_oauth2\apple_expiry_reminder
 */
final class apple_expiry_reminder_test extends \advanced_testcase {
    /**
     * @var object $appleissuer New appleissuer created to test the expiry reminder email.
     */
    protected $appleissuer = null;
    /**
     * Called before every test.
     */
    public function setUp(): void {
        parent::setUp();
        $classname = 'core\\oauth2\\service\\apple';
        if (class_exists($classname)) {
            $issuer = $classname::init();
            $issuer->create();
            $this->appleissuer = $issuer;
        }
    }
    /**
     * Test expiry reminder email via the send apple expiry reminder email method.
     *
     */
    public function test_send_apple_expiry_reminder_email(): void {

        $this->resetAfterTest();
        $this->setAdminUser();

        // Set an expired date.
        $pastdate = strtotime('-1 week');

        // Configure issuer.
        $secretkey = $this->generate_secretkey($pastdate);
        $this->appleissuer->set('clientid', 'apple1');
        $this->appleissuer->set('clientsecret', $secretkey);

        // Check service expiry email has been sent.
        $sink = $this->redirectEmails();
        $appleexpiryreminder = new apple_expiry_reminder();
        $appleexpiryreminder->send_expiry_reminder_email($this->appleissuer);
        $emails = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $emails);

        // Update to valid date.
        $futuredate = strtotime('+1 week');

        // Update issuer.
        $secretkey = $this->generate_secretkey($futuredate);
        $this->appleissuer->set('clientsecret', $secretkey);

        // Check service expiry email is not sent (date is still valid).
        $sink = $this->redirectEmails();
        $appleexpiryreminder = new apple_expiry_reminder();
        $appleexpiryreminder->send_expiry_reminder_email($this->appleissuer);
        $emails = $sink->get_messages();
        $sink->close();
        $this->assertCount(0, $emails);
    }

    /**
     * Generate secret key.
     *
     * @param int $date expiry date to generate the key.
     * @return string
     */
    protected function generate_secretkey($date) {
        global $DB;

        // Set the sample data to generate the secret key.
        $tokeninfo = [];
        $tokeninfo['iat'] = 'apple1';
        $tokeninfo['exp'] = $date;
        $tokeninfo['aud'] = 'https://appleid.apple.com';
        $tokeninfo['sub'] = 'apple1';

        // Generate sample secret key.
        $secretkey = $this->create_json_encoded_token($tokeninfo);
        return $secretkey;
    }

    /**
     * Create json encoded token.
     *
     * @param   array $data The key information to process and create the json encoded token.
     * @return  string
     */
    protected function create_json_encoded_token($data = []) {
        $generatedtoken = JWT::urlsafeB64Encode(JWT::jsonEncode($data));
        $parta = 'appletesta.';
        $partb = '.appletestb';
        return $parta . $generatedtoken . $partb;
    }
}
