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

namespace core_course\communication;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../communication/tests/communication_test_helper_trait.php');
require_once(__DIR__ . '/../../../communication/provider/matrix/tests/matrix_test_helper_trait.php');

use communication_matrix\matrix_test_helper_trait;
use core_communication\communication_test_helper_trait;
use core_communication\processor as communication_processor;
use core_communication\task\add_members_to_room_task;
use core_communication\task\delete_room_task;
use core_course\communication\communication_helper as course_communication_helper;
use core_group\communication\communication_helper as group_communication_helper;

/**
 * Test communication helper and its related functions.
 *
 * @package    core_course
 * @category   test
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_course\communication\communication_helper
 */
class course_communication_helper_test extends \advanced_testcase {

    use communication_test_helper_trait;
    use matrix_test_helper_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_communication_configs();
        $this->initialise_mock_server();
    }

    /**
     * Test load_by_course.
     *
     * @covers ::load_by_course
     */
    public function test_load_by_course(): void {
        // As communication is created by default.
        $course = $this->get_course();
        $coursecontext = \context_course::instance(courseid: $course->id);
        $coursecommunication = course_communication_helper::load_by_course(
            courseid: $course->id,
            context: $coursecontext,
        );
        $this->assertInstanceOf(
            expected: communication_processor::class,
            actual: $coursecommunication->get_processor(),
        );
    }

    /**
     * Test if the course instances are created properly for course default provider.
     */
    public function test_course_default_provider(): void {
        $defaultprovider = 'communication_matrix';
        // Set the default communication for course.
        set_config(
            name: 'coursecommunicationprovider',
            value: $defaultprovider,
            plugin: 'moodlecourse',
        );

        // Test that the default communication is created for course mode.
        $course = $this->get_course();
        $coursecontext = \context_course::instance(courseid: $course->id);
        $coursecommunication = course_communication_helper::load_by_course(
            courseid: $course->id,
            context: $coursecontext,
        );
        $this->assertEquals(
            expected: $defaultprovider,
            actual: $coursecommunication->get_provider(),
        );
        $this->assertEquals(
            expected: 'core_course',
            actual: $coursecommunication->get_processor()->get_component(),
        );
        $this->assertEquals(
            expected: $course->id,
            actual: $coursecommunication->get_processor()->get_instance_id(),
        );
    }

    /**
     * Test update_course_communication.
     *
     * @covers ::update_course_communication
     * @covers ::update_group_communication_instances
     * @covers ::get_enrolled_users_for_course
     */
    public function test_update_course_communication(): void {
        global $DB;

        // Set up the data with course, group, user etc.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->get_course();
        $group = $this->getDataGenerator()->create_group(record: ['courseid' => $course->id]);
        $coursecontext = \context_course::instance(courseid: $course->id);
        $teacherrole = $DB->get_record(
            table: 'role',
            conditions: ['shortname' => 'teacher'],
        );
        $this->getDataGenerator()->enrol_user(
            userid: $user->id,
            courseid: $course->id,
        );
        role_assign(
            roleid: $teacherrole->id,
            userid: $user->id,
            contextid: $coursecontext->id,
        );
        groups_add_member(
            grouporid: $group->id,
            userorid: $user->id,
        );

        // Now test that there is communication instances for the course and the user added for that instance.
        $coursecommunication = course_communication_helper::load_by_course(
            courseid: $course->id,
            context: $coursecontext,
        );
        $this->assertInstanceOf(
            expected: communication_processor::class,
            actual: $coursecommunication->get_processor(),
        );

        // Check the user is added for course communication instance.
        $courseusers = $coursecommunication->get_processor()->get_all_userids_for_instance();
        $courseusers = reset($courseusers);
        $this->assertEquals(
            expected: $user->id,
            actual: $courseusers,
        );

        // Group should not have any instance yet.
        $groupcommunication = group_communication_helper::load_by_group(
            groupid: $group->id,
            context: $coursecontext,
        );
        $this->assertNull(actual: $groupcommunication->get_processor());

        // Now update the course.
        $course->groupmode = SEPARATEGROUPS;
        $course->selectedcommunication = 'communication_matrix';
        update_course(data: $course);

        // Now there should be a group communication instance.
        $groupcommunication->reload();
        $this->assertInstanceOf(
            expected: communication_processor::class,
            actual: $groupcommunication->get_processor(),
        );

        // The course communication instance must be active.
        $coursecommunication->reload();
        $this->assertInstanceOf(
            expected: communication_processor::class,
            actual: $coursecommunication->get_processor(),
        );

        // All the course instance users must be marked as deleted.
        $coursecommunication->reload();
        $courseusers = $coursecommunication->get_processor()->get_all_delete_flagged_userids();
        $courseusers = reset($courseusers);
        $this->assertEquals(
            expected: $user->id,
            actual: $courseusers,
        );

        // Group instance should have the user.
        $groupusers = $groupcommunication->get_processor()->get_all_userids_for_instance();
        $groupusers = reset($groupusers);
        $this->assertEquals(
            expected: $user->id,
            actual: $groupusers,
        );

        // Now disable the communication instance for the course.
        $course->selectedcommunication = communication_processor::PROVIDER_NONE;
        update_course(data: $course);

        // Now both course and group instance should be disabled.
        $coursecommunication->reload();
        $this->assertNull(actual: $coursecommunication->get_processor());

        $groupcommunication->reload();
        $this->assertNull(actual: $groupcommunication->get_processor());
    }

    /**
     * Test get_access_to_all_group_cap_users.
     *
     * @covers ::get_users_has_access_to_all_groups
     */
    public function test_get_users_has_access_to_all_groups(): void {
        global $DB;
        // Set up the data with course, group, user etc.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->get_course();
        $coursecontext = \context_course::instance(courseid: $course->id);

        // Enrol user1 as teacher.
        $teacherrole = $DB->get_record(
            table: 'role',
            conditions: ['shortname' => 'manager'],
        );
        $this->getDataGenerator()->enrol_user(
            userid: $user1->id,
            courseid: $course->id,
        );
        role_assign(
            roleid: $teacherrole->id,
            userid: $user1->id,
            contextid: $coursecontext->id,
        );

        // Enrol user2 as student.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user(
            userid: $user2->id,
            courseid: $course->id,
        );
        role_assign(
            roleid: $studentrole->id,
            userid: $user2->id,
            contextid: $coursecontext->id,
        );

        $allgroupaccessusers = course_communication_helper::get_users_has_access_to_all_groups(
            userids: [$user1->id, $user2->id],
            courseid: $course->id,
        );
        $this->assertContains(
            needle: $user1->id,
            haystack: $allgroupaccessusers,
        );
        $this->assertNotContains(
            needle: $user2->id,
            haystack: $allgroupaccessusers,
        );
    }

    /**
     * Test update_communication_room_membership.
     *
     * @covers ::update_communication_room_membership
     */
    public function test_update_communication_room_membership(): void {
        global $DB;

        // Set up the data with course, group, user etc.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->get_course();
        $coursecontext = \context_course::instance(courseid: $course->id);
        $teacherrole = $DB->get_record(
            table: 'role',
            conditions: ['shortname' => 'manager'],
        );
        $this->getDataGenerator()->enrol_user(
            userid: $user->id,
            courseid: $course->id,
        );
        role_assign(
            roleid: $teacherrole->id,
            userid:$user->id,
            contextid: $coursecontext->id,
        );

        // Now remove members from room.
        course_communication_helper::update_communication_room_membership(
            course: $course,
            userids: [$user->id],
            memberaction: 'remove_members_from_room',
        );

        // Now test that there is communication instances for the course and the user removed from that instance.
        $coursecommunication = course_communication_helper::load_by_course(
            courseid: $course->id,
            context: $coursecontext,
        );

        // Check the user is added for course communication instance.
        $courseusers = $coursecommunication->get_processor()->get_all_delete_flagged_userids();
        $courseusers = reset($courseusers);
        $this->assertEquals(
            expected: $user->id,
            actual: $courseusers,
        );

        // Now add members to room.
        course_communication_helper::update_communication_room_membership(
            course: $course,
            userids: [$user->id],
            memberaction: 'add_members_to_room',
        );

        $coursecommunication->reload();
        // Check the user is added for course communication instance.
        $courseusers = $coursecommunication->get_processor()->get_instance_userids();
        $courseusers = reset($courseusers);
        $this->assertEquals(
            expected: $user->id,
            actual: $courseusers,
        );

        // Now update membership.
        course_communication_helper::update_communication_room_membership(
            course: $course,
            userids: [$user->id],
            memberaction: 'update_room_membership',
        );

        $coursecommunication->reload();
        // Check the user is added for course communication instance.
        $courseusers = $coursecommunication->get_processor()->get_instance_userids();
        $courseusers = reset($courseusers);
        $this->assertEquals(
            expected: $user->id,
            actual: $courseusers,
        );

        // Now try using invalid action.
        $this->expectException('coding_exception');
        $this->expectExceptionMessage('Invalid action provided.');
        course_communication_helper::update_communication_room_membership(
            course: $course,
            userids: [$user->id],
            memberaction: 'a_funny_action',
        );
    }

    /**
     * Test create_course_communication_instance.
     *
     * @covers ::create_course_communication_instance
     * @covers ::get_enrolled_users_for_course
     */
    public function test_create_course_communication_instance(): void {
        $course = $this->get_course();
        $coursecontext = \context_course::instance(courseid: $course->id);
        $coursecommunication = course_communication_helper::load_by_course(
            courseid: $course->id,
            context: $coursecontext,
        );

        $processor = $coursecommunication->get_processor();
        $this->assertEquals(
            expected: 'communication_matrix',
            actual: $processor->get_provider(),
        );
        $this->assertEquals(
            expected: 'Sampleroom',
            actual: $processor->get_room_name(),
        );
    }

    /**
     * Test delete_course_communication.
     *
     * @covers ::delete_course_communication
     */
    public function test_delete_course_communication(): void {
        $course = $this->get_course();
        delete_course(
            courseorid: $course,
            showfeedback: false,
        );

        $adhoctask = \core\task\manager::get_adhoc_tasks(delete_room_task::class);
        $this->assertCount(1, $adhoctask);
    }
}
