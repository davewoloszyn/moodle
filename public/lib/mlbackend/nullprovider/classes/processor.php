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

namespace mlbackend_nullprovider;

/**
 * Null provider predictions processor.
 *
 * This provides a mlbackend provider when none are configured.
 *
 * @package   mlbackend_nullprovider
 * @copyright 2025 David Woloszyn <david.woloszyn@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class processor implements \core_analytics\predictor {

    #[\Override]
    public function is_ready(): bool {
        return true;
    }

    #[\Override]
    public function clear_model($uniqueid, $modelversionoutputdir): void {
    }

    #[\Override]
    public function delete_output_dir($modeloutputdir, $uniqueid): void {
    }
}
