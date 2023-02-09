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

namespace communication_matrix;

use core_communication\communication_handler;
use core_communication\communication_settings_data;
use core_communication\communication_test_helper_trait;
use communication_matrix\matrix_test_helper_trait;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/matrix_test_helper_trait.php');
require_once(__DIR__ . '/../../../tests/communication_test_helper_trait.php');

/**
 * Class matrix_provider_test to test the matrix provider scenarios using the matrix endpoints.
 *
 * @package    communication_matrix
 * @category   test
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_communication\communication
 * @coversDefaultClass \core_communication\task\communication_room_operations
 * @coversDefaultClass \core_communication\communication_room_base
 * @coversDefaultClass \communication_matrix\matrix_room_manager
 */
class matrix_communication_test extends \advanced_testcase {

    use matrix_test_helper_trait;
    use communication_test_helper_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->initialise_mock_server();
    }

    /**
     * Test creating course with matrix provider creates all the associated data and matrix room.
     *
     * @return void
     * @covers ::create_room
     * @covers ::set_room_options
     * @covers ::execute
     */
    public function test_create_course_with_matrix_provider(): void {
        // Sample data.
        $roomname = 'Samplematrixroom';
        $provider = 'communication_matrix';
        $course = $this->get_course($roomname, $provider);

        // Run the task.
        $this->runAdhocTasks('\core_communication\task\communication_room_operations');

        // Get the communication id.
        $communicationsettingsdata = new communication_settings_data($course->id, 'core_course', 'coursecommunication');
        $this->assertTrue($communicationsettingsdata->record_exist());

        // Initialize the matrix room object.
        $matrixrooms = new matrix_rooms($communicationsettingsdata->get_communication_instance_id());

        // Test against the data.
        $matrixroomdata = $this->get_matrix_room_data($matrixrooms->roomid);
        $this->assertEquals($matrixrooms->roomid, $matrixroomdata->room_id);
        $this->assertEquals($matrixrooms->roomalias, $matrixroomdata->canonical_alias);
        $this->assertEquals($roomname, $matrixroomdata->name);
    }

    /**
     * Test update course with matrix provider.
     *
     * @return void
     * @covers ::update_room
     * @covers ::set_room_options
     */
    public function test_update_course_with_matrix_provider(): void {
        global $CFG;
        $course = $this->get_course();

        // Sample data.
        $communicationroomname = 'Sampleroomupdated';
        $selectedcommunication = 'communication_matrix';
        $avatarurl = $CFG->dirroot . '/communication/provider/matrix/tests/fixtures/moodle_logo.jpg';

        // Handler object to update communication data.
        $communication = new communication_handler($course->id, $avatarurl);
        $communication->update_room_and_membership($selectedcommunication, $communicationroomname);

        // Run the task.
        $this->runAdhocTasks('\core_communication\task\communication_room_operations');

        // Get the communication id.
        $communicationsettingsdata = new communication_settings_data($course->id, 'core_course', 'coursecommunication');
        $this->assertTrue($communicationsettingsdata->record_exist());

        // Initialize the matrix room object.
        $matrixrooms = new matrix_rooms($communicationsettingsdata->get_communication_instance_id());

        // Test against the data.
        $matrixroomdata = $this->get_matrix_room_data($matrixrooms->roomid);
        $this->assertEquals($matrixrooms->roomid, $matrixroomdata->room_id);
        $this->assertEquals($matrixrooms->roomalias, $matrixroomdata->canonical_alias);
        $this->assertEquals($communicationroomname, $matrixroomdata->name);
        $this->assertNotEmpty($matrixroomdata->avatar);
    }

    /**
     * Test course delete with matrix provider.
     *
     * @return void
     * @covers ::delete_room
     * @covers ::set_room_options
     * @covers ::execute
     */
    public function test_delete_course_with_matrix_provider(): void {
        global $DB;
        // Sample data.
        $roomname = 'Samplematrixroom';
        $provider = 'communication_matrix';
        $course = $this->get_course($roomname, $provider);

        // Run the task.
        $this->runAdhocTasks('\core_communication\task\communication_room_operations');

        // Get the communication id.
        $communicationsettingsdata = new communication_settings_data($course->id, 'core_course', 'coursecommunication');
        $this->assertTrue($communicationsettingsdata->record_exist());
        $communicationid = $communicationsettingsdata->get_communication_instance_id();

        // Initialize the matrix room object.
        $matrixrooms = new matrix_rooms($communicationsettingsdata->get_communication_instance_id());

        // Test against the data.
        $matrixroomdata = $this->get_matrix_room_data($matrixrooms->roomid);
        $this->assertEquals($matrixrooms->roomid, $matrixroomdata->room_id);
        $this->assertEquals($matrixrooms->roomalias, $matrixroomdata->canonical_alias);

        // Now delete the course.
        delete_course($course, false);

        // Run the task.
        $this->runAdhocTasks('\core_communication\task\communication_room_operations');

        // Get the communication id.
        $communicationsettingsdata = new communication_settings_data($course->id, 'core_course', 'coursecommunication');
        $this->assertFalse($communicationsettingsdata->record_exist());

        // Initialize the matrix room object.
        $matrixrooms = $DB->get_record('matrix_rooms', ['commid' => $communicationid]);
        $this->assertEmpty($matrixrooms);
    }

    /**
     * Test creating course with matrix provider creates all the associated data and matrix room.
     *
     * @return void
     * @covers ::execute
     */
    public function test_create_members_with_matrix_provider(): void {
        // Insert required fields first.
        $this->run_post_install_task();

        $course = $this->get_course('Samplematrixroom', 'communication_matrix');
        $user = $this->get_user('Samplefnmatrix', 'Samplelnmatrix', 'sampleunmatrix');

        // Run room operation task.
        $this->runAdhocTasks('\core_communication\task\communication_room_operations');

        // Enrol the user in the course.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course->id, true);
        $enrol->enrol_user(reset($enrolinstances), $user->id);

        // Run user operation task.
        $this->runAdhocTasks('\core_communication\task\communication_user_operations');

        $communicationsettingsdata = new communication_settings_data($course->id, 'core_course', 'coursecommunication');
        $matrixrooms = new matrix_rooms($communicationsettingsdata->get_communication_instance_id());
        $eventmanager = new matrix_events_manager($matrixrooms->roomid);

        // Get matrix user id from moodle
        $matrixuserid = matrix_user_manager::get_matrixid_from_moodle($user->id, $eventmanager->matrixhomeserverurl);
        $this->assertNotNull($matrixuserid);

        // Get matrix user id from matrix
        $matrixuserdata = $this->get_matrix_user_data($matrixrooms->roomid, $matrixuserid);
        $this->assertNotEmpty($matrixuserdata);
        $this->assertEquals("Samplefnmatrix Samplelnmatrix", $matrixuserdata->displayname);
    }
}
