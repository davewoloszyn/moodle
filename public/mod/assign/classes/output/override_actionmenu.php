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
 * Output the override actionbar for this activity.
 *
 * @package   mod_assign
 * @copyright 2021 Adrian Greeve <adrian@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_assign\output;

use core_availability\info_module;
use moodle_url;
use templatable;
use renderable;
use core\output\select_menu;

/**
 * Output the override actionbar for this activity.
 *
 * @package   mod_assign
 * @copyright 2021 Adrian Greeve <adrian@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class override_actionmenu implements templatable, renderable {

    /** @var moodle_url The current url for this page. */
    protected $currenturl;
    /** @var \cm_info course module information */
    protected $cm;
    /** @var bool Can all groups be accessed */
    protected $canaccessallgroups;
    /** @var array Groups related to this activity */
    protected $groups;
    /** @var bool If the user has capabilities to list overrides. */
    private $canedit;
    /** @var string The mode passed for the overrides url. */
    private $mode;
    /** @var bool Should the add override button be enabled or disabled. */
    private $addenabled;

    /**
     * Constructor for this action menu.
     *
     * @param moodle_url $currenturl The current url for this page.
     * @param \cm_info $cm course module information.
     * @param string $mode The mode passed for the overrides url.
     */
    public function __construct(moodle_url $currenturl, \cm_info $cm, string $mode) {
        $this->currenturl = $currenturl;
        $this->cm = $cm;
        $groupmode = groups_get_activity_groupmode($this->cm);
        $this->canaccessallgroups = ($groupmode === NOGROUPS) ||
                has_capability('moodle/site:accessallgroups', $this->cm->context);
        $this->groups = $this->canaccessallgroups ? groups_get_all_groups($this->cm->course) :
                groups_get_activity_allowed_groups($this->cm);

        // Work out what else needs to be displayed.
        $this->addenabled = true;
        $this->canedit = has_capability('mod/quiz:manageoverrides', $this->cm->context);
        if ($this->canedit) {
            if ($groupmode) {
                if (empty($this->groups)) {
                    // There are no groups.
                    $this->addenabled = false;
                }
            } else {
                $users = get_enrolled_users($this->cm->context);

                $info = new \core_availability\info_module($cm);
                $users = $info->filter_user_list($users);

                if ((empty($users) && $mode === 'user') && (empty($this->groups) && $mode === 'groups')) {
                    // There are no students and we tackled the user override selector or
                    // there are no groups and we tackled the group override selector.
                    $this->addenabled = false;
                }
            }
        }
        $this->mode = $mode;
    }

    /**
     * Whether to show groups or not. Assignments can be have group overrides if there are groups available in the course.
     * There is no restriction related to the assignment group setting.
     *
     * @return bool
     */
    protected function show_groups(): bool {
        if ($this->canaccessallgroups) {
            $groups = groups_get_all_groups($this->cm->course);
        } else {
            $groups = groups_get_activity_allowed_groups($this->cm);
        }
        return !(empty($groups));
    }

    /**
     * Whether to enable/disable user override button or not.
     *
     * @return bool
     */
    protected function show_useroverride(): bool {
        global $DB;
        $users = [];
        $context = $this->cm->context;
        if ($this->canaccessallgroups) {
            $users = get_enrolled_users($context, '', 0, 'u.id');
        } else if ($this->groups) {
            $enrolledjoin = get_enrolled_join($context, 'u.id');
            list($ingroupsql, $ingroupparams) = $DB->get_in_or_equal(array_keys($this->groups), SQL_PARAMS_NAMED);
            $params = $enrolledjoin->params + $ingroupparams;
            $sql = "SELECT u.id
                      FROM {user} u
                      JOIN {groups_members} gm ON gm.userid = u.id
                           {$enrolledjoin->joins}
                     WHERE gm.groupid $ingroupsql
                       AND {$enrolledjoin->wheres}";
            $users = $DB->get_records_sql($sql, $params);
        }

        $info = new info_module($this->cm);
        $users = $info->filter_user_list($users);

        return !empty($users);
    }

    /**
     * Create the add override button.
     *
     * @param \renderer_base $output an instance of the assign renderer.
     * @return \single_button the button, ready to render.
     */
    public function create_add_button(\renderer_base $output): \single_button {
        $addoverrideurl = new moodle_url(
            '/mod/assign/overrideedit.php',
            ['cmid' => $this->cm->id, 'action' => 'add' . $this->mode],
        );

        if ($this->mode === 'group') {
            $label = get_string('addnewgroupoverride', 'assign');
        } else {
            $label = get_string('addnewuseroverride', 'assign');
        }

        $addoverridebutton = new \single_button($addoverrideurl, $label, 'get', \single_button::BUTTON_PRIMARY);
        if (!$this->addenabled) {
            $addoverridebutton->disabled = true;
        }

        return $addoverridebutton;
    }

    /**
     * Export this object for template rendering.
     * @param \renderer_base $output the output renderer
     * @return array
     */
    public function export_for_template(\core\output\renderer_base $output): array {
        global $PAGE;
        $templatecontext = [];

        // Build the navigation drop-down.
        $useroverridesurl = new moodle_url('/mod/assign/overrides.php', ['cmid' => $this->cm->id, 'mode' => 'user']);
        $groupoverridesurl = new moodle_url('/mod/assign/overrides.php', ['cmid' => $this->cm->id, 'mode' => 'group']);

        $menu = [
            $useroverridesurl->out(false) => get_string('useroverrides', 'assign'),
            $groupoverridesurl->out(false) => get_string('groupoverrides', 'assign'),
        ];

        $overridesnav = new select_menu(
            'mod_assign_override_select',
            $menu,
            $PAGE->url->out(false),
        );
        $overridesnav->set_label(
            get_string('overrides', 'assign'),
            ['class' => 'visually-hidden']
        );

        $templatecontext['navigation'] = $overridesnav->export_for_template($output);

        // Build the add button - but only if the user can edit.
        if ($this->canedit) {
            $templatecontext['addoverridebutton'] = $this->create_add_button($output)->export_for_template($output);
        }

        return $templatecontext;
    }
}
