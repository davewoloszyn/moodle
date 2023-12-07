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

use core_communication\api;
use core_communication\processor;
use core_communication\room_user_provider;
use core_group\communication\communication_helper as groupcommunication_helper;
use stdClass;

/**
 * Class communication helper to help with communication related tasks for course.
 *
 * This class mainly handles the communication actions for different setup in course as well as helps to reduce duplication.
 *
 * @package    core_course
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class communication_helper {

    /** @var string COURSE_COMMUNICATION_INSTANCETYPE The course communication instance type. */
    public const COURSE_COMMUNICATION_INSTANCETYPE = 'coursecommunication';

    /** @var string COURSE_COMMUNICATION_COMPONENT The course communication component. */
    public const COURSE_COMMUNICATION_COMPONENT = 'core_course';

    /**
     * Load the communication instance for course id.
     *
     * @param int $courseid The course id
     * @param \context $context The context
     * @param string|null $provider The provider name
     * @return api The communication instance
     */
    public static function load_by_course(
        int $courseid,
        \context $context,
        ?string $provider = null,
    ): api {
        return \core_communication\api::load_by_instance(
            context: $context,
            component: self::COURSE_COMMUNICATION_COMPONENT,
            instancetype: self::COURSE_COMMUNICATION_INSTANCETYPE,
            instanceid: $courseid,
            provider: $provider,
        );
    }

    /**
     * Update course communication according to course data.
     * Course can have course or group rooms. Group mode enabling will create rooms for groups.
     *
     * @param stdClass $course The course data
     * @param stdClass $oldcourse The old course data before the update
     * @param bool $changesincoursecat Whether the course moved to a different category
     */
    public static function update_course_communication(
        stdClass $course,
        stdClass $oldcourse,
        bool $changesincoursecat
    ): void {
        // If the communication subsystem is not enabled then just ignore.
        if (!api::is_available()) {
            return;
        }

        // Check if provider is selected.
        $provider = $course->selectedcommunication ?? null;
        // If the course moved to hidden category, set provider to none.
        if ($changesincoursecat && empty($course->visible)) {
            $provider = processor::PROVIDER_NONE;
        }

        // Get the course context.
        $coursecontext = \context_course::instance(courseid: $course->id);
        // Get the course image.
        $courseimage = course_get_courseimage(course: $course);
        // Get the course communication instance.
        $coursecommunication = self::load_by_course(
            courseid: $course->id,
            context: $coursecontext,
        );

        // Attempt to get the communication provider if it wasn't provided in the data.
        if (empty($provider)) {
            $provider = $coursecommunication->get_provider();
        }

        // This nasty logic is here because of hide course doesn't pass anything in the data object.
        if (!empty($course->communicationroomname)) {
            $coursecommunicationroomname = $course->communicationroomname;
        } else {
            $coursecommunicationroomname = $course->fullname ?? $oldcourse->fullname;
        }

        // List of enrolled users for course communication.
        $enrolledusers = self::get_enrolled_users_for_course(course: $course);

        // Check for group mode, we will have to get the course data again as the group info is not always in the object.
        $groupmode = $course->groupmode ?? get_course(courseid: $course->id)->groupmode;

        // If group mode is disabled, get the communication information for creating room for a course.
        if ((int)$groupmode === NOGROUPS) {
            // Remove all the members from active group rooms if there is any.
            $coursegroups = groups_get_all_groups(courseid: $course->id);
            foreach ($coursegroups as $coursegroup) {
                $communication = groupcommunication_helper::load_by_group(
                    groupid: $coursegroup->id,
                    context: $coursecontext,
                );
                // Remove the members from the group room.
                $communication->remove_all_members_from_room();
                // Now delete the group room.
                $communication->update_room(active: processor::PROVIDER_INACTIVE);
            }

            // Now create/update the course room.
            $communication = self::load_by_course(
                courseid: $course->id,
                context: $coursecontext,
            );
            $communication->configure_room_and_membership_by_provider(
                provider: $provider,
                instance: $course,
                communicationroomname: $coursecommunicationroomname,
                users: $enrolledusers,
                instanceimage: $courseimage,
            );
        } else {
            // Update the group communication instances.
            self::update_group_communication_instances(
                course: $course,
                provider: $provider,
            );

            // Remove all the members for the course room if instance available.
            $communication = self::load_by_course(
                courseid: $course->id,
                context: $coursecontext,
            );
            $communication->remove_all_members_from_room();
            // Now update the course communication instance with the latest changes.
            // We are not making room for this instance as it is a group mode enabled course.
            // If provider is none, then we will make the room inactive, otherwise always active in group mode.
            $communication->update_room(
                active: $provider === processor::PROVIDER_NONE ? processor::PROVIDER_INACTIVE : processor::PROVIDER_ACTIVE,
                communicationroomname: $coursecommunicationroomname,
                avatar: $courseimage,
                instance: $course,
                queue: false,
            );
        }
    }

    /**
     * Get users with the capability to access all groups.
     *
     * @param array $userids user ids to check the permission
     * @param int $courseid course id
     * @return array of userids
     */
    public static function get_users_has_access_to_all_groups(
        array $userids,
        int $courseid
    ): array {
        $allgroupsusers = [];
        $context = \context_course::instance(courseid: $courseid);

        foreach ($userids as $userid) {
            if (
                has_capability(
                    capability: 'moodle/site:accessallgroups',
                    context: $context,
                    user: $userid,
                )
            ) {
                $allgroupsusers[] = $userid;
            }
        }

        return $allgroupsusers;
    }

    /**
     * Helper to update room membership according to action passed.
     * This method will help reduce a large amount of duplications of code in different places in core.
     *
     * @param \stdClass $course The course object.
     * @param array $userids The user ids to add to the communication room.
     * @param string $memberaction The action to perform on the communication room.
     */
    public static function update_communication_room_membership(
        \stdClass $course,
        array $userids,
        string $memberaction,
    ): void {
        // If the communication subsystem is not enabled then just ignore.
        if (!api::is_available()) {
            return;
        }

        // Validate communication api action.
        $roomuserprovider = new \ReflectionClass(room_user_provider::class);
        if (!$roomuserprovider->hasMethod($memberaction)) {
            throw new \coding_exception('Invalid action provided.');
        }

        // Get the group mode for this course.
        $groupmode = $course->groupmode ?? get_course(courseid: $course->id)->groupmode;
        $coursecontext = \context_course::instance(courseid: $course->id);

        // If group mode is not set then just handle the course communication for these users.
        if ((int)$groupmode === NOGROUPS) {
            $communication = self::load_by_course(
                courseid: $course->id,
                context: $coursecontext,
            );
            $communication->$memberaction($userids);
        } else {
            // If group mode is set then handle the group communication rooms for these users.
            $coursegroups = groups_get_all_groups(courseid: $course->id);

            $userhandled = [];

            foreach ($coursegroups as $coursegroup) {
                // Get the group user who need to be handled and also a member of the group.
                $groupuserstohandle = array_intersect(
                    array_map(
                        static fn($user) => $user->id,
                        groups_get_members(groupid: $coursegroup->id),
                    ),
                    $userids,
                );

                // Add the users not in the group but have the capability to access all groups.
                $allaccessgroupusers = self::get_users_has_access_to_all_groups(
                    userids: $userids,
                    courseid: $course->id,
                );
                foreach ($allaccessgroupusers as $allaccessgroupuser) {
                    if (!in_array($allaccessgroupuser, $groupuserstohandle, true)) {
                        $groupuserstohandle[] = $allaccessgroupuser;
                    }
                }

                $userhandled = array_merge($userhandled, $groupuserstohandle);

                $communication = groupcommunication_helper::load_by_group(
                    groupid: $coursegroup->id,
                    context: $coursecontext,
                );
                $communication->$memberaction($groupuserstohandle);
            }

            // If the user was not in any group but an update/remove action requested for the user.
            // Then the user had a role with access all groups cap, but made a regular user, so we need to handle the user.
            $usersnothandled = array_diff($userids, $userhandled);
            // These users are not handled and not in any group, so logically these users lost their permission to stay in a room.
            // So we need to remove them from the room.
            foreach ($coursegroups as $coursegroup) {
                $communication = groupcommunication_helper::load_by_group(
                    groupid: $coursegroup->id,
                    context: $coursecontext,
                );
                $communication->remove_members_from_room(userids: $usersnothandled);
            }
        }
    }

    /**
     * Get the course communication status notification for course.
     *
     * @param \stdClass $course The course object.
     */
    public static function get_course_communication_status_notification(\stdClass $course): void {
        // If the communication subsystem is not enabled then just ignore.
        if (!api::is_available()) {
            return;
        }

        // Get the group mode for this course.
        $groupmode = $course->groupmode ?? get_course(courseid: $course->id)->groupmode;
        $coursecontext = \context_course::instance(courseid: $course->id);

        // If group mode is not set then just handle the course communication for these users.
        if ((int)$groupmode === NOGROUPS) {
            $communication = self::load_by_course(
                courseid: $course->id,
                context: $coursecontext,
            );
            $communication->show_communication_room_status_notification();
        } else {
            // If group mode is set then handle the group communication rooms for these users.
            $coursegroups = groups_get_all_groups(courseid: $course->id);
            $numberofgroups = count($coursegroups);

            // If no groups available, nothing to show.
            if ($numberofgroups === 0) {
                return;
            }

            $numberofreadygroups = 0;

            foreach ($coursegroups as $coursegroup) {
                $communication = groupcommunication_helper::load_by_group(
                    groupid: $coursegroup->id,
                    context: $coursecontext,
                );
                $roomstatus = $communication->get_communication_room_url() ? 'ready' : 'pending';
                switch ($roomstatus) {
                    case 'ready':
                        $numberofreadygroups ++;
                        break;
                    case 'pending':
                        $pendincommunicationobject = $communication;
                        break;
                }
            }

            if ($numberofgroups === $numberofreadygroups) {
                $communication->show_communication_room_status_notification();
            } else {
                $pendincommunicationobject->show_communication_room_status_notification();
            }
        }
    }

    /**
     * Delete course communication data and remove members.
     * Course can have communication data if it is a group or a course.
     * This action is important to perform even if the experimental feature is disabled.
     *
     * @param stdclass $course The course object.
     */
    public static function delete_course_communication(stdclass $course): void {
        $groupmode = $course->groupmode ?? get_course(courseid: $course->id)->groupmode;
        $coursecontext = \context_course::instance(courseid: $course->id);

        // If group mode is not set then just handle the course communication room.
        if ((int)$groupmode === NOGROUPS) {
            $communication = self::load_by_course(
                courseid: $course->id,
                context: $coursecontext,
            );
            $communication->delete_room();
        } else {
            // If group mode is set then handle the group communication rooms.
            $coursegroups = groups_get_all_groups(courseid: $course->id);
            foreach ($coursegroups as $coursegroup) {
                $communication = \core_group\communication\communication_helper::load_by_group(
                    groupid: $coursegroup->id,
                    context: $coursecontext,
                );
                $communication->delete_room();
            }
        }
    }

    /**
     * Create course communication instance.
     *
     * @param stdClass $course The course object.
     */
    public static function create_course_communication_instance(stdClass $course): void {
        // If the communication subsystem is not enabled then just ignore.
        if (!api::is_available()) {
            return;
        }

        // Check for default provider config setting.
        $defaultprovider = get_config(
            plugin: 'moodlecourse',
            name: 'coursecommunicationprovider',
        );
        $provider = $course->selectedcommunication ?? $defaultprovider;

        if (empty($provider) && $provider === processor::PROVIDER_NONE) {
            return;
        }

        // Check for group mode, we will have to get the course data again as the group info is not always in the object.
        $createcourseroom = true;
        $creategrouprooms = false;
        $coursedata = get_course(courseid: $course->id);
        $groupmode = $course->groupmode ?? $coursedata->groupmode;
        if ((int)$groupmode !== NOGROUPS) {
            $createcourseroom = false;
            $creategrouprooms = true;
        }

        // Prepare the communication api data.
        $courseimage = course_get_courseimage(course: $course);
        $communicationroomname = !empty($course->communicationroomname) ? $course->communicationroomname : $coursedata->fullname;
        $coursecontext = \context_course::instance(courseid: $course->id);
        // Communication api call for course communication.
        $communication = \core_communication\api::load_by_instance(
            context: $coursecontext,
            component: self::COURSE_COMMUNICATION_COMPONENT,
            instancetype: self::COURSE_COMMUNICATION_INSTANCETYPE,
            instanceid: $course->id,
            provider: $provider,
        );
        $communication->create_and_configure_room(
            communicationroomname: $communicationroomname,
            avatar: $courseimage,
            instance: $course,
            queue: $createcourseroom,
        );

        // Communication api call for group communication.
        if ($creategrouprooms) {
            self::update_group_communication_instances(
                course: $course,
                provider: $provider,
            );
        } else {
            $enrolledusers = self::get_enrolled_users_for_course(course: $course);
            $communication->add_members_to_room(
                userids: $enrolledusers,
                queue: false,
            );
        }
    }

    /**
     * Update the group communication instances.
     *
     * @param stdClass $course The course object.
     * @param string $provider The provider name.
     */
    public static function update_group_communication_instances(
        stdClass $course,
        string $provider,
    ): void {
        $coursegroups = groups_get_all_groups(courseid: $course->id);
        $coursecontext = \context_course::instance(courseid: $course->id);
        $allaccessgroupusers = self::get_users_has_access_to_all_groups(
            userids: self::get_enrolled_users_for_course(course: $course),
            courseid: $course->id,
        );

        foreach ($coursegroups as $coursegroup) {
            $groupuserstoadd = array_column(
                groups_get_members(groupid: $coursegroup->id),
                'id',
            );

            foreach ($allaccessgroupusers as $allaccessgroupuser) {
                if (!in_array($allaccessgroupuser, $groupuserstoadd, true)) {
                    $groupuserstoadd[] = $allaccessgroupuser;
                }
            }

            // Now create/update the group room.
            $communication = groupcommunication_helper::load_by_group(
                groupid: $coursegroup->id,
                context: $coursecontext,
            );
            $communication->configure_room_and_membership_by_provider(
                provider: $provider,
                instance: $course,
                communicationroomname: $coursegroup->name,
                users: $groupuserstoadd,
            );
        }
    }

    /**
     * Get the enrolled users for course.
     *
     * @param stdClass $course The course object.
     * @return array
     */
    public static function get_enrolled_users_for_course(stdClass $course): array {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        return array_column(
            enrol_get_course_users(courseid: $course->id),
            'id',
        );
    }

    /**
     * Is group mode enabled for the course.
     *
     * @param stdClass $course The course object
     */
    public static function is_group_mode_enabled(stdClass $course): bool {
        $groupmode = $course->groupmode ?? get_course(courseid: $course->id)->groupmode;
        return (int)$groupmode !== NOGROUPS;
    }

    /**
     * Get the course communication url according to course setup.
     *
     * @param stdClass $course The course object.
     * @return string The communication room url.
     */
    public static function get_course_communication_url(stdClass $course): string {
        // If it's called from site context, then just return.
        if ($course->id === SITEID) {
            return '';
        }

        // If the communication subsystem is not enabled then just ignore.
        if (!api::is_available()) {
            return '';
        }

        $url = '';
        // Get the group mode for this course.
        $groupmode = $course->groupmode ?? get_course(courseid: $course->id)->groupmode;
        $coursecontext = \context_course::instance(courseid: $course->id);

        // If group mode is not set then just handle the course communication for these users.
        if ((int)$groupmode === NOGROUPS) {
            $communication = self::load_by_course(
                courseid: $course->id,
                context: $coursecontext,
            );
            $url = $communication->get_communication_room_url();
        } else {
            // If group mode is set then handle the group communication rooms for these users.
            $coursegroups = groups_get_all_groups(courseid: $course->id);
            $numberofgroups = count($coursegroups);

            // If no groups available, nothing to show.
            if ($numberofgroups === 0) {
                return '';
            }

            $readygroups = [];

            foreach ($coursegroups as $coursegroup) {
                $communication = groupcommunication_helper::load_by_group(
                    groupid: $coursegroup->id,
                    context: $coursecontext,
                );
                $roomstatus = $communication->get_communication_room_url() ? 'ready' : 'pending';
                if ($roomstatus === 'ready') {
                    $readygroups[$communication->get_processor()->get_id()] = $communication->get_communication_room_url();
                }
            }
            if (!empty($readygroups)) {
                $highestkey = max(array_keys($readygroups));
                $url = $readygroups[$highestkey];
            }
        }

        return $url;
    }

}
