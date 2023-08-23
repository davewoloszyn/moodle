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
 * Class matrix_room_base is the base class for matrix related records for spaces and rooms.
 *
 * @package    communication_matrix
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matrix_room_base {
    /** @var \stdClass|null $record The matrix room record from db */
    /** @var string $table The matrix table to get the record from db */

    /**
     * @var string The type of the matrix room.
     */
    public const TYPE = 'room';

    /**
     * Load the matrix room record for the supplied processor.
     * @param int $processorid
     * @return null|self
     */
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

    /**
     * Matrix rooms constructor to load the matrix room information from matrix_room table.
     *
     * @param stdClass $record
     */
    protected function __construct(
        protected stdClass $record,
        protected string $table,
    ) {
    }

    /**
     * Create matrix room data.
     *
     * @param int $processorid The id of the communication record
     * @param string|null $topic The topic of the room for matrix
     * @param string|null $roomid The id of the room from matrix
     * @return self
     */
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

    /**
     * Update matrix room data.
     *
     * @param string|null $roomid The id of the room from matrix
     * @param string|null $topic The topic of the room for matrix
     */
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

    /**
     * Delete matrix room data.
     */
    public function delete_room_record(): void {
        global $DB;
        $DB->delete_records($this->table, ['commid' => $this->record->commid]);

        unset($this->record);
    }

    /**
     * Get the id of the matrix room record.
     *
     * @return int The id of the matrix room record
     */
    public function get_id(): int {
        return $this->record->id;
    }

    /**
     * Get the processor id.
     *
     * @return int
     */
    public function get_processor_id(): int {
        return $this->record->commid;
    }

    /**
     * Get the matrix room id.
     *
     * @return string|null
     */
    public function get_room_id(): ?string {
        return $this->record->roomid;
    }

    /**
     * Get the matrix room topic.
     *
     * @return string|null
     */
    public function get_topic(): ?string {
        return $this->record->topic;
    }

    /**
     * Get the matrix room creation content.
     * @return array
     */
    public function get_creation_content(): array {
        return [];
    }

    /**
     * Get the table name for the type of record supplied.
     *
     * @param string $type The type of the record
     * @return string The table name for the supplied type
     */
    public static function get_table_for_record_type(string $type): string {
        if ($type === 'room') {
            return 'matrix_room';
        } else if ($type === 'space') {
            return 'matrix_space';
        }

        throw new \Exception('Invalid record type supplied');
    }
}
