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

/**
 * Class matrix_space to manage the updates to the space information in db.
 *
 * @package    communication_matrix
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matrix_space extends matrix_room_base {

    /**
     * @var string The type of the matrix room.
     */
    public const TYPE = 'space';

    public static function load_by_processor_id(
        int $processorid,
    ): ?self {
        global $DB;

        $table = self::get_table_for_record_type(self::TYPE);
        $record = $DB->get_record($table, ['commid' => $processorid]);

        if (!$record) {
            return null;
        }
        return new self($record, $table);
    }


    public static function create_room_record(
        int $processorid,
        ?string $topic,
        ?string $roomid = null,
    ): self {
        global $DB;

        $roomrecord = (object) [
            'commid' => $processorid,
            'roomid' => $roomid,
            'topic' => $topic,
        ];
        $table = self::get_table_for_record_type(self::TYPE);
        $roomrecord->id = $DB->insert_record($table, $roomrecord);

        return self::load_by_processor_id($processorid);
    }

    public function get_creation_content(): array {
        return [
            'm.federate'=> false,
            'type' => 'm.space',
        ];
    }
}
