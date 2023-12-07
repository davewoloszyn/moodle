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

use context_course;
use core_communication\api;
use core_communication\processor;
use core_course\communication\communication_helper as course_communication_helper;
use context;
use stdClass;

/**
 * Class communication helper to help with communication related tasks for groups.
 *
 * This class mainly handles the communication actions for different setup in groups as well as helps to reduce duplication.
 *
 * @package    core_group
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class communication_helper {

    /** @var string GROUP_COMMUNICATION_INSTANCETYPE The group communication instance type. */
    public const GROUP_COMMUNICATION_INSTANCETYPE = 'groupcommunication';

    /** @var string GROUP_COMMUNICATION_COMPONENT The group communication component. */
    public const GROUP_COMMUNICATION_COMPONENT = 'core_group';

    /**
     * Load the communication instance for group id.
     *
     * @param int $groupid The group id
     * @param context $context The context, to make sure any instance using group can load the communication instance
     * @return api The communication instance.
     */
    public static function load_by_group(int $groupid, context $context): api {
        return \core_communication\api::load_by_instance(
            context: $context,
            component: self::GROUP_COMMUNICATION_COMPONENT,
            instancetype: self::GROUP_COMMUNICATION_INSTANCETYPE,
            instanceid: $groupid,
        );
    }

    /**
     * Communication api call to create room for a group if course has group mode enabled.
     *
     * @param stdClass $course The course object
     * @param stdClass $group The group object
     */
    public static function create_group_communication(
        stdClass $course,
        stdclass $group,
    ): void {
        // If the communication subsystem is not enabled then just ignore.
        if (!api::is_available()) {
            return;
        }

        // Check if group mode enabled before handling the communication.
        if (!course_communication_helper::is_group_mode_enabled(course: $course)) {
            return;
        }

        $coursecontext = \context_course::instance(courseid: $course->id);
        // Get the course communication instance to set the provider.
        $coursecommunication = course_communication_helper::load_by_course(
            courseid: $course->id,
            context: $coursecontext,
        );

        $communication = api::load_by_instance(
            context: $coursecontext,
            component: self::GROUP_COMMUNICATION_COMPONENT,
            instancetype: self::GROUP_COMMUNICATION_INSTANCETYPE,
            instanceid: $group->id,
            provider: $coursecommunication->get_provider(),
        );

        $communication->create_and_configure_room(
            communicationroomname: $group->name,
            instance: $course,
        );

        // As it's a new group, we need to add the users with all access group role to the room.
        $enrolledusers = course_communication_helper::get_enrolled_users_for_course(course: $course);
        $userstoadd = course_communication_helper::get_users_has_access_to_all_groups(
            userids: $enrolledusers,
            courseid: $course->id,
        );
        $communication->add_members_to_room(
            userids: $userstoadd,
            queue: false,
        );
    }

    /**
     * Communication api call to update room for a group if course has group mode enabled.
     *
     * @param stdClass $course The course object
     * @param stdclass $group The group object
     * @param stdclass $oldgroup The old group object
     */
    public static function update_group_communication(
        stdClass $course,
        stdclass $group,
        stdClass $oldgroup,
    ): void {
        // If the communication subsystem is not enabled then just ignore.
        if (!api::is_available()) {
            return;
        }

        // Check if group mode enabled before handling the communication.
        if (!course_communication_helper::is_group_mode_enabled($course)) {
            return;
        }

        // If the name didn't change, then we don't need to update the room.
        if ($group->name === $oldgroup->name) {
            return;
        }

        $coursecontext = \context_course::instance(courseid: $course->id);
        $communication = self::load_by_group(
            groupid: $group->id,
            context: $coursecontext,
        );

        $communication->update_room(
            active: processor::PROVIDER_ACTIVE,
            communicationroomname: $group->name,
            instance: $course,
        );
    }

    /**
     * Delete the communication room for a group if course has group mode enabled.
     *
     * @param stdClass $course The course object
     * @param stdClass $group The group object
     */
    public static function delete_group_communication(
        stdClass $course,
        stdClass $group,
    ): void {
        // If the communication subsystem is not enabled then just ignore.
        if (!api::is_available()) {
            return;
        }

        // Check if group mode enabled before handling the communication.
        if (!course_communication_helper::is_group_mode_enabled($course)) {
            return;
        }

        $context = context_course::instance($course->id);
        $communication = self::load_by_group(
            groupid: $group->id,
            context: $context,
        );
        $communication->delete_room();
    }
}
