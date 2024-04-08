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

namespace core\oauth2\service;

use core\oauth2\issuer;
use core\oauth2\endpoint;
use core\oauth2\user_field_mapping;
use core\oauth2\discovery\openidconnect;

/**
 * Class for Apple oAuth service, with the specific methods related to it.
 *
 * @package    core
 * @copyright  2023 eabyas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apple extends openidconnect implements issuer_interface {

    /**
     * Build an OAuth2 issuer, with all the default values for this service.
     *
     * @return issuer The issuer initialised with proper default values.
     */
    public static function init(): issuer {
        $record = (object) [
            'name' => 'Apple',
            'image' => 'https://www.apple.com/apple-touch-icon.png',
            'baseurl' => '',
            'loginscopes' => 'public_profile email',
            'loginscopesoffline' => 'public_profile email',
            'showonloginpage' => issuer::EVERYWHERE,
            'servicetype' => 'apple',
        ];

        $issuer = new issuer(0, $record);
        return $issuer;
    }
    /**
     * Process the additional information and create endpoints needed with the expected format.
     *
     * @param issuer $issuer The OAuth issuer the endpoints should be discovered for.
     * @param stdClass $info The discovery information, with the endpoints to process and create.
     * @return void
     */
    protected static function process_configuration_json(issuer $issuer, \stdClass $info): void {
        global $CFG;
        if (!isset($info->userinfo_endpoint)) {
            $record = (object) [
                'issuerid' => $issuer->get('id'),
                'name' => 'userinfo_endpoint',
                'url' => $info->token_endpoint,
            ];
            $endpoint = new endpoint(0, $record);
            $endpoint->create();
        }
        if (!isset($info->jwks_endpoint) && isset($info->jwks_uri)) {
            $record = (object) [
                'issuerid' => $issuer->get('id'),
                'name' => 'jwks_endpoint',
                'url' => $info->jwks_uri,
            ];
            $endpoint = new endpoint(0, $record);
            $endpoint->create();
        }
        parent::process_configuration_json($issuer, $info);
    }
}
