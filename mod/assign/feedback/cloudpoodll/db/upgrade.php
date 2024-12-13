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
 * Upgrade code for the feedback_cloudpoodll module.
 *
 * @package   assignfeedback_cloudpoodll
 * @copyright 2019 Justin Hunt {@link https://poodll.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Stub for upgrade code
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_assignfeedback_cloudpoodll_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Moodle v2.8.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.9.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v3.0.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v3.1.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.2.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2022100701) {

        require_once($CFG->libdir . '/filelib.php');

        // Define field type to be added to assignfeedback_cloudpoodll.
        $table = new xmldb_table('assignfeedback_cloudpoodll');
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'grade');

        // Conditionally launch add field type.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field feedbacktext to be added to assignfeedback_cloudpoodll.
        $table = new xmldb_table('assignfeedback_cloudpoodll');
        $field = new xmldb_field('feedbacktext', XMLDB_TYPE_TEXT, null, null, null, null, null );

        // Conditionally launch add field feedbacktext.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $feedbackset = $DB->get_recordset_sql('SELECT id,filename from {assignfeedback_cloudpoodll}');
        foreach ($feedbackset as $feedback) {
            if (!empty($feedback->filename)) {
                $mimetype = mimeinfo('type', $feedback->filename);
                switch($mimetype) {
                    case 'video/mp4':
                        $DB->set_field('assignfeedback_cloudpoodll', 'type',
                            \assignfeedback_cloudpoodll\constants::SUBMISSIONTYPE_VIDEO, ['id' => $feedback->id]);
                        break;
                    case 'audio/mp3':
                        $DB->set_field('assignfeedback_cloudpoodll', 'type',
                            \assignfeedback_cloudpoodll\constants::SUBMISSIONTYPE_AUDIO, ['id' => $feedback->id]);
                        break;
                }
            }
        }
        $feedbackset->close();

        // Cloudpoodll savepoint reached.
        upgrade_plugin_savepoint(true, 2022100701, 'assignfeedback', 'cloudpoodll');
    }

    if ($oldversion < 2022100702) {

        // Define key uniqgradesubtype (unique) to be added to assignfeedback_cloudpoodll.
        $table = new xmldb_table('assignfeedback_cloudpoodll');
        $key = new xmldb_key('uniqgradesubtype', XMLDB_KEY_UNIQUE, ['grade', 'type']);

        // Launch add key uniqgradesubtype.
        $dbman->add_key($table, $key);

        // Cloudpoodll savepoint reached.
        upgrade_plugin_savepoint(true, 2022100702, 'assignfeedback', 'cloudpoodll');
    }

    if($oldversion < 2024020701){
        // Define field feedbacktext to be added to assignfeedback_cloudpoodll.
        $table = new xmldb_table('assignfeedback_cloudpoodll');
        $fields=[];
        $fields[] = new xmldb_field('submittedtext', XMLDB_TYPE_TEXT, null, null, null, null, null );
        $fields[] = new xmldb_field('correctedtext', XMLDB_TYPE_TEXT, null, null, null, null, null);

        foreach($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Cloudpoodll savepoint reached.
        upgrade_plugin_savepoint(true, 2024020701, 'assignfeedback', 'cloudpoodll');
    }

    return true;
}
