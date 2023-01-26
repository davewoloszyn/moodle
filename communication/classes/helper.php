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
 * Core communication helper class to add additional methods to be used in different locations.
 *
 * @package    core_communication
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Get the available communication providers.
     * It will only supply the enabled ones and also the ones implementing the plugin entrypoint.
     *
     * @return array
     */
    public static function get_available_communication_providers(): array {
        $plugintype = 'communication';
        $plugins = \core_component::get_plugin_list($plugintype);
        foreach ($plugins as $pluginname => $plugin) {
            if (!\core\plugininfo\communication::is_plugin_enabled($plugintype . '_' . $pluginname)) {
                unset($plugins[$pluginname]);
            }
        }
        return $plugins;
    }

    /**
     * Get the list of plugins for form selection.
     *
     * @return array
     */
    public static function get_communication_plugin_list_for_form(): array {
        $selection = [];
        $communicationplugins = self::get_available_communication_providers();
        foreach ($communicationplugins as $pluginname => $notusing) {
            $selection['communication_' . $pluginname] = get_string('pluginname', 'communication_'. $pluginname);
        }
        return $selection;
    }

}
