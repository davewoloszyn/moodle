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

use core\http_client;
use GuzzleHttp\Psr7\Request;

/**
 * Class matrix_endpoint_manager to manage the api endpoints of matrix provider.
 *
 * @package    communication_matrix
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matrix_events_manager {

    /**
     * @var string|false|mixed|object $matrixhomeserverurl The URL of the home server
     */
    private string $matrixhomeserverurl;

    /**
     * @var string|false|mixed|object $matrixaccesstoken The access token of the matrix server
     */
    private string $matrixaccesstoken;

    /**
     * @var string|false|mixed|object $matrixrefreshtoken The refresh token of the matrix server
     */
    private string $matrixrefreshtoken;

    /**
     * @var string $tokeninuse The actual token in use, it can be access token or refresh token
     */
    private string $tokeninuse;

    /**
     * @var string $roomid The id of the room from matrix server
     */
    private string $roomid;

    /**
     * Matrix events constructor to get the room id and refresh token usage if required.
     *
     * @param string|null $roomid The id of the room from matrix server
     * @param bool $userefreshtoken The usage of refresh token or nor
     */
    public function __construct(string $roomid = null, bool $userefreshtoken = false) {
        $this->matrixhomeserverurl = get_config('communication_matrix', 'matrixhomeserverurl');
        $this->matrixaccesstoken = get_config('communication_matrix', 'matrixaccesstoken');
        $this->use_access_token();

        if (!empty($refreshtoken = get_config('communication_matrix', 'matrixrefreshtoken'))) {
            $this->matrixrefreshtoken = $refreshtoken;
            if ($userefreshtoken) {
                $this->use_refresh_token();
            }
        }

        // TODO decide how manage or use refresh token and access token and when to use what.

        if (!empty($roomid)) {
            $this->roomid = $roomid;
        }
    }

    /**
     * Get the current token in use.
     *
     * @return string
     */
    public function get_token(): string {
        return $this->tokeninuse;
    }

    /**
     * Get the matrix api endpoint for creating a new room.
     *
     * @return string
     */
    public function get_create_room_endpoint(): string {
        return $this->matrixhomeserverurl . '/' . '_matrix/client/r0/createRoom';
    }

    /**
     * Get the matrix api endpoint for updating the room topic.
     *
     * @return string
     */
    public function get_update_room_topic_endpoint(): string {
        if (!empty($this->roomid)) {
            return $this->matrixhomeserverurl . '/' . '_matrix/client/r0/rooms' .
                '/' . urlencode($this->roomid) . '/' . 'state/m.room.topic/';
        }
    }

    /**
     * Get the matrix api endpoint for updating room name.
     *
     * @return string
     */
    public function get_update_room_name_endpoint(): string {
        if (!empty($this->roomid)) {
            return $this->matrixhomeserverurl . '/' . '_matrix/client/r0/rooms' .
                '/' . urlencode($this->roomid) . '/' . 'state/m.room.name/';
        }
    }

    /**
     * Get delete room endpoint.
     *
     * @return string
     */
    public function get_delete_room_endpoint(): string {
        if (!empty($this->roomid)) {
            return $this->matrixhomeserverurl . '/' . '_synapse/admin/v1/rooms/' . urlencode($this->roomid);
        }
    }

    /**
     * Get the matrix api endpoint for uploading a content to synapse server.
     */
    public function get_upload_content_endpoint(): string {
        return $this->matrixhomeserverurl . '/' . '_matrix/media/v3/upload/';
    }

    /**
     * Get the matrix api endpoint for updating room avatar.
     *
     * @return string
     */
    public function get_update_avatar_endpoint(): string {
        if (!empty($this->roomid)) {
            return $this->matrixhomeserverurl . '/' . '_matrix/client/r0/rooms' .
                '/' . urlencode($this->roomid) . '/' . 'state/m.room.avatar/';
        }
    }

    /**
     * The http request for the api call.
     *
     * @param array $jsonarray The array of json
     * @param array $headers The array of headers
     * @return \core\http_client
     */
    public function request(array $jsonarray = [], array $headers = []): \core\http_client {
        $header = ['Authorization' => 'Bearer ' . $this->get_token()];
        $headers = array_merge($header, $headers);
        $response = new  \core\http_client([
            // TODO remove ignore security before integrating as its required for development.
            'ignoresecurity' => true,
            'headers' => $headers,
            'json' => $jsonarray,
        ]);
        return $response;
    }

    /**
     * Use access token for the api call.
     *
     * @return void
     */
    public function use_access_token(): void {
        $this->tokeninuse = $this->matrixaccesstoken;
    }

    /**
     * Use refresh token for the api call.
     *
     * @return void
     */
    public function use_refresh_token(): void {
        if (!empty($this->matrixrefreshtoken)) {
            $this->tokeninuse = $this->matrixrefreshtoken;
        } else {
            throw new \coding_exception('Refresh token is not set, please add a refresh token in matrix plugin config.');
        }
    }

    /**
     * Upload the content in the matrix/synapse server.
     *
     * @param string $content The content to be uploaded
     * @return string|false
     */
    public function upload_matrix_content(string $content): bool|string {
        $headers = [
            'Authorization' => 'Bearer ' . $this->get_token(),
            'Content-Type' => 'image/png'
        ];

        // TODO remove ignore security before integrating as its required for development.
        $client = new http_client(['ignoresecurity' => true]);
        $request = new Request('POST', $this->get_upload_content_endpoint(), $headers, $content);

        $response = $client->send($request);
        $response = json_decode($response->getBody());
        if ($response) {
            return $response->content_uri;
        }
        return false;
    }

}
