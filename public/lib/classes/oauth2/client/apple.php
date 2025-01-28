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

namespace core\oauth2\client;

use stdClass;
use core\oauth2\client;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

/**
 * Custom client handler to fetch data from Apple.
 *
 * Custom oauth2 client for apple as it doesn't support OIDC and has a different way to get
 * key information for users - username, email.
 *
 * @package    core
 * @copyright  2023 eabyas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apple extends client {
    /**
     * Fetch the user info from the idtoken.
     *
     * @return array|false
     */
    public function get_userinfo() {
        global $SESSION;
        $userrecord = $this->get_raw_userinfo();
        if (!empty($userrecord)) {
            $userinfo = [];
            if (!empty($SESSION->appleuserpostcontent)) {
                $userdata = json_decode($SESSION->appleuserpostcontent);
                $userinfo = ['firstname' => $userdata->name->firstName, 'lastname' => $userdata->name->lastName];
                // Clean the session variable containing the Apple oauth post content.
                unset($SESSION->appleuserpostcontent);
            }
            $userinfo['username'] = $userinfo['email'] = $userrecord->email;
            return $userinfo;
        }
        return false;
    }

    /**
     * Fetch the raw user info.
     *
     * @return stdClass|false
     */
    public function get_raw_userinfo(): stdClass|false {
        if (!empty($this->rawuserinfo)) {
            return $this->rawuserinfo;
        }
        if (empty($this->refreshtoken)) {
            return false;
        }

        $issuer = $this->get_issuer();
        $clientid = $issuer->get('clientid');
        $clientsecret = $issuer->get('clientsecret');
        $postfields = [
            'client_id' => $clientid,
            'client_secret' => $clientsecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshtoken,
        ];
        $apiparams = htmlspecialchars_decode(http_build_query($postfields));
        $userinfourl = $issuer->get_endpoint_url('userinfo');
        $jwksurl = $issuer->get_endpoint_url('jwks');
        $response = $this->post($userinfourl, $apiparams);
        $decodedresponse = json_decode($response);
        $keys = $this->get($jwksurl);
        $keyresponse = json_decode($keys);
        $keysarray = array_map(function ($keyinfo): array {
            return (array)$keyinfo;
        }, $keyresponse->keys);
        $userinfo = JWT::decode($decodedresponse->id_token, JWK::parseKeySet(['keys' => $keysarray, 'alg' => 'ES256]']));
        $this->rawuserinfo = $userinfo;

        return $userinfo;
    }
}
