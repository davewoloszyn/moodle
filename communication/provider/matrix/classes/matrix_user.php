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

use core_communication\communication_user_base;

/**
 * Class matrix_user to manage matrix provider users.
 *
 * @package    communication_matrix
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matrix_user extends communication_user_base {

    /**
     * @var matrix_events_manager $eventmanager The event manager object to get the endpoints
     */
    private matrix_events_manager $eventmanager;

    /**
     * @var matrix_rooms $matrixrooms The matrix room object to update room information
     */
    private matrix_rooms $matrixrooms;

    protected function init(): void {
        $this->matrixrooms = new matrix_rooms($this->communication->communicationsettings->get_communication_instance_id());
        $this->eventmanager = new matrix_events_manager($this->matrixrooms->roomid);
    }

    /**
     * Create members.
     *
     * @param array $userids The Moodle user ids to create
     * @return void
     */
    public function create_members(array $userids): void {
        // TODO: MDL-76708 implement the calls for member creation.
    }

    /**
     * Add members to a room if they qualify, or create them and then add them.
     *
     * @param array $userids The Moodle user ids to add
     * @return void
     */
    public function add_members_to_room(array $userids): void {
        $unregisteredusers = [];
        foreach ($userids as $userid) {
            // Check if Matrix user exists in Moodle and or in Matrix.
            $matrixuserid = '@user:synapse'; // TODO: MDL-76708
            if (!$matrixuserid || !$this->check_user_exists($matrixuserid)) {
                $unregisteredusers[] = $userid;
                if (!$matrixuserid) {
                    $this->add_user_matrix_id_to_moodle($userid);
                }
            } else {
                $this->add_user_to_matrix_room($matrixuserid);
            }
        }
        // Create Matrix users.
        if (count($unregisteredusers) > 0) {
            $this->create_members($unregisteredusers);
        }
    }

    /**
     * Add a new or existed Matrix user to room.
     *
     * @param string $matrixuserid New or existed Matrix user id
     * @return void
     */
    private function add_user_to_matrix_room(string $matrixuserid): void {
        // Check user isn't a member already.
        if (!$this->check_room_membership($matrixuserid)) {
            $json = ['user_id' => $matrixuserid];
            $headers = ['Content-Type' => 'application/json'];
            $this->eventmanager->request($json, $headers)->post($this->eventmanager->get_room_membership_join_endpoint());
        }
    }

    /**
     * Add user's Matrix user id.
     *
     * @param string $userid Moodle user id
     * @return void
     */
    private function add_user_matrix_id_to_moodle(string $userid): void {
        // TODO: MDL-76708
    }

    /**
     * Remove members from a room.
     *
     * @param array $userids The Moodle user ids to remove
     * @return void
     */
    public function remove_members_from_room(array $userids): void {
        foreach ($userids as $userid) {
            $matrixuserid = '@user:synapse'; // TODO: MDL-76708
            // Check user is member of room first.
            if ($matrixuserid && $this->check_room_membership($matrixuserid)) {
                $json = ['user_id' => $matrixuserid];
                $headers = ['Content-Type' => 'application/json'];
                $this->eventmanager->request($json, $headers)->post($this->eventmanager->get_room_membership_kick_endpoint());
            }
        }
    }

    /**
     * Check if a user exists in Matrix.
     * Use if user existence is needed before doing something else.
     *
     * @param string $matrixuserid The Matrix user id to check
     * @return bool
     */
    public function check_user_exists(string $matrixuserid): bool {
        $response = $this->eventmanager->request([], [], false)->get($this->eventmanager->get_user_info_endpoint($matrixuserid));
        if ($response->getStatusCode() === 200) {
            $response = json_decode($response->getBody());
            // Check user displayname is returned for user.
            if (isset($response->displayname)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a user is a member of a room.
     * Use if membership confirmation is needed before doing something else.
     *
     * @param string $matrixuserid The Matrix user id to check
     * @return array
     */
    public function check_room_membership(string $matrixuserid): bool {
        $response = $this->eventmanager->request([], [], false)->get($this->eventmanager->get_room_membership_joined_endpoint());
        // Only valid status codes.
        if ($response->getStatusCode() === 200) {
            $response = json_decode($response->getBody(), true);
            // Check user id is in the returned room member ids.
            if (isset($response['joined']) && in_array($matrixuserid, array_keys($response['joined']))) {
                return true;
            }
        }
        return false;
    }
}
