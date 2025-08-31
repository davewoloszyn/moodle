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

namespace core_ai;

trait ai_test_trait {
    /**
     * Creates a dummy AI provider.
     *
     * @param string $actionclass
     * @param string $providerclass
     * @return void
     */
    private function create_ai_provider(string $actionclass, $providerclass) {
        global $DB;

        $config = ['apikey' => 'test'];
        $record = new \stdClass();
        $record->name = 'test';
        $record->provider = $providerclass;
        $record->enabled = 1;
        $record->config = json_encode($config);
        $record->actionconfig = json_encode([
            $actionclass => [
                'enabled' => true,
                'settings' => [
                    'model' => 'test',
                    'endpoint' => 'test',
                    'systeminstruction' => 'test',
                ],
            ],
        ]);
        $DB->insert_record('ai_providers', $record);
    }
}