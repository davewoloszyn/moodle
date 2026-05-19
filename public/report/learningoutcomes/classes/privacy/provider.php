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

/**
 * Privacy Subsystem implementation for report_learningoutcomes.
 *
 * The learning outcomes alignment report displays course-content data only
 * (outcome names and activity tags). It does not store, process, or export
 * any personal data about individual users.
 *
 * @package    report_learningoutcomes
 * @copyright  2026 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_learningoutcomes\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for report_learningoutcomes implementing null_provider.
 *
 * @copyright  2026 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Get the language string identifier with the component's language
     * file to explain why this plugin stores no data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
