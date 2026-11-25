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

/**
 * Contains helper class for the H5P area.
 *
 * @package    core_h5p
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

use context_system;
use core_h5p\local\library\autoloader;
use core_user;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for the H5P area.
 *
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Store an H5P file.
     *
     * @param factory $factory The \core_h5p\factory object
     * @param \stored_file $file Moodle file instance
     * @param \stdClass $config Button options config
     * @param bool $onlyupdatelibs Whether new libraries can be installed or only the existing ones can be updated
     * @param bool $skipcontent Should the content be skipped (so only the libraries will be saved)?
     *
     * @return int|false|null The H5P identifier or null if there is an error when saving or false if it's not a valid H5P package
     */
    public static function save_h5p(factory $factory, \stored_file $file, \stdClass $config, bool $onlyupdatelibs = false,
            bool $skipcontent = false) {

        if (api::is_valid_package($file, $onlyupdatelibs, $skipcontent, $factory, false)) {
            $core = $factory->get_core();
            $h5pvalidator = $factory->get_validator();
            $h5pstorage = $factory->get_storage();

            $content = [
                'pathnamehash' => $file->get_pathnamehash(),
                'contenthash' => $file->get_contenthash(),
            ];
            $options = ['disable' => self::get_display_options($core, $config)];

            // Add the 'title' if exists from 'h5p.json' data to keep it for the editor.
            if (!empty($h5pvalidator->h5pC->mainJsonData['title'])) {
                $content['title'] = $h5pvalidator->h5pC->mainJsonData['title'];
            }

            // If exists, add the metadata from 'h5p.json' to avoid loosing this information.
            $data = $h5pvalidator->h5pC->mainJsonData;
            if (!empty($data)) {
                // The metadata fields are defined in 'joubel/core/h5p-metadata.class.php'.
                $metadatafields = [
                    'title',
                    'a11yTitle',
                    'changes',
                    'authors',
                    'source',
                    'license',
                    'licenseVersion',
                    'licenseExtras',
                    'authorComments',
                    'yearFrom',
                    'yearTo',
                    'defaultLanguage',
                ];
                $content['metadata'] = array_reduce($metadatafields, function ($array, $field) use ($data) {
                    if (array_key_exists($field, $data)) {
                        $array[$field] = $data[$field];
                    }
                    return $array;
                }, []);
            }
            $h5pstorage->savePackage($content, null, $skipcontent, $options);

            return $h5pstorage->contentId;
        }

        return false;
    }

    /**
     * Get the error messages stored in our H5P framework.
     *
     * @param \stdClass $messages The error, exception and info messages, raised while preparing and running an H5P content.
     * @param factory $factory The \core_h5p\factory object
     *
     * @return \stdClass with framework error messages.
     */
    public static function get_messages(\stdClass $messages, factory $factory): \stdClass {
        $core = $factory->get_core();

        // Check if there are some errors and store them in $messages.
        if (empty($messages->error)) {
            $messages->error = $core->h5pF->getMessages('error') ?: false;
        } else {
            $messages->error = array_merge($messages->error, $core->h5pF->getMessages('error'));
        }

        if (empty($messages->info)) {
            $messages->info = $core->h5pF->getMessages('info') ?: false;
        } else {
            $messages->info = array_merge($messages->info, $core->h5pF->getMessages('info'));
        }

        return $messages;
    }

    /**
     * Get the representation of display options as int.
     *
     * @param core $core The \core_h5p\core object
     * @param stdClass $config Button options config
     *
     * @return int The representation of display options as int
     */
    public static function get_display_options(core $core, \stdClass $config): int {
        $export = isset($config->export) ? $config->export : 0;
        $embed = isset($config->embed) ? $config->embed : 0;
        $copyright = isset($config->copyright) ? $config->copyright : 0;
        $frame = ($export || $embed || $copyright);
        if (!$frame) {
            $frame = isset($config->frame) ? $config->frame : 0;
        }

        $disableoptions = [
            core::DISPLAY_OPTION_FRAME     => $frame,
            core::DISPLAY_OPTION_DOWNLOAD  => $export,
            core::DISPLAY_OPTION_EMBED     => $embed,
            core::DISPLAY_OPTION_COPYRIGHT => $copyright,
        ];

        return $core->getStorableDisplayOptions($disableoptions, 0);
    }

    /**
     * Convert the int representation of display options into stdClass
     *
     * @param core $core The \core_h5p\core object
     * @param int $displayint integer value representing display options
     *
     * @return int The representation of display options as int
     */
    public static function decode_display_options(core $core, ?int $displayint = null): \stdClass {
        $config = new \stdClass();
        if ($displayint === null) {
            $displayint = self::get_display_options($core, $config);
        }
        $displayarray = $core->getDisplayOptionsForEdit($displayint);
        $config->export = $displayarray[core::DISPLAY_OPTION_DOWNLOAD] ?? 0;
        $config->embed = $displayarray[core::DISPLAY_OPTION_EMBED] ?? 0;
        $config->copyright = $displayarray[core::DISPLAY_OPTION_COPYRIGHT] ?? 0;
        return $config;
    }

    /**
     * Checks if the author of the .h5p file is "trustable". If the file hasn't been uploaded by a user with the
     * required capability, the content won't be deployed, unless the user has been deleted, in this
     * case we check the capability against current user.
     *
     * @param  stored_file $file The .h5p file to be deployed
     * @return bool Returns true if the file can be deployed, false otherwise.
     */
    public static function can_deploy_package(\stored_file $file): bool {
        $userid = $file->get_userid();
        if (null === $userid) {
            // If there is no userid, it is owned by the system.
            return true;
        }

        $context = \context::instance_by_id($file->get_contextid());
        $fileuser = core_user::get_user($userid);
        if (empty($fileuser) || $fileuser->deleted) {
            $userid = null;
        }
        return has_capability('moodle/h5p:deploy', $context, $userid);
    }

    /**
     * Checks if the content-type libraries can be upgraded.
     * The H5P content-type libraries can only be upgraded if the author of the .h5p file can manage content-types or if all the
     * content-types exist, to avoid users without the required capability to upload malicious content. If user has been deleted
     * we check against current user.
     *
     * @param  stored_file $file The .h5p file to be deployed
     * @return bool Returns true if the content-type libraries can be created/updated, false otherwise.
     */
    public static function can_update_library(\stored_file $file): bool {
        $userid = $file->get_userid();
        if (null === $userid) {
            // If there is no userid, it is owned by the system.
            return true;
        }
        // Check if the owner of the .h5p file has the capability to manage content-types.
        $context = \context::instance_by_id($file->get_contextid());
        $fileuser = core_user::get_user($userid);
        if (empty($fileuser) || $fileuser->deleted) {
            $userid = null;
        }

        return has_capability('moodle/h5p:updatelibraries', $context, $userid);
    }

    /**
     * Convenience to take a fixture test file and create a stored_file.
     *
     * @param string $filepath The filepath of the file
     * @param  int   $userid  The author of the file
     * @param  \context $context The context where the file will be created
     * @return \stored_file The file created
     */
    public static function create_fake_stored_file_from_path(string $filepath, int $userid = 0,
            ?\context $context = null): \stored_file {
        if (is_null($context)) {
            $context = context_system::instance();
        }
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'core_h5p',
            'filearea'  => 'unittest',
            'itemid'    => rand(),
            'filepath'  => '/',
            'filename'  => basename($filepath),
        ];
        if (!is_null($userid)) {
            $filerecord['userid'] = $userid;
        }

        $fs = get_file_storage();
        return $fs->create_file_from_pathname($filerecord, $filepath);
    }

    /**
     * Get information about different H5P tools and their status.
     *
     * @return array Data to render by the template
     */
    public static function get_h5p_tools_info(): array {
        $tools = array();

        // Getting information from available H5P tools one by one because their enabled/disabled options are totally different.
        // Check the Display H5P filter status.
        $link = \core\plugininfo\filter::get_manage_url();
        $status = filter_get_active_state('displayh5p', context_system::instance()->id);
        $tools[] = self::convert_info_into_array('filter_displayh5p', $link, $status);

        // Check H5P scheduled task.
        $link = '';
        $status = 0;
        $statusaction = '';
        if ($task = \core\task\manager::get_scheduled_task('\core\task\h5p_get_content_types_task')) {
            $status = !$task->get_disabled();
            $link = new \moodle_url(
                '/admin/tool/task/scheduledtasks.php',
                array('action' => 'edit', 'task' => get_class($task))
            );
            if ($status && \core\task\manager::is_runnable() && get_config('tool_task', 'enablerunnow')) {
                $statusaction = \html_writer::link(
                    new \moodle_url('/admin/tool/task/schedule_task.php',
                        array('task' => get_class($task))),
                    get_string('runnow', 'tool_task'));
            }
        }
        $tools[] = self::convert_info_into_array('task_h5p', $link, $status, $statusaction);

        return $tools;
    }

    /**
     * Convert information into needed mustache template data array
     * @param string $tool The name of the tool
     * @param \moodle_url $link The URL to management page
     * @param int $status The current status of the tool
     * @param string $statusaction A link to 'Run now' option for the task
     * @return array
     */
    private static function convert_info_into_array(string $tool,
        \moodle_url $link,
        int $status,
        string $statusaction = ''): array {

        $statusclasses = array(
            TEXTFILTER_DISABLED => 'badge bg-danger text-white',
            TEXTFILTER_OFF => 'badge bg-warning text-dark',
            0 => 'badge bg-danger text-white',
            TEXTFILTER_ON => 'badge bg-success text-white',
        );

        $statuschoices = array(
            TEXTFILTER_DISABLED => get_string('disabled', 'admin'),
            TEXTFILTER_OFF => get_string('offbutavailable', 'core_filters'),
            0 => get_string('disabled', 'admin'),
            1 => get_string('enabled', 'admin'),
        );

        return [
            'tool' => get_string($tool, 'h5p'),
            'tool_description' => get_string($tool . '_description', 'h5p'),
            'link' => $link,
            'status' => $statuschoices[$status],
            'status_class' => $statusclasses[$status],
            'status_action' => $statusaction,
        ];
    }

    /**
     * Get a query string with the theme revision number to include at the end
     * of URLs. This is used to force the browser to reload the asset when the
     * theme caches are cleared.
     *
     * @return string
     */
    public static function get_cache_buster(): string {
        global $CFG;
        return '?ver=' . $CFG->themerev;
    }

    /**
     * Get the settings needed by the H5P library.
     *
     * @param string|null $component
     * @return array The settings.
     */
    public static function get_core_settings(?string $component = null): array {
        global $CFG, $USER;

        $basepath = $CFG->wwwroot . '/';
        $systemcontext = context_system::instance();

        // H5P doesn't currently support xAPI State. It implements a mechanism in contentUserDataAjax() in h5p.js to update user
        // data. However, in our case, we're overriding this method to call the xAPI State web services.
        $ajaxpaths = [
            'contentUserData' => '',
        ];

        $factory = new factory();
        $core = $factory->get_core();

        // When there is a logged in user, her information will be passed to the player. It will be used for tracking.
        $usersettings = [];
        if (isloggedin()) {
            $usersettings['name'] = fullname($USER, has_capability('moodle/site:viewfullnames', $systemcontext));
            $usersettings['id'] = $USER->id;
        }
        $savefreq = false;
        if ($component !== null && get_config($component, 'enablesavestate')) {
            $savefreq = get_config($component, 'savestatefreq');
        }
        $settings = array(
            'baseUrl' => $basepath,
            'url' => "{$basepath}pluginfile.php/{$systemcontext->instanceid}/core_h5p",
            'urlLibraries' => "{$basepath}pluginfile.php/{$systemcontext->id}/core_h5p/libraries",
            'postUserStatistics' => false,
            'ajax' => $ajaxpaths,
            'saveFreq' => $savefreq,
            'siteUrl' => $CFG->wwwroot,
            'l10n' => array('H5P' => $core->getLocalization()),
            'user' => $usersettings,
            'hubIsEnabled' => false,
            'reportingIsEnabled' => false,
            'crossorigin' => !empty($CFG->h5pcrossorigin) ? $CFG->h5pcrossorigin : null,
            'libraryConfig' => $core->h5pF->getLibraryConfig(),
            'pluginCacheBuster' => self::get_cache_buster(),
            'libraryUrl' => autoloader::get_h5p_core_library_url('js')->out(),
        );

        return $settings;
    }

    /**
     * Get the core H5P assets, including all core H5P JavaScript and CSS.
     *
     * @param string|null $component
     * @return Array core H5P assets.
     */
    public static function get_core_assets(?string $component = null): array {
        global $PAGE;

        // Get core settings.
        $settings = self::get_core_settings($component);
        $settings['core'] = [
            'styles' => [],
            'scripts' => []
        ];
        $settings['loadedJs'] = [];
        $settings['loadedCss'] = [];

        // Make sure files are reloaded for each plugin update.
        $cachebuster = self::get_cache_buster();

        // Use relative URL to support both http and https.
        $liburl = autoloader::get_h5p_core_library_url()->out();
        $relpath = '/' . preg_replace('/^[^:]+:\/\/[^\/]+\//', '', $liburl);

        // Add core stylesheets.
        foreach (core::$styles as $style) {
            $settings['core']['styles'][] = $relpath . $style . $cachebuster;
            $PAGE->requires->css(new \moodle_url($liburl . $style . $cachebuster));
        }
        // Add core JavaScript.
        foreach (core::get_scripts() as $script) {
            $settings['core']['scripts'][] = $script->out(false);
            $PAGE->requires->js($script, true);
        }

        return $settings;
    }

    /**
     * Prepare the library name to be used as a cache key (remove whitespaces and replace dots to underscores).
     *
     * @param  string $library Library name.
     * @return string Library name in a cache simple key format (a-zA-Z0-9_).
     */
    public static function get_cache_librarykey(string $library): string {
        // Remove whitespaces and replace '.' to '_'.
        return str_replace('.', '_', str_replace(' ', '', $library));
    }

    /**
     * Parse a JS object to a PHP array.
     *
     * The content can be a whole JS file, such as "H5PEditor.language.core = { ... };". Only the object from the first '{'
     * to the final line holding just its closing '}' or '};' is parsed.
     *
     * Nested objects and arrays are returned as nested PHP arrays. String values are unescaped, and any other value
     * (number, boolean...) is returned as written. Properties without a value are ignored, and parsing stops at any
     * unexpected content, returning the properties found before it.
     *
     * @param  string $jscontent The JS content to parse to PHP array.
     * @return array The JS object converted to PHP array.
     */
    public static function parse_js_array(string $jscontent): array {
        // Convert all line-endings to UNIX format first.
        $jscontent = str_replace(["\r\n", "\r"], "\n", $jscontent);

        // Nested objects close with '},' or are followed by their parent's closing brace, so the last line holding only
        // '}' or '};' is the end of the object. The first '}' cannot be used because it may close a nested object.
        $start = strpos($jscontent, '{');
        if ($start === false || !preg_match_all('/^[ \t]*\};?[ \t]*$/m', $jscontent, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        [$closingline, $closingoffset] = end($matches[0]);
        $end = $closingoffset + strpos($closingline, '}');
        $jscontent = substr($jscontent, $start, $end - $start + 1);

        $position = 0;
        return self::parse_js_collection($jscontent, $position);
    }

    /**
     * Parse the JS object or array starting at the given position.
     *
     * @param  string $js The JS content.
     * @param  int $position Position of the opening '{' or '['. It is moved past the closing '}' or ']', or to the end of
     *     the content when unexpected content is found.
     * @return array The object properties keyed by name, or the array values.
     */
    private static function parse_js_collection(string $js, int &$position): array {
        $isobject = $js[$position] === '{';
        $closing = $isobject ? '}' : ']';
        $collection = [];
        $position++;
        while (($char = self::skip_js_whitespace($js, $position)) !== $closing && $char !== '') {
            if ($char === ',') {
                $position++;
                continue;
            }
            if ($isobject) {
                $key = self::parse_js_key($js, $position);
                if ($key === null) {
                    break;
                }
                if (self::skip_js_whitespace($js, $position) !== ':') {
                    // A property without value, such as "key,", is ignored.
                    continue;
                }
                $position++;
            }
            $value = self::parse_js_value($js, $position);
            if ($value === null) {
                break;
            }
            if ($isobject) {
                $collection[$key] = $value;
            } else {
                $collection[] = $value;
            }
        }

        if ($char !== $closing) {
            // Unexpected content was found: stop parsing, keeping what has been parsed so far.
            $position = strlen($js);
        } else {
            $position++;
        }
        return $collection;
    }

    /**
     * Parse the JS property name starting at the given position.
     *
     * @param  string $js The JS content.
     * @param  int $position Position of the property name. It is moved past the name.
     * @return string|null The property name, or null if there is no valid property name at the position.
     */
    private static function parse_js_key(string $js, int &$position): ?string {
        if ($js[$position] === "'" || $js[$position] === '"') {
            return self::parse_js_string($js, $position);
        }
        if (!preg_match('/[\w$]+/A', $js, $matches, 0, $position)) {
            return null;
        }
        $position += strlen($matches[0]);
        return $matches[0];
    }

    /**
     * Parse the JS value starting at the given position.
     *
     * @param  string $js The JS content.
     * @param  int $position Position of the value. It is moved past the value.
     * @return array|string|null The parsed value, or null if there is no valid value at the position.
     */
    private static function parse_js_value(string $js, int &$position): array|string|null {
        $char = self::skip_js_whitespace($js, $position);
        if ($char === '{' || $char === '[') {
            return self::parse_js_collection($js, $position);
        }
        if ($char === "'" || $char === '"') {
            $value = self::parse_js_string($js, $position);
            if ($value !== null && self::skip_js_whitespace($js, $position) === '+') {
                // Join concatenated strings, such as 'First part, ' + 'second part'.
                $position++;
                $next = self::parse_js_value($js, $position);
                $value = is_string($next) ? $value . $next : null;
            }
            return $value;
        }
        // Other values, such as numbers or booleans, are returned as written.
        if (!preg_match('/[\w.+-]+/A', $js, $matches, 0, $position)) {
            return null;
        }
        $position += strlen($matches[0]);
        return $matches[0];
    }

    /**
     * Parse the single or double quoted JS string starting at the given position.
     *
     * @param  string $js The JS content.
     * @param  int $position Position of the opening quote. It is moved past the closing quote.
     * @return string|null The unescaped string, or null if the string is not closed.
     */
    private static function parse_js_string(string $js, int &$position): ?string {
        $quote = $js[$position];
        if (!preg_match('/' . $quote . '((?:[^' . $quote . '\\\\]|\\\\.)*)' . $quote . '/As', $js, $matches, 0, $position)) {
            return null;
        }
        $position += strlen($matches[0]);

        return preg_replace_callback('/\\\\(u[0-9a-fA-F]{4}|.)/s', function (array $escape): string {
            return match ($escape[1][0]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'u' => strlen($escape[1]) === 5 ? mb_chr(hexdec(substr($escape[1], 1)), 'UTF-8') : 'u',
                // Any other escaped character, such as a quote or a backslash, is the character itself.
                default => $escape[1],
            };
        }, $matches[1]);
    }

    /**
     * Skip the whitespaces and comments starting at the given position.
     *
     * @param  string $js The JS content.
     * @param  int $position Current position. It is moved to the next character that is not a whitespace or a comment.
     * @return string The character at the new position, or an empty string at the end of the content.
     */
    private static function skip_js_whitespace(string $js, int &$position): string {
        preg_match('~(?:\s+|//[^\n]*|/\*.*?\*/)*~As', $js, $matches, 0, $position);
        $position += strlen($matches[0]);
        return $js[$position] ?? '';
    }

    /**
     * Get the information related to the H5P export file.
     * The information returned will be:
     * - filename, filepath, mimetype, filesize, timemodified and fileurl.
     *
     * @param  string $exportfilename The H5P export filename (with slug).
     * @param  \moodle_url $url The URL of the exported file.
     * @param  factory $factory The \core_h5p\factory object
     * @return array|null The information export file otherwise null.
     */
    public static function get_export_info(string $exportfilename, ?\moodle_url $url = null, ?factory $factory = null): ?array {

        if (!$factory) {
            $factory = new factory();
        }
        $core = $factory->get_core();

        // Get export file.
        if (!$fileh5p = $core->fs->get_export_file($exportfilename)) {
            return null;
        }

        // Build the export info array.
        $file = [];
        $file['filename'] = $fileh5p->get_filename();
        $file['filepath'] = $fileh5p->get_filepath();
        $file['mimetype'] = $fileh5p->get_mimetype();
        $file['filesize'] = $fileh5p->get_filesize();
        $file['timemodified'] = $fileh5p->get_timemodified();

        if (!$url) {
            $url  = \moodle_url::make_webservice_pluginfile_url(
                $fileh5p->get_contextid(),
                $fileh5p->get_component(),
                $fileh5p->get_filearea(),
                '',
                '',
                $fileh5p->get_filename()
            );
        }

        $file['fileurl'] = $url->out(false);

        return $file;
    }
}
