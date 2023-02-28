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
 * class matrix_user_manager to handle specific actions.
 *
 * @package    communication_matrix
 * @copyright  2023 Stevani Andolo <stevani.andolo@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matrix_user_manager {

    /**
     * Gets matrix user id from moodle.
     *
     * @param string $userid
     * @return string|null
     */
    public static function get_matrixid_from_moodle(string $userid) : string|null {
        $matrixprofilefield = get_config('communication_matrix', 'profile_field_name');
        $matrixuserid = null;
        $fields = profile_get_user_fields_with_data($userid);
        foreach ($fields as $field) {
            if ($field->field->shortname === $matrixprofilefield) {
                $matrixuserid = $field->data;
                break;
            }
        }

        return $matrixuserid;
    }

    /**
     * Sets qualified matrix user user id
     *
     * @param string $username Moodle user's username
     * @return string
     */
    public static function set_qualified_matrix_user_id(string $userid, string $homeserver) : string {
        $user = \core_user::get_user($userid);
        $homeserver = parse_url($homeserver)['host'];
        if (strpos($homeserver, '.') !== false) {
            $host = explode('.', $homeserver);
            $homeserver = strpos($homeserver, 'www') !== false ? $host[1] : $host[0];
        } else {
            $homeserver = $homeserver;
        }
        return "@{$user->username}:{$homeserver}";
    }
}