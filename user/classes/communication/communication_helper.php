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

namespace core_user\communication;

use stdClass;
use core_communication\api;
use core_course\communication\communication_helper as course_communication_helper;

/**
 * Class communication helper to help with communication related tasks for the users.
 *
 * This class mainly handles the communication actions for different changes for users as well as helps to reduce duplication.
 *
 * @package    core_user
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class communication_helper {

    /**
     * Update the room membership for the user updates.
     *
     * @param stdClass $user The user object.
     */
    public static function update_user_room_memberships(stdClass $user): void {
        if (!api::is_available()) {
            return;
        }

        // Get the user courses.
        $usercourses = enrol_get_users_courses(userid: $user->id);
        // Get the current record for compare the changes before triggering any action.
        $currentrecord = user_get_users_by_id(userids: [$user->id]);
        $currentrecord = reset($currentrecord);

        // If the user is suspended then remove the user from all the rooms.
        // Otherwise add the user to all the rooms for the courses the user enrolled in.
        if (!empty($currentrecord) && isset($user->suspended) && $currentrecord->suspended !== $user->suspended) {
            // Decide the action for the communication api for the user.
            $memberaction = ($user->suspended === 0) ? 'add_members_to_room' : 'remove_members_from_room';

            foreach ($usercourses as $usercourse) {
                course_communication_helper::update_communication_room_membership(
                    course: $usercourse,
                    userids: [$user->id],
                    memberaction: $memberaction,
                );
            }
        }
    }

    /**
     * Delete all room memberships for a user.
     *
     * @param stdClass $user The user object.
     */
    public static function delete_user_room_memberships(stdClass $user): void {
        if (!api::is_available()) {
            return;
        }

        foreach (enrol_get_users_courses(userid: $user->id) as $course) {
            $groupmode = $course->groupmode ?? get_course(courseid: $course->id)->groupmode;
            $coursecontext = \context_course::instance(courseid: $course->id);

            if ((int)$groupmode === NOGROUPS) {
                $communication = \core_course\communication\communication_helper::load_by_course(
                    courseid: $course->id,
                    context: $coursecontext,
                );
                $communication->get_room_user_provider()->remove_members_from_room(userids: [$user->id]);
                $communication->get_processor()->delete_instance_user_mapping(userids: [$user->id]);
            } else {
                // If group mode is set then handle the group communication rooms.
                $coursegroups = groups_get_all_groups(courseid: $course->id);
                foreach ($coursegroups as $coursegroup) {
                    $communication = \core_group\communication\communication_helper::load_by_group(
                        groupid: $coursegroup->id,
                        context: $coursecontext,
                    );
                    $communication->get_room_user_provider()->remove_members_from_room(userids: [$user->id]);
                    $communication->get_processor()->delete_instance_user_mapping(userids: [$user->id]);
                }
            }
        }
    }
}
