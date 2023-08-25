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

use stdClass;

/**
 * Class matrix_room_base is the base class for matrix_room and matrix_space.
 *
 * @package    communication_matrix
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class matrix_room_base {

    protected function __construct(
        protected stdClass $record,
        protected string $table,
    ) {
    }

    public function update_room_record(
        ?string $roomid = null,
        ?string $topic = null,
    ): void {
        global $DB;

        if ($roomid !== null) {
            $this->record->roomid = $roomid;
        }

        if ($topic !== null) {
            $this->record->topic = $topic;
        }

        $DB->update_record($this->table, $this->record);
    }

    public function delete_room_record(): void {
        global $DB;
        $DB->delete_records($this->table, ['commid' => $this->record->commid]);

        unset($this->record);
    }

    public function get_id(): int {
        return $this->record->id;
    }

    public function get_processor_id(): int {
        return $this->record->commid;
    }

    public function get_room_id(): ?string {
        return $this->record->roomid;
    }

    public function get_topic(): ?string {
        return $this->record->topic;
    }
}
