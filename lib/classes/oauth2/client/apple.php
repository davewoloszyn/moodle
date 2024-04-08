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

use core\oauth2\client;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use moodle_url;
/**
 * Class apple - Custom client handler to fetch data from apple
 *
 * Custom oauth2 client for apple as it doesn't support OIDC and has a different way to get
 * key information for users - username, email.
 *
 * @copyright  2023 eabyas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    core
 */
class apple extends client {
    /** @var \core\oauth2\client\apple $clientid */
    private $clientid;

    /** @var \core\oauth2\client\apple $clientsecret */
    private $clientsecret;

    /** @var \core\oauth2\client\apple $userinfourl */
    private $userinfourl;

    /** @var \core\oauth2\client\apple $jwksurl */
    private $jwksurl;
    /**
     * Constructor.
     *
     * @param issuer $issuer
     * @param moodle_url|null $returnurl
     * @param string $scopesrequired
     * @param boolean $system
     * @param boolean $autorefresh whether refresh_token grants are used to allow continued access across sessions.
     */
    public function __construct($issuer, $returnurl, $scopesrequired, $system = false, $autorefresh = false) {
        $this->issuer = $issuer;
        $this->clientid = $issuer->get('clientid');
        $this->clientsecret = $issuer->get('clientsecret');
        $this->userinfourl = $issuer->get_endpoint_url('userinfo');
        $this->jwksurl = $issuer->get_endpoint_url('jwks');
        parent::__construct($this->issuer, $returnurl, $scopesrequired, $system, $autorefresh);
    }
    /**
     * Fetch the user info from the idtoken
     *
     * @return array|false
     */
    public function get_userinfo() {
        $userrecord = $this->get_raw_userinfo();
        if (!empty($userrecord)) {
            $user = [];
            $user['username'] = $userrecord->email;
            $user['email'] = $userrecord->email;
        } else {
            return false;
        }
        return $user;
    }

    /**
     * Fetch the raw user info
     *
     * @return array|false
     */
    public function get_raw_userinfo() {
        if (!empty($this->rawuserinfo)) {
            return $this->rawuserinfo;
        }
        if (empty($this->refreshtoken)) {
            return false;
        }
        $postfields = [
            'client_id' => $this->clientid,
            'client_secret' => $this->clientsecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshtoken,
        ];
        $apiparams = htmlspecialchars_decode(http_build_query($postfields));
        $response = $this->post($this->userinfourl, $apiparams);
        $decodedresponse = json_decode($response);
        $keys = $this->get($this->jwksurl);
        $keyresponse = json_decode($keys);
        $keysarray = array_map(function($keyinfo){
            return (array)$keyinfo;
        }, $keyresponse->keys);
        $userrecord = JWT::decode($decodedresponse->id_token, JWK::parseKeySet(['keys' => $keysarray, 'alg' => 'ES256]']));
        $this->rawuserinfo = $userrecord;
        return $userrecord;
    }

}
