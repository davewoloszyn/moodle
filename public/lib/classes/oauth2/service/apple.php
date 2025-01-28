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
use core\oauth2\discovery\openidconnect;
use stdClass;
use Firebase\JWT\JWT;

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
            'baseurl' => 'https://appleid.apple.com',
            'loginscopes' => 'name email',
            'loginscopesoffline' => 'name email',
            'showonloginpage' => issuer::EVERYWHERE,
            'servicetype' => 'apple',
            'loginparams' => 'response_mode=form_post',
        ];
        return new issuer(0, $record);
    }

    /**
     * Process the additional information and create endpoints needed with the expected format.
     *
     * @param issuer $issuer The OAuth issuer the endpoints should be discovered for.
     * @param stdClass $info The discovery information, with the endpoints to process and create.
     * @return void
     */
    protected static function process_configuration_json(issuer $issuer, stdClass $info): void {
        // Apple doesn't provide a userinfo endpoint, so user info must be fetched from the id_token,
        // which is retrieved using a refresh grant on the token endpoint.
        if (isset($info->token_endpoint)) {
            $record = (object) [
                'issuerid' => $issuer->get('id'),
                'name' => 'userinfo_endpoint',
                'url' => $info->token_endpoint,
            ];
            $endpoint = new endpoint(0, $record);
            $endpoint->create();
        }
        // Unset the user_info endpoint in case apple provides it as it is currently not available.
        if (isset($info->userinfo_endpoint)) {
            unset($info->userinfo_endpoint);
        }
        if (isset($info->jwks_uri)) {
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

    /**
     * Decode the provided issuer's secret and return the defined configurations.
     *
     * @param issuer $issuer The OAuth issuer the endpoints should be discovered for.
     * @return stdClass|bool $configuration Decoded token information, false if none.
     */
    public static function get_expiry_information(issuer $issuer): stdClass|bool {
        $clientsecret = $issuer->get('clientsecret');
        $content = explode('.', $clientsecret);
        // Decoding the configuration set in the client secret info.
        if (!empty($content[1])) {
            $configuration = JWT::jsonDecode(JWT::urlsafeB64Decode($content[1]));
        }
        return !empty($configuration) ? $configuration : false;
    }
}
