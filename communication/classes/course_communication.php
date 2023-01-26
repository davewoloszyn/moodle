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

namespace core_communication;

/**
 * Class course_communication to manage the updates to the course communication settings in db.
 *
 * @package    core_communication
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_communication {

    /**
     * @var string $provider The communication provider
     */
    private string $provider;

    /**
     * @var string $roomname The room of the room
     */
    private string $roomname;

    /**
     * @var string $roomdesc The description of the room
     */
    private string $roomdesc;

    /**
     * @var \stdClass $course The course object
     */
    private \stdClass $course;

    /**
     * @var int $enabled If the communication is enabled or not
     */
    private int $enabled;

    /**
     * @var bool $recordexist The record available or not
     */
    private bool $recordexist = false;

    /**
     * Course communication constructor to load the course communication information from course_communication table.
     *
     * @param \stdClass $course The course object
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
        if ($commrecord = $this->get_course_communication_data()) {
            $this->enabled = $commrecord->status;
            $this->provider = $commrecord->provider;
            $this->roomname = $commrecord->roomname;
            $this->roomdesc = $commrecord->roomdesc;
        } else {
            $this->roomname = $this->course->shortname;
            $this->roomdesc = $this->course->fullname;
        }
    }

    /**
     * Get the course communication data from database. Either get the data object or return false if no data found.
     *
     * @return \stdClass|bool
     */
    public function get_course_communication_data(): bool|\stdClass {
        global $DB;
        $record = $DB->get_record('course_communication', ['course' => $this->course->id]);
        if ($record) {
            $this->recordexist = true;
        }
        return $record;
    }

    /**
     * Get communication provider.
     *
     * @return string|null
     */
    public function get_provider(): ?string {
        return $this->provider;
    }

    /**
     * Get room name.
     *
     * @return string|null
     */
    public function get_roomname(): ?string {
        return $this->roomname;
    }

    /**
     * Get room description.
     *
     * @return string|null
     */
    public function get_room_description(): ?string {
        return $this->roomdesc;
    }

    /**
     * Get communication status, enabled or disabled.
     *
     * @return int
     */
    public function get_communication_status(): int {
        return $this->enabled;
    }

    /**
     * Set communication status.
     *
     * @param int $status Enable or disable communication
     * @return void
     */
    public function set_status(int $status): void {
        $this->enabled = $status;
    }

    /**
     * Set communication provider.
     *
     * @param string $provider The name of the provider
     * @return void
     */
    public function set_provider(string $provider): void {
        $this->provider = $provider;
    }

    /**
     * Set room name.
     *
     * @param string $roomname The name of the room
     * @return void
     */
    public function set_roomname(string $roomname): void {
        $this->roomname = $roomname;
    }

    /**
     * Set room description.
     *
     * @param string $roomdescription The description of the room
     * @return void
     */
    public function set_room_description(string $roomdescription): void {
        $this->roomdesc = $roomdescription;
    }

    /**
     * Create coruse communication data.
     *
     * @return void
     */
    public function create(): void {
        global $DB;
        $commrecord = new \stdClass();
        $commrecord->course = $this->course->id;
        $commrecord->status = $this->enabled;
        $commrecord->provider = $this->provider;
        $commrecord->roomname = $this->roomname;
        $commrecord->roomdesc = $this->roomdesc;
        $DB->insert_record('course_communication', $commrecord);
    }

    /**
     * Update course communication data.
     *
     * @return void
     */
    public function update(): void {
        global $DB;
        if ($commrecord = $this->get_course_communication_data()) {
            $commrecord->status = $this->enabled;
            $commrecord->provider = $this->provider;
            $commrecord->roomname = $this->roomname;
            $commrecord->roomdesc = $this->roomdesc;
            $DB->update_record('course_communication', $commrecord);
        }
    }

    /**
     * Delete course communication data.
     *
     * @return void
     */
    public function delete(): void {
        global $DB;
        $DB->delete_records('course_communication', ['course' => $this->course->id]);
    }

    /**
     * Check if the record for course communication exist or not.
     *
     * @return bool
     */
    public function record_exist(): bool {
        return $this->recordexist;
    }

}
