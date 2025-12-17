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

namespace core\hook\admin;

/**
 * Allow plugins to provide additional confirmation HTML when deleting users.
 *
 * @package    core
 * @copyright  2026 Jayce Birrell <jayce.birrell@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allow plugins to provide confirmation text for deleting users.')]
#[\core\attribute\tags('user', 'admin')]
class user_deletion_confirmation_text {
    /** @var string[] Extra confirmation HTML snippets */
    private array $additions = [];

    /**
     * Create a new instance of the hook.
     *
     * @param string $helpurl Documentation URL describing the implications of user deletion.
     * May be used by plugins to provide contextual help links.
     */
    public function __construct(
        /** @var string Documentation URL describing the implications of user deletion. */
        public readonly string $helpurl,
    ) {
    }

    /**
     * Add extra confirmation HTML.
     *
     * Plugins should provide fully-formed HTML (e.g. <p>, <div>, <ul>).
     *
     * @param string $html
     */
    public function add_html(string $html): void {
        if ($html !== '') {
            $this->additions[] = $html;
        }
    }

    /**
     * Get extra confirmation HTML snippets.
     *
     * @return string[]
     */
    public function get_additions(): array {
        return $this->additions;
    }
}
