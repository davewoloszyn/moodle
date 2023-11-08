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

namespace core_group\communication;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../communication/tests/communication_test_helper_trait.php');
require_once(__DIR__ . '/../../../communication/provider/matrix/tests/matrix_test_helper_trait.php');

use communication_matrix\matrix_test_helper_trait;
use core_communication\communication_test_helper_trait;
use core_communication\task\add_members_to_room_task;
use core_communication\task\create_and_configure_room_task;
use core_communication\task\delete_room_task;
use core_communication\task\remove_members_from_room;
use core_communication\task\update_room_task;
use core_group\communication\communication_helper as group_communication_helper;
use core_communication\processor as communication_processor;

/**
 * Test communication helper and related methods for groups.
 *
 * @package    core_group
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_group\communication\communication_helper
 */
class group_communication_helper_test extends \advanced_testcase {

    use communication_test_helper_trait;
    use matrix_test_helper_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_communication_configs();
        $this->initialise_mock_server();
    }

    /**
     * Test load_for_group_id.
     *
     * @covers ::load_for_group_id
     */
    public function test_load_for_group_id(): void {

        // As communication is created by default.
        $course = $this->get_course(
            extrafields: ['groupmode' => SEPARATEGROUPS],
        );
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $context = \context_course::instance(courseid: $course->id);

        $groupcommunication = group_communication_helper::load_for_group_id(
            groupid: $group->id,
            context: $context,
        );
        $this->assertInstanceOf(
            expected: communication_processor::class,
            actual: $groupcommunication->get_processor(),
        );
    }

    /**
     * Test create_group_communication.
     *
     * @covers ::create_group_communication
     * @covers ::update_group_communication
     * @covers ::delete_group_communication
     */
    public function test_create_update_delete_group_communication(): void {
        global $DB;

        $course = $this->get_course(
            extrafields: ['groupmode' => SEPARATEGROUPS],
        );
        $coursecontext = \context_course::instance(courseid: $course->id);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

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

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $context = \context_course::instance($course->id);

        $groupcommunication = group_communication_helper::load_for_group_id(
            groupid: $group->id,
            context: $context,
        );
        $this->assertInstanceOf(
            expected: communication_processor::class,
            actual: $groupcommunication->get_processor(),
        );

        $this->assertEquals(
            expected: $group->id,
            actual: $groupcommunication->get_processor()->get_instance_id(),
        );

        // Task to create room should be added.
        $adhoctask = \core\task\manager::get_adhoc_tasks(create_and_configure_room_task::class);
        $this->assertCount(1, $adhoctask);

        // Task to add members to room should not be there as the room is yet to be created.
        $adhoctask = \core\task\manager::get_adhoc_tasks(add_members_to_room_task::class);
        $this->assertCount(0, $adhoctask);

        // Only users with access to all groups should be added to the room at this point.
        $groupcommunicationusers = $groupcommunication->get_processor()->get_all_userids_for_instance();
        $this->assertEquals(
            expected: [$user1->id],
            actual: $groupcommunicationusers,
        );

        // Now delete all the ad-hoc tasks.
        $DB->delete_records('task_adhoc');

        // Now cann the update group but don't change the group name.
        groups_update_group($group);

        // No task should be added as nothing changed.
        $adhoctask = \core\task\manager::get_adhoc_tasks(update_room_task::class);
        $this->assertCount(0, $adhoctask);

        // Now change the group name.
        $changedgroupname = 'Changedgroupname';
        $group->name = $changedgroupname;
        groups_update_group($group);

        // Now one task should be there to update the group room name.
        $adhoctask = \core\task\manager::get_adhoc_tasks(update_room_task::class);
        $this->assertCount(1, $adhoctask);

        $this->assertEquals(
            expected: $changedgroupname,
            actual: $groupcommunication->get_processor()->get_room_name(),
        );

        // Now delete the group.
        groups_delete_group($group->id);

        $adhoctask = \core\task\manager::get_adhoc_tasks(delete_room_task::class);
        $this->assertCount(1, $adhoctask);
    }
}
