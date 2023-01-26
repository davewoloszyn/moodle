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

namespace core_communication\tests;

/**
 * Trait matrix_helper_trait to manage methods to generate initial setup for matrix tests.
 *
 * @package    communication_matrix
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait matrix_helper_trait {

    /** @var \testing_data_generator|null */
    protected $generator = null;

    /** @var object|null */
    protected $course = null;

    /**
     * Get or create course if it does not exist
     *
     * @return \stdClass|null
     */
    protected function get_course($records = []): \stdClass {
        $record = [
            'enablecommunication' => 1,
            'seleccommunicationprovider' => 'communication_matrix',
        ];
        $records = array_merge($record, $records);
        return $this->getDataGenerator()->create_course($records);
    }


}
