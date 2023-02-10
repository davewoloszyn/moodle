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

use core_communication\communication_room_base;
use core_course\external\course_summary_exporter;

/**
 * Class communication_room to manage the room operation of matrix provider.
 *
 * @package    communication_matrix
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class communication_room extends communication_room_base {

    /**
     * @var matrix_events_manager $eventmanager The event manager object to get the endpoints
     */
    private matrix_events_manager $eventmanager;

    /**
     * @var matrix_rooms $matrixrooms The matrix room object to update room information
     */
    private matrix_rooms $matrixrooms;

    protected function init(): void {
        $this->matrixrooms = new matrix_rooms($this->communication->get_course()->id);
        $this->eventmanager = new matrix_events_manager($this->matrixrooms->get_roomid());
    }

    public function create(): void {
        $json = [
            'name' => $this->communication->get_room_name(),
            'topic' => $this->communication->get_room_description(),
            'visibility' => 'public',
            'preset' => 'public_chat',
            'room_alias_name' => str_replace(' ', '', $this->communication->get_room_name()),
            'initial_state' => [],
        ];

        $response = $this->eventmanager->request($json)->post($this->eventmanager->get_create_room_endpoint());
        $response = json_decode($response->getBody());

        if (!empty($roomid = $response->room_id) && !empty($alias = $response->room_alias)) {
            $this->matrixrooms->set_roomid($roomid);
            $this->matrixrooms->set_room_alias($alias);
            $this->matrixrooms->create();
        } else {
            throw new \coding_exception('Can not create record without room id and room alias');
        }

        $this->update_room_avatar();
    }

    public function delete(): void {
        if ($this->matrixrooms->is_room_data_available()) {
            $json = [
                'block' => true,
                'purge' => true,
            ];

            $this->eventmanager->request($json)->delete($this->eventmanager->get_delete_room_endpoint());
            $this->matrixrooms->delete();
        }
    }

    public function status(bool $enable = true): void {
        if ($this->matrixrooms->is_room_data_available()) {
            $json = [
                'block' => $enable,
                'purge' => false,
            ];

            $this->eventmanager->request($json)->delete($this->eventmanager->get_delete_room_endpoint());
        }
    }

    public function update(): void {
        if ($this->matrixrooms->is_room_data_available()) {
            // Update the room name first.
            $this->update_room_name();
            // Now update room topic.
            $this->update_room_topic();
            // Update room avatar.
            $this->update_room_avatar();
            // TODO check if we need to update local matrix rooms record.
        }
    }

    /**
     * Update the matrix room name when a course shortname is changed.
     *
     * @return void
     */
    public function update_room_name(): void {
        $json = ['name' => $this->communication->get_room_name(),];
        $this->eventmanager->request($json)->put($this->eventmanager->get_update_room_name_endpoint());
    }

    /**
     * Update the room topic when a course fullname is changed.
     *
     * @return void
     */
    public function update_room_topic(): void {
        $json = ['topic' => $this->communication->get_room_description(),];
        $this->eventmanager->request($json)->put($this->eventmanager->get_update_room_topic_endpoint());
    }

    /**
     * Update the room avatar when a course image is added or updated.
     *
     * @return void
     */
    public function update_room_avatar(): void {
        // TODO find a way to only update when a course image is changed.
        $courseimage = course_summary_exporter::get_course_image($this->communication->get_course());

        if ($courseimage) {
            $courseimage = file_get_contents($courseimage);
            // First upload the content.
            $contenturi = $this->eventmanager->upload_matrix_content($courseimage);
            if ($contenturi) {
                // Now update the room avatar.
                $json = [
                    'url' => $contenturi,
                ];
                $this->eventmanager->request($json)->put($this->eventmanager->get_update_avatar_endpoint());
            }
        }
    }

    /**
     * Generate a link to the room.
     *
     * @return \moodle_url
     */
    public function generate_room_link(): \moodle_url {
        $baseurl = $this->eventmanager->get_web_client_url();
        $roomalias = $this->matrixrooms->get_room_alias();

        $url = new \moodle_url($baseurl . '#/room/' . $roomalias);
        return $url;
    }
}
