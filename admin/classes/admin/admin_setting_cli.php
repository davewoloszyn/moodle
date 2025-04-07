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

namespace core_admin\admin;

use admin_setting;

/**
 * Write settings to the log via CLI.
 *
 * @package    core_admin
 * @subpackage admin
 * @copyright  2025 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_cli extends admin_setting {

    public function __construct($name) {
        parent::__construct($name, '', '', '');
    }

    #[\Override]
    public function get_setting(): bool {
        return true;
    }

    #[\Override]
    public function write_setting($data): string {
        return $this->config_write($this->name, $data) ? '' : get_string('errorsetting', 'admin');
    }
}
