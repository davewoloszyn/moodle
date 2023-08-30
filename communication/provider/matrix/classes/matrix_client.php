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

use Closure;
use communication_matrix\local\command;
use core\http_client;
use core\lock\lock_factory;
use DirectoryIterator;
use Exception;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * The abstract class for a versioned API client for Matrix.
 *
 * Matrix uses a versioned API, and a handshake occurs between the Client (Moodle) and server, to determine the APIs available.
 *
 * This client represents a version-less API client.
 * Versions are implemented by combining the various features into a versionedclass.
 * See v1p1 for example.
 *
 * @package    communication_matrix
 * @copyright  2023 Andrew Lyons <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class matrix_client {

    /** @var string $serverurl The URL of the home server */
    /** @var Closure $tokenfetcher The callable token fetcher function */
    /** @var Closure $tokensetter The callable token setter function */
    /** @var Closure $refreshcommand The callable refresh token command function */
    /** @var lock_factory $lockfactory The access token of the matrix server */

    /** @var http_client|null The client to use */
    protected static http_client|null $client = null;

    /**
     * Matrix events constructor to get the room id and refresh token usage if required.
     *
     * @param string $serverurl The URL of the API server
     * @param Closure $tokenfetcher A Closure which will return a token value
     * @param Closure $tokensetter A Closure which will set a token value
     * @param Closure $refreshcommand A Closure which will get the refresh token command
     * @param lock_factory $lockfactory
     */
    protected function __construct(
        protected string $serverurl,
        protected Closure $tokenfetcher,
        protected Closure $tokensetter,
        protected Closure $refreshcommand,
        protected lock_factory $lockfactory,
    ) {
    }

    /**
     * Return the versioned instance of the API.
     *
     * @param string $serverurl The URL of the API server
     * @param callable $tokenfetcher
     * @param callable $tokensetter
     * @param callable $refreshcommand
     * @param lock_factory $lockfactory
     * @return matrix_client
     */
    public static function instance(
        string $serverurl,
        callable $tokenfetcher,
        callable $tokensetter,
        callable $refreshcommand,
        lock_factory $lockfactory,
    ): matrix_client {
        // Fetch the list of supported API versions.
        $clientversions = self::get_supported_versions();

        // Fetch the supported versions from the server.
        $serversupports = self::query_server_supports($serverurl);
        $serverversions = $serversupports->versions;

        // Calculate the intersections and sort to determine the highest combined version.
        $versions = array_intersect($clientversions, $serverversions);
        if (count($versions) === 0) {
            // No versions in common.
            throw new \moodle_exception('No supported Matrix API versions found.');
        }
        asort($versions);
        $version = array_key_last($versions);

        $classname = \communication_matrix\local\spec::class . '\\' . $version;

        return new $classname(
            serverurl: $serverurl,
            tokenfetcher: Closure::fromCallable($tokenfetcher),
            tokensetter: Closure::fromCallable($tokensetter),
            refreshcommand: Closure::fromCallable($refreshcommand),
            lockfactory: $lockfactory,
        );
    }

    /**
     * Determine if the API supports a feature.
     *
     * If an Array is provided, this will return true if any of the specified features is implemented.
     *
     * @param string[]|string $feature The feature to check. This is in the form of a namespaced class.
     * @return bool
     */
    public function implements_feature(array|string $feature): bool {
        if (is_array($feature)) {
            foreach ($feature as $thisfeature) {
                if ($this->implements_feature($thisfeature)) {
                    return true;
                }
            }

            // None of the features are implemented in this API version.
            return false;
        }

        return in_array($feature, $this->get_supported_features());
    }

    /**
     * Get a list of the features supported by this client.
     *
     * @return string[]
     */
    public function get_supported_features(): array {
        $features = [];
        $class = static::class;
        do {
            $features = array_merge($features, class_uses($class));
            $class = get_parent_class($class);
        } while ($class);

        return $features;
    }

    /**
     * Require that the API supports a feature.
     *
     * If an Array is provided, this is treated as a require any of the features.
     *
     * @param string[]|string $feature The feature to test
     * @throws \moodle_exception
     */
    public function require_feature(array|string $feature): void {
        if (!$this->implements_feature($feature)) {
            if (is_array($feature)) {
                $features = implode(', ', $feature);
                throw new \moodle_exception(
                    "None of the possible feature are implemented in this Matrix Client: '{$features}'"
                );
            }
            throw new \moodle_exception("The requested feature is not implemented in this Matrix Client: '{$feature}'");
        }
    }

    /**
     * Require that the API supports a list of features.
     *
     * All features specified will be required.
     *
     * If an array is provided as one of the features, any of the items in the nested array will be required.
     *
     * @param string[]|array[] $features The list of features required
     *
     * Here is an example usage:
     * <code>
     * $matrixapi->require_features([
     *
     *     \communication_matrix\local\spec\features\create_room::class,
     *     [
     *         \communication_matrix\local\spec\features\get_room_info_v1::class,
     *         \communication_matrix\local\spec\features\get_room_info_v2::class,
     *     ]
     * ])
     * </code>
     */
    public function require_features(array $features): void {
        array_walk($features, [$this, 'require_feature']);
    }

    /**
     * Get the URL of the server.
     *
     * @return string
     */
    public function get_server_url(): string {
        return $this->serverurl;
    }

    /**
     * Query the supported versions, and any unstable features, from the server.
     *
     * Servers must implement the client versions API described here:
     * - https://spec.matrix.org/latest/client-server-api/#get_matrixclientversions
     *
     * @param string $serverurl The server base
     * @return \stdClass The list of supported versions and a list of enabled unstable features
     */
    protected static function query_server_supports(string $serverurl): \stdClass {
        // Attempt to return from the cache first.
        $cache = \cache::make('communication_matrix', 'serverversions');
        $serverkey = sha1($serverurl);
        if ($cache->get($serverkey)) {
            return $cache->get($serverkey);
        }

        // Not in the cache - fetch and store in the cache.
        $client = static::get_http_client();
        $response = $client->get("{$serverurl}/_matrix/client/versions");
        $supportsdata = json_decode(
            json: $response->getBody(),
            associative: false,
            flags: JSON_THROW_ON_ERROR,
        );

        $cache->set($serverkey, $supportsdata);

        return $supportsdata;
    }

    /**
     * Get the list of supported versions based on the available classes.
     *
     * @return array
     */
    public static function get_supported_versions(): array {
        $versions = [];
        $iterator = new DirectoryIterator(__DIR__ . '/local/spec');
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDir()) {
                continue;
            }

            // Get the classname from the filename.
            $classname = substr($fileinfo->getFilename(), 0, -4);

            if (!preg_match('/^v\d+p\d+$/', $classname)) {
                // @codeCoverageIgnoreStart
                // This file does not fit the format v[MAJOR]p[MINOR]].
                continue;
                // @codeCoverageIgnoreEnd
            }

            $versions[$classname] = "v" . self::get_version_from_classname($classname);
        }

        return $versions;
    }

    /**
     * Get the current access token.
     *
     * @return string
     */
    public function get_access_token(): string {
        return $this->tokenfetcher->call($this, 'accesstoken');
    }

    /**
     * Get the current refresh token.
     *
     * @return null|string
     */
    public function get_refresh_token(): ?string {
        return $this->tokenfetcher->call($this, 'refreshtoken');
    }

    /**
     * Get the refresh token command used in the request.
     *
     * @return command
     */
    public function get_refresh_token_command(): command {
        return $this->refreshcommand->call($this);
    }

    /**
     * Helper to fetch the HTTP Client for the instance.
     *
     * @return \core\http_client
     */
    protected function get_client(): \core\http_client {
        return static::get_http_client();
    }

    /**
     * Helper to fetch the HTTP Client.
     *
     * @return \core\http_client
     */
    protected static function get_http_client(): \core\http_client {
        if (static::$client !== null) {
            return static::$client;
        }
        // @codeCoverageIgnoreStart
        return new http_client();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Execute the specified command.
     *
     * @param command $command
     * @return Response
     */
    protected function execute(
        command $command,
    ): Response {
        $client = $this->get_client();
        $options = $command->get_options();

        if ($command->require_authorization()) {
            $accesstoken = $this->get_access_token();
            $command = $command->apply_bearer_authorization($accesstoken);
            // Refresh token checks (401) are interuppted with exceptions from Guzzle.
            // Ensuring http_errors is set allows custom analysis of the response.
            $options['http_errors'] = false;
        }

        $response = $client->send(
            $command,
            $options,
        );

        if ($command->require_authorization()) {
            return $this->check_response_for_expired_tokens($command, $response, $accesstoken);
        } else {
            return $response;
        }
    }

    /**
     * Check for an expired token and attempt to use the refresh token to obtain fresh ones.
     * This method passes in the original command that will be resent after all checks and updates.
     *
     * @param command $command
     * @param ResponseInterface $response
     * @param string $currenttoken
     * @return ResponseInterface
     */
    protected function check_response_for_expired_tokens(
        command $command,
        ResponseInterface $response,
        string $currenttoken
    ): ResponseInterface {
        // This response is not a 401 response. Return the original response and carry on.
        if ($response->getStatusCode() !== 401) {
            return $response;
        }
        // This client does not support refresh tokens. Nothing we can do even if it has expired.
        if (!$this->support_refresh_tokens()) {
            return $response;
        }
        // Apply a lock while we go and attempt to update tokens.
        if ($lock = $this->lockfactory->get_lock('matrix_client_token_refresh', 10)) {
            $retrycommand = false;
            // Check whether the token has changed since we requested the lock.
            if ($this->get_access_token() === $currenttoken) {
                // Get the refresh token command.
                // Note: This command must not use the standard execute method in case it returns a 401 and gets looped.
                $refreshcommand = $this->get_refresh_token_command();
                $client = $this->get_client();
                $refreshresponse = $client->send(
                    $refreshcommand,
                    $refreshcommand->get_options(),
                );
                // The refresh token has three possible response codes:
                // - 200: New tokens generated
                // - 401: The provided token was unknown, or has already been used.
                // - 429: The request was rate limited.
                if ($refreshresponse->getStatusCode() === 200) {
                    // New tokens returned.
                    $body = json_decode(
                        $refreshresponse->getBody(),
                        associative: true,
                        flags: JSON_THROW_ON_ERROR,
                    );
                    // Set new access token.
                    $this->tokensetter->call($this,
                        'accesstoken',
                        $body['access_token'],
                    );
                    // Set new refresh token.
                    $this->tokensetter->call($this,
                        'refreshtoken',
                        $body['refresh_token'],
                    );
                    $retrycommand = true;
                }
            } else {
                // The token changed while we were waiting for the lock.
                $retrycommand = true;
            }
            $lock->release();
        }
        // Re-run the original request and return the response.
        if ($retrycommand) {
            $command = $command->apply_bearer_authorization($this->get_access_token());

            return $client->send(
                $command,
                $command->get_options(),
            );
        }
        // Return original response.
        return $response;
    }

    /**
     * Check if the client supports refresh tokens.
     * Returns false if no refresh token detected.
     *
     * @return boolean
     */
    protected function support_refresh_tokens(): bool {
        if ($this->get_refresh_token() === null) {
            return false;
        }
        return $this->implements_feature(local\spec\features\matrix\refresh_token_v3::class);
    }

    /**
     * Get the API version of the current instance.
     *
     * @return string
     */
    public function get_version(): string {
        $reflect = new \ReflectionClass(static::class);
        $classname = $reflect->getShortName();
        return self::get_version_from_classname($classname);
    }

    /**
     * Normalise an API version from a classname.
     *
     * @param string $classname The short classname, omitting any namespace or file extension
     * @return string The normalised version
     */
    protected static function get_version_from_classname(string $classname): string {
        $classname = str_replace('v', '', $classname);
        $classname = str_replace('p', '.', $classname);
        return $classname;
    }

    /**
     * Check if the API version is at least the specified version.
     *
     * @param string $minversion The minimum API version required
     * @return bool
     */
    public function meets_version(string $minversion): bool {
        $thisversion = $this->get_version();
        return version_compare($thisversion, $minversion) >= 0;
    }

    /**
     * Assert that the API version is at least the specified version.
     *
     * @param string $minversion The minimum API version required
     * @throws Exception
     */
    public function requires_version(string $minversion): void {
        if ($this->meets_version($minversion)) {
            return;
        }

        throw new \moodle_exception("Matrix API version {$minversion} or higher is required for this command.");
    }
}
