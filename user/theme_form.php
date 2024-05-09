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
 * Form to edit a user's theme preferences.
 *
 * @package    core_user
 * @copyright  2024 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot.'/user/lib.php');

class user_edit_theme_form extends moodleform {

    /**
     * Define the form.
     */
    public function definition(): void {
        global $CFG, $COURSE, $USER;

        $mform = $this->_form;
        $user = $this->_customdata['user'];

        // Add some extra hidden fields.
        $mform->addElement('hidden', 'id', $user->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'course', $COURSE->id);
        $mform->setType('course', PARAM_INT);

        // Dark mode.
        $mform->addElement('checkbox', 'preference_theme_darkmode', get_string('themedarkmode'));
        $mform->setDefault('preference_theme_darkmode', 0);

        // User is using a theme override.
        // if ($CFG->allowuserthemes && !empty($user->theme)) {
        //     $usertheme = theme_config::load($user->theme);
        //     if (!$usertheme->get_dark_mode_support()) {
        //         $darkmodemsg = '<div class=\'alert alert-warning\'>' . get_string('darkmodenotsupported', 'error', $usertheme->name) . '</div>';
        //         $mform->addElement('static', 'darkmodemsg', '', $darkmodemsg);
        //     }
        // }
        //     $coretheme = theme_config::load($CFG->theme);
        //     if (!$coretheme->get_dark_mode_support()) {
        //         $darkmodemsg = '<div class=\'alert alert-warning\'>' . get_string('darkmodenotsupported', 'error', $coretheme->name) . '</div>';
        //         $mform->addElement('static', 'darkmodemsg', '', $darkmodemsg);
        //     }


        // User override theme.
        if (!empty($CFG->allowuserthemes)) {
            $choices = [];
            $choices[''] = get_string('default');
            $themes = get_list_of_themes();
            foreach ($themes as $key => $theme) {
                if (empty($theme->hidefromselector)) {
                    $choices[$key] = get_string('pluginname', 'theme_'.$theme->name);
                }
            }
            $mform->addElement('select', 'theme', get_string('preferredtheme'), $choices);
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Extend the form definition after the data has been parsed.
     */
    public function definition_after_data(): void {
        global $CFG, $DB;

        $mform = $this->_form;

        $userid = $mform->getElementValue('id');
        $user = $DB->get_record('user', ['id' => $userid]);

        // User changing their preferred theme will delete the cache for this theme.
        $theme = $mform->getSubmitValue('theme');
        if ($mform->elementExists('theme') && $mform->isSubmitted()) {
            if (!empty($user) && ($theme != $user->theme)) {
                theme_delete_used_in_context_cache($theme, $user->theme);
            }
        }
    }

    /**
     * Validate form data and return errors (if any).
     *
     * @param array $data
     * @param array $files
     * @return array An array of errors
     */
    public function validation($data, $files): array {
        global $CFG;

        $errors = [];

        // Check for dark mode compatibility of the incoming user theme.
        if (!empty($data['preference_theme_darkmode']) && !empty($data['theme'])) {
            $usertheme = theme_config::load($data['theme']);
            if(!$usertheme->get_dark_mode_support()) {
                $errors['theme'] = get_string('darkmodenotsupported', 'error', $usertheme->name);
            }
        } else {
            // Check for dark mode compatibility of the core theme.
            if (!empty($data['preference_theme_darkmode'])) {
                $coretheme = theme_config::load($CFG->theme);
                if(!$coretheme->get_dark_mode_support()) {
                    $errors['theme'] = get_string('darkmodenotsupported', 'error', $coretheme->name);
                }
            }
        }


        return $errors;
    }
}
