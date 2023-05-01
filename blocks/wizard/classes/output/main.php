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
 * Class containing data for Wizard block.
 *
 * @package    block_wizard
 * @copyright  2023 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_wizard\output;
defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use context_user;

/**
 * Class containing data for Wizard block.
 *
 * @package    block_wizard
 * @copyright  2023 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main implements renderable, templatable {

    /** @var int id of the block_wizard record */
    public $id = 0;

    /** @var int id of the wizard being undertaken */
    public $wizardid = 0;

    /** @var array all the available wizards */
    public $wizards = [];

    /** @var array the current wizard */
    public $wizard = [];

    /** @var int the current step */
    public $step = 0;

    /** @var int is this wizard completed? */
    public $completed = 0;

    /** @var int show or hide the wizard menu */
    public $showmenu = 1;

    /** @var array the in progress wizards */
    public $inprogress = [];

    /** @var int the last wizard id that is in progress still */
    public $lastwizardid = 0;

    /** @var bool xx */
    public $validwizard = false;

    /** @var bool xx */
    public $capable = false;

    public function __construct() {

        $this->init();
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array{

        // Check for errors and let the user know.
        $errors = [];

        if (!$this->validwizard) {
            $errors[] = [
                'text' => 'Invalid wizard'
            ];
        }

        if (!$this->capable) {
            $errors[] = [
                'text' => 'Sorry, you do not have the permissions to use this wizard.'
            ];
        }

        if (count($errors) > 0) {
            $templatedata['errors'] = $errors;
        }

        // Wizard menu items.
        $templatedata['wizardmenu'] = $this->get_wizard_menu_data();

        // Show wizard steps if we don't have any issues.
        if ($this->capable && $this->validwizard) {
            $templatedata['activewizard'] = $this->get_active_wizard_data();
        }

        return $templatedata;
    }

    /**
     * Run some setup methods to get things going.
     *
     * @return void
     */
    public function init() {

        // Set up all available wizards.
        $this->set_available_wizards();

        // Get the incoming wizard id (if set).
        $this->wizardid = intval(optional_param('wizardid', null, PARAM_INT));

        // If there is no active wizard, try to get the last uncompleted one.
        if ($this->wizardid === 0) {

            if($inprogresswizards = $this->get_in_progress_wizards()) {

                $lastwizard = array_pop($inprogresswizards);

                if(isset($lastwizard)) {

                    $this->wizardid = intval($lastwizard['wizardid']);
                }
            }
        }

        if ($this->validate_wizard($this->wizardid)) {
            $this->validwizard = true;
        }

        if($this->validwizard && $this->check_wizard_capability($this->wizardid)) {
            $this->capable = true;
        }

        if ($this->capable && $this->validwizard) {
           $this->set_active_wizard($this->wizardid);
           $this->init_record();
           $this->check_incoming_step();
        }

    }

    /**
     * Check the the current user has the correct capability to use this wizard.
     *
     * @return bool
     */
    public function check_wizard_capability(int $id): bool {
        global $USER;

        $iscapable = false;
        $wizard = $this->get_wizard($id);

        if ($USER->id !== 0) {

            $context = context_user::instance($USER->id);
            if (has_capability($wizard['capability'], $context)) {
                $iscapable = true;
            }
        }

        return $iscapable;
    }

    /**
     * Initialise our DB records.
     *
     * @return void
     */
    public function init_record() {
        global $DB, $USER;

        // Existing record.
        if ($record = $DB->get_record('block_wizard', ['wizardid' => $this->wizardid, 'userid' => $USER->id])) {

            $this->id = $record->id;
            $this->step = $record->step;
            //$this->completed = $record->completed;

        // New record.
        } else{

            $this->create_record();
        }
    }

    /**
     * Create a wizard record in the DB.
     *
     * @return void
     */
    public function create_record(): void {
        global $DB, $USER;

        $data = [
            'wizardid' => $this->wizardid,
            'userid' => $USER->id,
            'step' => 0,
            'completed' => 0,
            'timemodified' => time(),
        ];

        $this->id = $DB->insert_record('block_wizard', $data);
    }

    /**
     * Update a wizard record in the DB.
     *
     * @return void
     */
    public function update_record(): void {
        global $DB;

        $data = [
            'id' => $this->id,
            'step' => $this->step,
            'completed' => $this->completed,
            'timemodified' => time(),
        ];

        $DB->update_record('block_wizard', $data);
    }

    /**
     * Get a list of all the wizards that have not yet been completed.
     *
     * @return array
     */
    public function get_in_progress_wizards(): array{
        global $DB, $USER;

        $data = [];

        if ($results = $DB->get_records('block_wizard', ['completed' => 0, 'userid' => $USER->id], 'timemodified')) {

            foreach($results as $result) {

                $this->step = $result->step;
                $wizard = $this->get_wizard($result->wizardid);

                $temp = [];
                $temp = (array)$result;
                $temp['url'] = $this->which_url($result->wizardid, 'inprogress');
                $temp['title'] = $wizard['title'];

                $data[] = $temp;
            }
        }

        return $data;
    }

    /**
     * Is this wizard at the start?
     *
     * @return boolean
     */
    public function is_start(): bool{
        return ($this->step == 0);
    }

    /**
     * Check incoming params that indicate an action has taken place.
     *
     * @return void
     */
    public function check_incoming_step(){

        // Grab the incoming params from the url.
        $wizardid = intval(optional_param('wizardid', null, PARAM_INT));
        $wizardstep = intval(optional_param('wizardstep', null, PARAM_INT));
        $wizardcomplete = intval(optional_param('wizardcomplete', null, PARAM_INT));

        // Validate incoming params in case they have been fiddled with.
        if (!$this->validate_wizard($wizardid) || !$this->validate_step($wizardstep)) {
            return;
        }

        // Remember the entryurl for this wizard so we can return them at the end.
        if ($wizardstep == 0) {
            global $PAGE;
            $_SESSION['entryurl'] = $PAGE->url;
        }

        // Completed a wizard.
        if (!empty($wizardcomplete)) {
            $this->step = 0;
            $this->completed = 1;
            $this->update_record();
            return;
        }

        // Staring a new wizard.
        if ($wizardstep == 1) {
            $this->step = $wizardstep;
            $this->completed = 0;
            $this->update_record();

        // All other steps.
        } else {
            $this->step = $wizardstep;
            $this->update_record();
        }
    }

    /**
     * Check if a wizard exists.
     *
     * @param integer $wizardid
     * @return boolean
     */
    public function validate_wizard(int $wizardid): bool {
        return array_key_exists($wizardid, $this->wizards);
    }

    /**
     * Check if the step exists in the wizard.
     *
     * @param int $wizardstep
     * @return boolean
     */
    public function validate_step(int $wizardstep): bool {
        return array_key_exists($wizardstep, $this->wizard['steps']) || $wizardstep == 0;
    }

    /**
     * Get a particular wizard.
     *
     * @param int $id id of wizard you want to get
     * @return array
     */
    public function get_wizard(int $id): array {
        return $this->wizards[$id];
    }

    /**
     * Indicate which wizard is active.
     *
     * @return void
     */
    public function set_active_wizard(int $id): void {
        $this->wizard = $this->wizards[$id];
    }

    /**
     * Check if a wizard has been completed by comparing steps.
     *
     * @return boolean
     */
    public function is_final_step(): bool{

        // if (!$this->validate_wizard($this->wizardid)) {
        //     return false;
        // }

        return ($this->step >= count($this->wizard['steps'])) ? true : false;
    }

    /**
     * Get the button text to show.
     *
     * @param $type the button text will depend on the type provided
     * @return string
     */
    public function which_button($type): string{

        switch ($type) {

            case 'next':

                if ($this->is_final_step()) {
                    $button = 'Finish';
                } else if ($this->is_start()) {
                    $button = 'Start';
                } else {
                    $button = 'Next';
                }
                break;

            case 'prev':
                if (!$this->is_start()) {
                    $button = 'Back';
                }
                break;
        }

        return isset($button) ? $button : '';
    }

    /**
     * Decide which url is needed based on the params provided and completion.
     *
     * @param integer $id the wizard id
     * @param string $type the type will depend on which url we will provide
     * @return string
     */
    public function which_url(int $id, string $type): string {
        global $PAGE;

        $complete = 0;
        $step = 0;
        $baseurl = null;

        switch ($type) {

            case 'menu':
                $step = 0;
                $baseurl = $PAGE->url;
                break;

            case 'inprogress':
                $step = $this->step;
                break;

            case 'nextstep':

                if ($this->is_final_step()) {
                    $complete = 1;
                    if (isset($_SESSION['entryurl'])) {
                        $baseurl = $_SESSION['entryurl'];
                    } else {
                        $baseurl = $PAGE->url;
                    }

                } else {

                    $step = $this->step + 1;
                    $wizard = $this->get_wizard($id);
                    $baseurl = $wizard['steps'][$step]['url'];
                }

                break;

            case 'prevstep':
                $step = $this->step - 1;
                $wizard = $this->get_wizard($id);
                $baseurl = $wizard['steps'][$step]['url'];
                break;
        }

        // Only return a url if we have something to work with.
        return ($baseurl) ? $this->build_url($id, $step, $complete, $baseurl)->out(false) : '';

    }

    /**
     * Build a url for use with the current wizard step.
     *
     * @param integer $id wizard id
     * @param integer $step incoming step for url
     * @param integer $complete incoming complete flag for url
     * @param string|null $baseurl override the url with this value
     * @return \moodle_url
     */
    public function build_url(int $id, int $step = 0, int $complete = 0, string|null $baseurl = null): \moodle_url{

        $params = [
            'wizardid' => $id,
            'wizardstep' => $step,
            'wizardcomplete' => $complete,
        ];

        return new \moodle_url($baseurl, $params);
    }

    /**
     * Get the current step's sub-steps.
     *
     * @return array
     */
    public function which_substeps(): array{

        return $this->wizard['steps'][$this->step]['substeps'] ?? [];
    }

    /**
     * Build the sub-steps array.
     *
     * @return array
     */
    public function build_substeps(): array{

        $substeps = [];
        $substepscount = $this->wizard['steps'][$this->step]['substepscount'];

        if ($substepscount > 0) {
            for ($i=1; $i <= $substepscount; $i++) {
                $locator = 'w' . $this->wizardid . '_s' . $this->step . '_ss' . $i;
                $substeps[]['text'] = get_string($locator, 'block/wizard');
            }
        }

        return $substeps;
    }

    /**
     * Get the data to be used in the dropdown menu of the tempplate.
     *
     * @return array
     */
    public function get_wizard_menu_data(): array{

        $data = [];

        foreach ($this->wizards as $key => $wizard) {

            $temp = [];
            $temp['title'] = $wizard['title'];
            $temp['description'] = $wizard['description'];
            $temp['url'] = $this->which_url($key, 'menu');

            $data[] = $temp;
        }

        return $data;
    }

    /**
     * Get the data to be used in displaying the wizard steps.
     *
     * @return array
     */
    public function get_active_wizard_data(): array{

        $data = [];

        if ($this->validwizard && $this->completed == 0) {

            $data = [
                'id' => $this->wizardid,
                'title' => $this->wizard['title'],
                'description' => $this->wizard['description'],
                'step' => $this->step,
                'steptotal' => count($this->wizard['steps']),
                'completed' => $this->completed,
                'start' => $this->is_start(),
                'substeps' => $this->build_substeps(),
                'nexturl' => $this->which_url($this->wizardid, 'nextstep'),
                'prevurl' => $this->which_url($this->wizardid, 'prevstep'),
                'nextlabel' => $this->which_button('next'),
                'prevlabel' => $this->which_button('prev'),
            ];

        }

        return $data;
    }

    /**
     * Set up all wizard data (current hard-coded).
     *
     * @return void
     */
    public function set_available_wizards() {
        global $CFG;

        $this->wizards = [
            // oAuth 2 (Google)
            1 => [
                'title' => get_string('w1_title', 'block/wizard'),
                'description' => get_string('w1_description', 'block/wizard'),
                'capability' => 'moodle/site:config',
                'steps' => [
                    1 => [
                        'url' => $CFG->wwwroot . '/admin/settings.php?section=manageauths',
                        'substepscount' => 1,
                    ],
                    2 => [
                        'url' => $CFG->wwwroot . '/admin/tool/oauth2/issuers.php',
                        'substepscount' => 3,
                    ]
                ]
            ],
            // MNet
            2 => [
                'title' => get_string('w2_title', 'block/wizard'),
                'description' => get_string('w2_description', 'block/wizard'),
                'capability' => 'moodle/site:config',
                'steps' => [
                    1 => [
                        'url' => $CFG->wwwroot . '/admin/environment.php',
                        'substepscount' => 3,
                    ],
                    2 => [
                        'url' => $CFG->wwwroot . '/admin/search.php?query=mnet_dispatcher_mode',
                        'substepscount' => 2,
                    ],
                    3 => [
                        'url' => $CFG->wwwroot . '/admin/search.php?query=sessioncookie',
                        'substepscount' => 3,
                    ],
                    4 => [
                        'url' => $CFG->wwwroot . '/admin/mnet/peers.php',
                        'substepscount' => 5,
                    ],
                    5 => [
                        'url' => $CFG->wwwroot . '/admin/settings.php?section=manageauths',
                        'substepscount' => 2,
                    ],
                    6 => [
                        'url' => $CFG->wwwroot . '/admin/mnet/peers.php',
                        'substepscount' => 5,
                    ],
                    7 => [
                        'url' => $CFG->wwwroot . '/admin/roles/manage.php',
                        'substepscount' => 5,
                    ],
                    8 => [
                        'url' => $CFG->wwwroot . '/?redirect=0',
                        'substepscount' => 3,
                    ],
                    9 => [
                        'url' => $CFG->wwwroot . '/?redirect=0',
                        'substepscount' => 1,
                    ],
                ]
            ],
            // Enrol users into a course
            3 => [
                'title' => get_string('w3_title', 'block/wizard'),
                'description' => get_string('w3_description', 'block/wizard'),
                'capability' => 'moodle/site:config',//moodle/course:update
                'steps' => [
                    1 => [
                        'url' => $CFG->wwwroot . '/admin/user.php',
                        'substepscount' => 4,
                    ],
                    2 => [
                        'url' => $CFG->wwwroot . '/admin/search.php#linkcourses',
                        'substepscount' => 3,
                    ],
                    3 => [
                        'url' => null,
                        'substepscount' => 7,
                    ],
                ]
            ],
        ];
    }

}