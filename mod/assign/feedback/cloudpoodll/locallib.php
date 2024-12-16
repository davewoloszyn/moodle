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
 * This file contains the definition for the library class for comment feedback plugin.
 *
 * @package   assignfeedback_cloudpoodll
 * @copyright 2019 Justin Hunt {@link https://poodll.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignfeedback_cloudpoodll\constants;
use assignfeedback_cloudpoodll\utils;
use assignfeedback_cloudpoodll\aitranscriptutils;

class assign_feedback_cloudpoodll extends assign_feedback_plugin {
    /**
     * @var array map of submission type and recording type.
     */
    const SUBTYPEMAP_ALL = [
        constants::REC_AUDIO => constants::SUBMISSIONTYPE_AUDIO,
        constants::REC_VIDEO => constants::SUBMISSIONTYPE_VIDEO,
        constants::REC_SCREEN => constants::SUBMISSIONTYPE_SCREEN,
        constants::REC_TEXT => constants::SUBMISSIONTYPE_TEXT,
        constants::REC_CORRECTIONS => constants::SUBMISSIONTYPE_CORRECTIONS,
    ];
    const SUBTYPEMAP_RECORDERS = [
        constants::REC_AUDIO => constants::SUBMISSIONTYPE_AUDIO,
        constants::REC_VIDEO => constants::SUBMISSIONTYPE_VIDEO,
        constants::REC_SCREEN => constants::SUBMISSIONTYPE_SCREEN
    ];

    #[\Override]
    public function is_enabled(): bool {
        return $this->get_config('enabled') && $this->is_configurable();
    }

    #[\Override]
    public function is_configurable(): bool {
        $context = context_course::instance($this->assignment->get_course()->id);
        if ($this->get_config('enabled')) {
            return true;
        }
        if (!has_capability('assignfeedback/' .  constants::M_SUBPLUGIN . ':use', $context)) {
            return false;
        }
        return parent::is_configurable();
    }

    #[\Override]
    public function get_name(): string {
        if(get_config(constants::M_COMPONENT,'customname')){
            return get_config(constants::M_COMPONENT,'customname');
        }else {
            return get_string('pluginname', constants::M_COMPONENT);
        }
    }

    /**
     * DW: Add a description here.
     *
     * @return array DW: Add a description here.
     */
    public function get_allfeedbacks(): array {
        // DW: You are taking in $grade but not doing anything with it. Do you need this argument?
        global $DB;
        return $DB->get_records(constants::M_TABLE, compact('grade'), 'id',
                'type,id,filename,transcript,fulltranscript,vttdata,feedbacktext,submittedtext,correctedtext');
    }

    /**
     * Has the comment feedback been modified?
     *
     * @param stdClass $grade The grade object.
     * @param stdClass $data Data from the form submission.
     * @return boolean True if the comment feedback has been modified, else false.
     */
    #[\Override]
    public function is_feedback_modified(stdClass $grade, stdClass $data): bool {
        $allsubtypes = $this->get_all_subtypes();
        if (!empty($grade) && !empty($allsubtypes)) {
            $allfeedbacks = $this->get_allfeedbacks($grade->id);
            foreach ($allsubtypes as $subtypeconst) {
                $subtypeselected = !empty($data->recorders) && !empty($data->recorders[$subtypeconst]);
                $filename = !empty($data->filename) && !empty($data->filename[$subtypeconst]) ? $data->filename[$subtypeconst] : '';
                // DW: evaluating $subtypeselected as a bool.
                if ($subtypeselected) {
                    if (!empty($allfeedbacks[$subtypeconst])) {
                        return true;
                    }
                } else if (in_array($subtypeconst, self::SUBTYPEMAP_RECORDERS)) {
                    if ($allfeedbacks[$subtypeconst]->filename != $filename) {
                        return true;
                    }
                } else if ($subtypeconst == constants::SUBMISSIONTYPE_TEXT) {
                    if ($allfeedbacks[$subtypeconst]->feedbacktext != $data->feedbacktext) {
                        return true;
                    }
                } else if ($subtypeconst == constants::SUBMISSIONTYPE_CORRECTIONS) {
                    if ($allfeedbacks[$subtypeconst]->correctedtext != $data->correctedtext ||
                        $allfeedbacks[$subtypeconst]->suggestedtext != $data->suggestedtext) {
                        return true;
                    }
                }
            }
            return false;
        }
        return true;
    }

    // DW: We can remove supports_quickgrading as the parent is already returning false.

    /**
     * Return a list of the text fields that can be imported/exported by this plugin.
     *
     * @return array An array of field names and descriptions. (name=>description, ...)
     */
    #[\Override]
    public function get_editor_fields(): array {
        return ['cloudpoodll' => get_string('pluginname', constants::M_COMPONENT)];
    }

    // DW: Removed save_quickgrading_changes as it was completely commented out.

    /**
     * Save the settings for feedback cloudpoodll plugin
     *
     * @param stdClass $data DW: Add description here.
     * @return bool
     */
    #[\Override]
    public function save_settings(stdClass $data): bool {
        // Recorder type.
        $this->set_config('recordertype', $data->{constants::M_COMPONENT . '_recordertype'});
        // Recorder skin.
        $this->set_config('recorderskin', $data->{constants::M_COMPONENT . '_recorderskin'});

        // If we have a time limit, set it.
        if (isset($data->{constants::M_COMPONENT . '_timelimit'})) {
            $this->set_config('timelimit', $data->{constants::M_COMPONENT . '_timelimit'});
        } else {
            $this->set_config('timelimit', 0);
        }
        // Expire days.
        $this->set_config('expiredays', $data->{constants::M_COMPONENT . '_expiredays'});
        // Language.
        $this->set_config('language', $data->{constants::M_COMPONENT . '_language'});
        // Trancribe.
        $this->set_config('enabletranscription', $data->{constants::M_COMPONENT . '_enabletranscription'});
        // Transcode.
        $this->set_config('enabletranscode', $data->{constants::M_COMPONENT . '_enabletranscode'});
        // Player type.
        $this->set_config('playertype', $data->{constants::M_COMPONENT . '_playertype'});
        // Player type student.
        $this->set_config('playertypestudent', $data->{constants::M_COMPONENT . '_playertypestudent'});
        // Corrections language.
        $this->set_config('correctionslanguage', $data->{constants::M_COMPONENT . '_correctionslanguage'});

        return true;
    }

    /**
     * Get the default setting for feedback cloudpoodll plugin
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    #[\Override]
    public function get_settings(MoodleQuickForm $mform): void {
        global $CFG, $COURSE;

        $adminconfig = get_config(constants::M_COMPONENT);
        $recordertype = $this->get_config('recordertype') ? $this->get_config('recordertype') : $adminconfig->defaultrecorder;
        $recorderskin = $this->get_config('recorderskin') ? $this->get_config('recorderskin') : constants::SKIN_BMR;
        $timelimit = $this->get_config('timelimit') ? $this->get_config('timelimit') : 0;
        $expiredays = $this->get_config('expiredays') ? $this->get_config('expiredays') : $adminconfig->expiredays;
        $language = $this->get_config('language') ? $this->get_config('language') : $adminconfig->language;
        $correctionslanguage = $this->get_config('correctionslanguage') ? $this->get_config('correctionslanguage') :
                $adminconfig->correctionslanguage;
        $playertype = $this->get_config('playertype') ? $this->get_config('playertype') : $adminconfig->defaultplayertype;
        $playertypestudent = $this->get_config('playertypestudent') ? $this->get_config('playertypestudent') :
                $adminconfig->defaultplayertypestudent;
        $enabletranscription = $this->get_config('enabletranscription') ? $this->get_config('enabletranscription') :
                $adminconfig->enabletranscription;

        // Show a divider to keep settings manageable.
        $pluginname = get_string('pluginname', constants::M_COMPONENT);
        $customname = get_config(constants::M_COMPONENT, 'customname');
        if(!empty($customname)){
            $args = new stdClass();
            $args->pluginname = $pluginname;
            $args->customname = $customname;
            $divider = get_string('customdivider', constants::M_COMPONENT, $args);
        }else{
            $divider = get_string('divider', constants::M_COMPONENT, $pluginname);
        }

        // DW: Removing this Moodle 3.4 check.

        $recoptions = utils::fetch_options_recorders($adminconfig->awsregion);
        $mform->addElement('select', constants::M_COMPONENT . '_recordertype', get_string('recordertype', constants::M_COMPONENT),
                $recoptions);
        $mform->setDefault(constants::M_COMPONENT . '_recordertype', $recordertype);
        $mform->disabledIf(constants::M_COMPONENT . '_recordertype', constants::M_COMPONENT . '_enabled', 'notchecked');

        $skinoptions = utils::fetch_options_skins();
        $mform->addElement('select', constants::M_COMPONENT . '_recorderskin', get_string('recorderskin', constants::M_COMPONENT),
                $skinoptions);
        $mform->setDefault(constants::M_COMPONENT . '_recorderskin', $recorderskin);
        $mform->disabledIf(constants::M_COMPONENT . '_recorderskin', constants::M_COMPONENT . '_enabled', 'notchecked');

        // Add a place to set a maximum recording time.
        $mform->addElement('duration', constants::M_COMPONENT . '_timelimit', get_string('timelimit', constants::M_COMPONENT));
        $mform->setDefault(constants::M_COMPONENT . '_timelimit', $timelimit);
        $mform->disabledIf(constants::M_COMPONENT . '_timelimit', constants::M_COMPONENT . '_enabled', 'notchecked');

        // Add expire days.
        $expireoptions = utils::get_expiredays_options();
        $mform->addElement('select', constants::M_COMPONENT . '_expiredays', get_string("expiredays", constants::M_COMPONENT),
                $expireoptions);
        $mform->setDefault(constants::M_COMPONENT . '_expiredays', $expiredays);
        $mform->disabledIf(constants::M_COMPONENT . '_expiredays', constants::M_COMPONENT . '_enabled', 'notchecked');

        // Transcode settings. Hardcoded to always transcode.
        $mform->addElement('hidden', constants::M_COMPONENT . '_enabletranscode', 1);
        $mform->setType(constants::M_COMPONENT . '_enabletranscode', PARAM_INT);

        // Transcription settings.
        // Here add googlecloudspeech or amazontranscrobe options.
        $transcriberoptions = utils::get_transcriber_options();
        $mform->addElement('select', constants::M_COMPONENT . '_enabletranscription',
                get_string("enabletranscription", constants::M_COMPONENT), $transcriberoptions);
        $mform->setDefault(constants::M_COMPONENT . '_enabletranscription', $enabletranscription);
        $mform->disabledIf(constants::M_COMPONENT . '_enabletranscription', constants::M_COMPONENT . '_enabled', 'notchecked');

        // Lang options.
        $langoptions = utils::get_lang_options();
        $mform->addElement('select', constants::M_COMPONENT . '_language', get_string("language", constants::M_COMPONENT),
                $langoptions);
        $mform->setDefault(constants::M_COMPONENT . '_language', $language);
        $mform->disabledIf(constants::M_COMPONENT . '_language', constants::M_COMPONENT . '_enabled', 'notchecked');
        $mform->disabledIf(constants::M_COMPONENT . '_language', constants::M_COMPONENT . '_enabletranscription', 'eq', 0);

        // Player type : teacher.
        $playertypeoptions = utils::fetch_options_interactivetranscript();
        $mform->addElement('select', constants::M_COMPONENT . '_playertype', get_string("playertype", constants::M_COMPONENT),
                $playertypeoptions);
        $mform->setDefault(constants::M_COMPONENT . '_playertype', $playertype);
        $mform->disabledIf(constants::M_COMPONENT . '_playertype', constants::M_COMPONENT . '_enabled', 'notchecked');
        $mform->disabledIf(constants::M_COMPONENT . '_playertype', constants::M_COMPONENT . '_enabletranscription', 'eq', 0);

        // Player type: student.
        $playertypeoptions = utils::fetch_options_interactivetranscript();
        $mform->addElement('select', constants::M_COMPONENT . '_playertypestudent',
                get_string("playertypestudent", constants::M_COMPONENT), $playertypeoptions);
        $mform->setDefault(constants::M_COMPONENT . '_playertypestudent', $playertypestudent);
        $mform->disabledIf(constants::M_COMPONENT . '_playertypestudent', constants::M_COMPONENT . '_enabled', 'notchecked');
        $mform->disabledIf(constants::M_COMPONENT . '_playertypestudent', constants::M_COMPONENT . '_enabletranscription', 'eq', 0);

        // Corrections language options.
        $mform->addElement('select', constants::M_COMPONENT . '_correctionslanguage', get_string("correctionslanguage",
                constants::M_COMPONENT),$langoptions);
        $mform->setDefault(constants::M_COMPONENT . '_correctionslanguage', $correctionslanguage);
        $mform->disabledIf(constants::M_COMPONENT . '_correctionslanguage', constants::M_COMPONENT . '_enabled', 'notchecked');

        // Hide elements when we need to.
        $mform->hideIf(constants::M_COMPONENT . '_recordertype', constants::M_COMPONENT . '_enabled', 'notchecked');
        $mform->hideIf(constants::M_COMPONENT . '_recorderskin', constants::M_COMPONENT . '_enabled', 'notchecked');
        $mform->hideIf(constants::M_COMPONENT . '_timelimit', constants::M_COMPONENT . '_enabled', 'notchecked');
        $mform->hideIf(constants::M_COMPONENT . '_expiredays', constants::M_COMPONENT . '_enabled', 'notchecked');
        $mform->hideIf(constants::M_COMPONENT . '_enabletranscription', constants::M_COMPONENT . '_enabled', 'notchecked');
        $mform->hideIf(constants::M_COMPONENT . '_language', constants::M_COMPONENT . '_enabled', 'notchecked');
        $mform->hideIf(constants::M_COMPONENT . '_playertype', constants::M_COMPONENT . '_enabled', 'notchecked');
        $mform->hideIf(constants::M_COMPONENT . '_playertypestudent', constants::M_COMPONENT . '_enabled', 'notchecked');
        $mform->hideIf(constants::M_COMPONENT . '_correctionslanguage', constants::M_COMPONENT . '_enabled', 'notchecked');
    }
    /**
     * Get form elements for the grading page.
     *
     * @param stdClass|null $grade
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool true if elements were added to the form.
     */
    #[\Override]
    public function get_form_elements_for_user($grade, MoodleQuickForm $mform, stdClass $data, $userid): bool {

        $feedbackcloudpoodll = [];

        if ($grade) {
            $feedbackcloudpoodll = $this->get_allfeedbacks($grade->id);
        }

        $this->fetch_cloudpoodll_feedback_form($mform, $feedbackcloudpoodll);

        return true;
    }

    /**
     * DW: Add description here.
     *
     * @return array
     */
    public function get_all_subtypes(): array {
        $selectedsubtype = $this->get_config('recordertype');
        $allsubtypes = []; // DW: I just simplified the array assignment in here.

        if ($selectedsubtype == constants::REC_FREE) {
            $allsubtypes[]=constants::SUBMISSIONTYPE_AUDIO;
            $allsubtypes[]=constants::SUBMISSIONTYPE_VIDEO;
            $allsubtypes[]=constants::SUBMISSIONTYPE_TEXT;
            $allsubtypes[]=constants::SUBMISSIONTYPE_CORRECTIONS;
            $awsregion=get_config(constants::M_COMPONENT, 'awsregion');
            if(utils::can_use_loom($awsregion)) {
                $allsubtypes[] = constants::SUBMISSIONTYPE_SCREEN;
            }

        } else if (array_key_exists($selectedsubtype, self::SUBTYPEMAP_ALL)) {
            $allsubtypes[] = self::SUBTYPEMAP_ALL[$selectedsubtype];
        }

        return $allsubtypes;
    }

    /**
     * DW: Add description here.
     *
     * @param MoodleQuickForm $mform DW: Add description here.
     * @param array $feedbackcloudpoodll DW: Add description here.
     */
    public function fetch_cloudpoodll_feedback_form(MoodleQuickForm $mform, array $feedbackcloudpoodll = []): void {
        global $CFG, $USER, $PAGE;

        $allsubtypes = $this->get_all_subtypes();
        if (empty($allsubtypes)) {
            return;
        }

        // Get recorder on-screen title.
        $displayname = get_config(constants::M_COMPONENT, 'customname');
        if (empty($displayname)) {
            $displayname = get_string('recorderdisplayname', constants::M_COMPONENT);
        }

        // Get our renderers.
        $renderer = $PAGE->get_renderer(constants::M_COMPONENT);

        // Fetch API token.
        $apiuser = get_config(constants::M_COMPONENT, 'apiuser');
        $apisecret = get_config(constants::M_COMPONENT, 'apisecret');
        $groupelements = $formelements = $formdata = [];

        // If there is only one subtype then let's show it by default. Flag that here.
        $showbydefault = count($allsubtypes) === 1 ? true : false;

        foreach ($allsubtypes as $subtypeconst) {
            $subtypefeedback = !empty($feedbackcloudpoodll[$subtypeconst]) ? $feedbackcloudpoodll[$subtypeconst] : null;

            switch ($subtypeconst) {
                case constants::SUBMISSIONTYPE_AUDIO:
                case constants::SUBMISSIONTYPE_VIDEO:

                    // Prepare the AMD javascript for deletesubmission and showing the recorder.
                    $subtypename = array_flip(self::SUBTYPEMAP_RECORDERS)[$subtypeconst];
                    $opts = [
                        'component' => constants::M_COMPONENT,
                        'subtype' => '_' . $subtypename
                    ];
                    $hassubmission = !empty($subtypefeedback) && !empty($subtypefeedback->filename);

                    // Output our hidden field which has the filename.
                    $hiddeninputattrs['id'] = str_replace(constants::M_COMPONENT, constants::M_COMPONENT . $opts['subtype'],
                            constants::ID_UPDATE_CONTROL);
                    $mform->addElement('hidden', constants::NAME_UPDATE_CONTROL . '[' . $subtypeconst . ']', '', $hiddeninputattrs);
                    $mform->setType(constants::NAME_UPDATE_CONTROL . '[' . $subtypeconst . ']', PARAM_TEXT);

                    $extraclasses = 'fa togglerecorder toggle' . $subtypename;
                    $extraclasses .= ($subtypeconst == constants::SUBMISSIONTYPE_AUDIO) ? ' fa-microphone' : ' fa-video-camera';
                    if ($hassubmission || $showbydefault) {
                        $extraclasses .= ' enabledstate';
                        $formdata[constants::NAME_UPDATE_CONTROL . '[' . $subtypeconst . ']'] = $subtypefeedback->filename;
                        $formdata['recorders[' . $subtypeconst . ']'] = 1;
                    }
                    $groupelements[] = $mform->createElement('checkbox', $subtypeconst, null, null,
                            ['class' => $extraclasses, 'id' => constants::M_COMPONENT . $opts['subtype'] . '_recorder',
                            'data-target' => '#feedbackcontainer' . $opts['subtype'], 'data-action' => 'toggle']);

                    // Recorder data.
                    $roptions = new stdClass();
                    $roptions->recordertype = $subtypename;
                    $roptions->subtype = $opts['subtype'];
                    $roptions->recorderskin = $this->get_config('recorderskin');
                    $roptions->timelimit = $this->get_config('timelimit');
                    $roptions->expiredays = $this->get_config('expiredays');
                    $roptions->transcode = 1; // Hardcoded to always transcode.
                    $roptions->transcribe = $this->get_config('enabletranscription');
                    $roptions->language = $this->get_config('language');
                    $roptions->awsregion = get_config(constants::M_COMPONENT, 'awsregion');
                    $roptions->fallback = get_config(constants::M_COMPONENT, 'fallback');

                    // Check user has entered creds.
                    if (empty($apiuser) || empty($apisecret)) {
                        $message = get_string('nocredentials', constants::M_COMPONENT,
                                $CFG->wwwroot . constants::M_PLUGINSETTINGS);
                        $recorderbox = $renderer->show_problembox($message);
                    } else {
                        // Fetch token.
                        $token = !empty($token) ? $token : utils::fetch_token($apiuser, $apisecret);

                        // Check token authenticated and no errors in it.
                        $errormessage = utils::fetch_token_error($token);
                        if (!empty($errormessage)) {
                            $recorderbox = $renderer->show_problembox($errormessage);

                        } else {
                            // All good. So lets fetch recorder html.
                            $recorderbox = $renderer->fetch_recorder($roptions, $token);
                            $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/feedbackhelper', 'init', [$opts]);
                            $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/setuprecorder', 'init', [$opts]);
                        }
                    }

                    $recorderhtml = html_writer::div($recorderbox, '', ['id' => 'recordingtype' . $opts['subtype']]);

                    $recordertypeheading = get_string($subtypeconst == constants::SUBMISSIONTYPE_VIDEO ? 'recordervideo' :
                            'recorderaudio', constants::M_COMPONENT);

                    $hideorshow = ($hassubmission || $showbydefault) ? ' show' : '';
                    $formelements[] = $mform->createElement('html', html_writer::start_div(constants::M_COMPONENT .
                            '_feedbackcontainer collapse' . $hideorshow, ['id' => 'feedbackcontainer' . $opts['subtype']]) .
                            html_writer::tag('h5', $recordertypeheading));

                    if ($hassubmission) {
                        $deletefeedback = $renderer->fetch_delete_feedback($opts['subtype']);

                        // Show current submission.
                        // Show the previous response in a player or whatever and a delete button.
                        $feedbackplayer = $this->fetch_feedback_player($subtypefeedback);
                        $currentfeedback = $renderer->prepare_current_feedback($feedbackplayer, $deletefeedback, $opts['subtype']);

                        $formelements[] = $mform->createElement('static', 'currentfeedback' . $opts['subtype'],
                                get_string('currentfeedback', constants::M_COMPONENT), $currentfeedback);
                    }

                    $formelements[] = $mform->createElement('static', 'description' . $opts['subtype'], $recorderhtml);
                    $formelements[] = $mform->createElement('html', html_writer::end_div());

                    break;

                case constants::SUBMISSIONTYPE_TEXT:
                    $opts = [
                        'subtype' => constants::TYPE_TEXT
                    ];

                    $extraclasses = 'fa fa-pencil togglerecorder toggle' . $opts['subtype'];
                    if ($hassubmission = !empty($subtypefeedback)) {
                        $formdata[constants::TYPE_TEXT] = ['text' => $subtypefeedback->feedbacktext];
                        $formdata['recorders[' . $subtypeconst . ']'] = 1;
                        $extraclasses .= ' enabledstate';
                    }
                    $groupelements[] = $mform->createElement('checkbox', $subtypeconst, null, null,
                            ['class' => $extraclasses, 'id' => constants::M_COMPONENT . $opts['subtype'] . '_recorder',
                            'data-target' => '#feedbackcontainer' . $opts['subtype'], 'data-action' => 'toggle']);
                    $formelements[] = $mform->createElement('html',
                            html_writer::start_div(constants::M_COMPONENT . '_feedbackcontainer collapse' .
                            ($hassubmission ? ' show' : ''), ['id' => 'feedbackcontainer' . $opts['subtype']]) .
                            html_writer::tag('h5', get_string('recorderfeedbacktext', constants::M_COMPONENT)));
                    $formelements[] = $mform->createElement('editor', constants::TYPE_TEXT, null, 'rows="5" cols="240"',
                            ['enable_filemanagement' => false]);
                    $formelements[] = $mform->createElement('html', html_writer::end_div());

                    break;

                case constants::SUBMISSIONTYPE_CORRECTIONS:
                    $opts = [
                        'subtype' => constants::TYPE_CORRECTIONS
                    ];

                    $extraclasses = 'fa fa-strikethrough togglerecorder toggle' . $opts['subtype'];
                    if ($hassubmission = !empty($subtypefeedback)) {
                        $formdata['submittedtext'] =  $subtypefeedback->submittedtext;
                        $formdata['correctedtext'] =  $subtypefeedback->correctedtext;
                        $formdata['recorders[' . $subtypeconst . ']'] = 1;
                        $extraclasses .= ' enabledstate';
                    }
                    $groupelements[] = $mform->createElement('checkbox', $subtypeconst, null, null,
                            ['class' => $extraclasses, 'id' => constants::M_COMPONENT . $opts['subtype'] . '_recorder',
                            'data-target' => '#feedbackcontainer' . $opts['subtype'], 'data-action' => 'toggle']);
                    $formelements[] = $mform->createElement('html',
                            html_writer::start_div(constants::M_COMPONENT . '_feedbackcontainer collapse' .
                            ($hassubmission ? ' show' : ''), ['id' => 'feedbackcontainer' . $opts['subtype']]) .
                            html_writer::tag('h5', get_string('recorderfeedbackcorrections', constants::M_COMPONENT)));

                    // Submitted text textarea.
                    $s_instructions = $renderer->render_from_template(constants::M_COMPONENT . '/correctionsinstructions',
                            ['instructions'=>get_string('submittedtext_instructions', constants::M_COMPONENT),
                            'extraclass' => 'asf_cp_submittedta']);
                    $formelements[] = $mform->createElement('static', 'asf_cp_instructions1', $s_instructions);

                    // Action buttons for submissions.
                    $subbuttonopts = [];
                    $subbutton = $renderer->render_from_template(constants::M_COMPONENT . '/fetchsubmissionbutton',$subbuttonopts);
                    $formelements[] = $mform->createElement('static', 'asf_cp_sub_actionbuttons', $subbutton);
                    $formelements[] = $mform->createElement('textarea', 'submittedtext', null,
                            'wrap="virtual" rows="8" cols="100"'); // DW: I think these attribures should  be an array (need to confirm).

                    // Action buttons for corrections.
                    $language = $this->get_config('correctionslanguage');
                    if (empty($language)){
                        $language = constants::M_LANG_ENUS;
                    }
                    $corrbuttonopts = ['language' => $language];
                    $corrbutton = $renderer->render_from_template(constants::M_COMPONENT .
                            '/fetchcorrectionsbutton',$corrbuttonopts);
                    $formelements[] = $mform->createElement('static', 'asf_cp_corr_actionbuttons', $corrbutton);

                    // Corrected text textarea.
                    $c_instructions = $renderer->render_from_template(constants::M_COMPONENT . '/correctionsinstructions',
                           ['instructions' => get_string('correctedtext_instructions', constants::M_COMPONENT),
                           'extraclass' => 'asf_cp_correctedta']);
                    $formelements[] = $mform->createElement('static', 'asf_cp_instructions2', $c_instructions);
                    $formelements[] = $mform->createElement('textarea', 'correctedtext', null,
                            'wrap="virtual" rows="8" cols="100"'); // DW: I think these attribures should  be an array (need to confirm).
                    $correctionspreview = $renderer->render_from_template(constants::M_COMPONENT . '/correctionspreview', []);
                    $formelements[] = $mform->createElement('static', 'asf_cp_correctionspreview', $correctionspreview);
                    // End of collapsible div.
                    $formelements[] = $mform->createElement('html', html_writer::end_div());
                    $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/grammarsuggestions', 'init', []);
                    $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/previewcorrections', 'init', []);
                    $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/fetchsubmission', 'init', []);

                    break;

                case constants::SUBMISSIONTYPE_SCREEN:
                    $subtypename = array_flip(self::SUBTYPEMAP_RECORDERS)[constants::SUBMISSIONTYPE_SCREEN];
                    $opts = [
                        'component' => constants::M_COMPONENT,
                        'subtype' => '_' .  $subtypename
                    ];
                    $extraclasses = 'fa fa-desktop togglerecorder toggle' . $opts['subtype'];
                    if ($hassubmission = !empty($subtypefeedback) && !empty($subtypefeedback->filename)) {
                        $formdata[constants::NAME_UPDATE_CONTROL . '[' . $subtypeconst . ']'] = $subtypefeedback->filename;
                        $formdata['recorders[' . $subtypeconst . ']'] = 1;
                        $extraclasses .= ' enabledstate';
                    }
                    $groupelements[] = $mform->createElement('checkbox', $subtypeconst, null, null,
                            ['class' => $extraclasses, 'id' => constants::M_COMPONENT . $opts['subtype'] . '_recorder',
                            'data-target' => '#feedbackcontainer' . $opts['subtype'], 'data-action' => 'toggle']);
                    $formelements[] = $mform->createElement('html',
                            html_writer::start_div(constants::M_COMPONENT . '_feedbackcontainer collapse' .
                            ($hassubmission ? ' show' : ''), ['id' => 'feedbackcontainer' . $opts['subtype']]) .
                            html_writer::tag('h5', get_string('recorderscreen', constants::M_COMPONENT)));

                    if ($hassubmission) {
                        $deletefeedback = $renderer->fetch_delete_feedback($opts['subtype']);

                        // Show current submission.
                        // Show the previous response in a player or whatever and a delete button.
                        $opts['mediaurl'] = $subtypefeedback->filename;
                        $loomplayer = $renderer->render_from_template(constants::M_COMPONENT . '/loomplayer', $opts);
                        $currentfeedback = $renderer->prepare_current_feedback($loomplayer, $deletefeedback, $opts['subtype']);
                        $PAGE->requires->js_call_amd(constants::M_COMPONENT . "/feedbackhelper", 'init', [$opts]);

                        $formelements[] = $mform->createElement('static', 'currentfeedback' . $opts['subtype'],
                                get_string('currentfeedback', constants::M_COMPONENT), $currentfeedback);
                    }

                    // Output our hidden field which has the filename.
                    $hiddeninputattrs['id'] = str_replace(constants::M_COMPONENT, constants::M_COMPONENT .
                            $opts['subtype'], constants::ID_UPDATE_CONTROL);
                    $mform->addElement('hidden', constants::NAME_UPDATE_CONTROL . '[' . $subtypeconst . ']', '', $hiddeninputattrs);
                    $mform->setType(constants::NAME_UPDATE_CONTROL . '[' . $subtypeconst . ']', PARAM_TEXT);

                    // Loom Launcher.
                    if (utils::can_use_loom($region)) {
                        $token = utils::fetch_token($apiuser, $apisecret);
                        $region = get_config(constants::M_COMPONENT, 'awsregion');
                        $loomtoken = utils::fetch_loom_token($token, $region);
                        $loomappopts = [
                            'jws' => $loomtoken,
                            'videourlfield' => $hiddeninputattrs['id'],
                        ];
                        $loomapp = $renderer->render_from_template(constants::M_COMPONENT . '/loomapp', $loomappopts);
                        $formelements[] = $mform->createElement('static', 'loomapp', $loomapp);

                    } else {
                        $loomunavailable = $renderer->render_from_template(constants::M_COMPONENT . '/loomunavailable',
                                ['region' => $region]);
                        $formelements[] = $mform->createElement('static', 'loomapp', $loomunavailable);
                    }
                    // Close the toggle panel html div.
                    $formelements[] = $mform->createElement('html', html_writer::end_div());

                    break;
            }
        }

        if (!empty($groupelements)) {
            $mform->addGroup($groupelements, 'recorders', '', '', true);
        }

        if (!empty($formelements)) {
            foreach ($formelements as $formelement) {
                $mform->addElement($formelement);
            }
            foreach ($formdata as $elname => $elvalue) {
                $mform->setDefault($elname, $elvalue);
            }
        }

        $PAGE->requires->strings_for_js(['reallydeletefeedback', 'clicktohide', 'clicktoshow'], constants::M_COMPONENT);
        $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/feedbackhelper', 'registerToggler', []);

        // DW: I removed the return of 'true' as it doesn't look needed.
    }

    /**
     * Saving the cloud poodll content into database.
     *
     * @param stdClass $grade
     * @param stdClass $data
     * @return bool
     */
    #[\Override]
    public function save(stdClass $grade, stdClass $data): bool {
        global $DB;

        $allsubtypes = $this->get_all_subtypes();
        $allfeedbacks = $this->get_allfeedbacks($grade->id);
        $allsavedok = true;

        // Get expiretime of this record.
        $fileexpiry = 0;
        $expiredays = $this->get_config("expiredays");
        if ($expiredays < 9999) {
            $fileexpiry = time() + DAYSECS * $expiredays;
        }

        // Though it's a bit naff, we will loop through all the subtypes and save them one by one as separate feedback records.
        foreach ($allsubtypes as $subtypeconst) {
            $savedok = false;
            $thefeedback = !empty($allfeedbacks[$subtypeconst]) ? $allfeedbacks[$subtypeconst] : null;
            $subtypeselected = !empty($data->recorders) && !empty($data->recorders[$subtypeconst]);
            $filename = !empty($data->filename) && !empty($data->filename[$subtypeconst]) ? $data->filename[$subtypeconst] : '';
            // Recorded feedback - audio / video / screen.
            if (in_array($subtypeconst, self::SUBTYPEMAP_RECORDERS)) {
                if (!empty($thefeedback)) {
                    if ($filename == '-1' || !$subtypeselected) {
                        // This is a flag to delete the feedback.
                        $DB->delete_records(constants::M_TABLE, ['id' => $thefeedback->id]);
                        continue;
                    } else {
                        $thefeedback->{constants::NAME_UPDATE_CONTROL} = $filename;
                        $thefeedback->fileexpiry = $fileexpiry;
                        $savedok = $DB->update_record(constants::M_TABLE, $thefeedback);
                    }
                } else if ($subtypeselected) {
                    $thefeedback = new stdClass();
                    $thefeedback->type = $subtypeconst;
                    $thefeedback->{constants::NAME_UPDATE_CONTROL} = $filename;
                    $thefeedback->fileexpiry = $fileexpiry;
                    $thefeedback->grade = $grade->id;
                    $thefeedback->assignment = $this->assignment->get_instance()->id;
                    $feedbackid = $DB->insert_record(constants::M_TABLE, $thefeedback);
                    if ($feedbackid > 0) {
                        $thefeedback->id = $feedbackid;
                        $savedok = true;
                    }
                } else {
                    continue;
                }
                if ($savedok) {
                    $this->register_fetch_transcript_task($thefeedback);
                }
            // Comments feedback.
            } else if ($subtypeconst == constants::SUBMISSIONTYPE_TEXT) {
                $feedbacktext = !empty($data->feedbacktext) ? $data->feedbacktext['text'] : '';
                if (empty($thefeedback)) {
                    if (empty($subtypeselected)) {
                        continue;
                    }
                    $thefeedback = new stdClass();
                    $thefeedback->type = $subtypeconst;
                    $thefeedback->grade = $grade->id;
                    $thefeedback->assignment = $this->assignment->get_instance()->id;
                    $thefeedback->feedbacktext = $feedbacktext;
                    $feedbackid = $DB->insert_record(constants::M_TABLE, $thefeedback);
                    if ($feedbackid > 0) {
                        $thefeedback->id = $feedbackid;
                        $savedok = true;
                    }
                } else if ($subtypeselected) {
                    $thefeedback->feedbacktext = $feedbacktext;
                    $savedok = $DB->update_record(constants::M_TABLE, $thefeedback);
                } else {
                    $DB->delete_records(constants::M_TABLE, ['id' => $thefeedback->id]);
                    $savedok = true;
                }
            // Corrections feedback.
            } else if ($subtypeconst == constants::SUBMISSIONTYPE_CORRECTIONS) {
                $submittedtext = !empty($data->submittedtext) ? $data->submittedtext : '';
                $correctedtext = !empty($data->correctedtext) ? $data->correctedtext : '';
                if (empty($thefeedback)) {
                    if (empty($subtypeselected)) {
                        continue;
                    }
                    $thefeedback = new stdClass();
                    $thefeedback->type = $subtypeconst;
                    $thefeedback->grade = $grade->id;
                    $thefeedback->assignment = $this->assignment->get_instance()->id;
                    $thefeedback->submittedtext = $submittedtext;
                    $thefeedback->correctedtext = $correctedtext;
                    $feedbackid = $DB->insert_record(constants::M_TABLE, $thefeedback);
                    if ($feedbackid > 0) {
                        $thefeedback->id = $feedbackid;
                        $savedok = true;
                    }
                } else if ($subtypeselected) {
                    $thefeedback->submittedtext = $submittedtext;
                    $thefeedback->correctedtext = $correctedtext;
                    $savedok = $DB->update_record(constants::M_TABLE, $thefeedback);
                } else {
                    $DB->delete_records(constants::M_TABLE, ['id' => $thefeedback->id]);
                    $savedok = true;
                }

            }

            // From here we either have a $thefeedback object with data ... or we do not.
            $allsavedok = $allsavedok && $savedok;
        }

        return $allsavedok;
    }

    /**
     * DW: Add description here.
     *
     * @param stdClass $cloudpoodllfeedback DW: Add description here.
     */
    public function register_fetch_transcript_task(stdClass $cloudpoodllfeedback): void {
        $fetchtask = new \assignfeedback_cloudpoodll\task\cloudpoodll_s3_adhoc();
        $fetchtask->set_component(constants::M_COMPONENT);

        $customdata = new \stdClass();
        $customdata->feedback = $cloudpoodllfeedback;
        $customdata->taskcreationtime = time();

        $fetchtask->set_custom_data($customdata);

        // Queue it.
        \core\task\manager::queue_adhoc_task($fetchtask, true);
    }

    /**
     * Display the comment in the feedback table.
     *
     * @param stdClass $grade
     * @param bool $showviewlink Set to true to show a link to view the full feedback.
     * @return string
     */
    #[\Override]
    public function view_summary(stdClass $grade, & $showviewlink): string {
        global $PAGE;

        $islist = optional_param('action', '', PARAM_TEXT) === 'grading';
        $showviewlink = $islist; // Is this a list page?

        // Get our renderers.
        $renderer = $PAGE->get_renderer(constants::M_COMPONENT);

        $feedbackcloudpoodll = $this->get_allfeedbacks($grade->id);
        if ($feedbackcloudpoodll) {
            $cellhtml = '';
            foreach ($this->get_all_subtypes() as $subtypeconst) {
                if (!empty($feedbackcloudpoodll[$subtypeconst])) {
                    $subtypefeedback = $feedbackcloudpoodll[$subtypeconst];
                    switch($subtypeconst) {
                        case constants::SUBMISSIONTYPE_VIDEO:
                        case constants::SUBMISSIONTYPE_AUDIO:
                            if (!empty($subtypefeedback->filename)) {
                                $recordertypeheading = get_string($subtypeconst == constants::SUBMISSIONTYPE_VIDEO ?
                                        'recordervideo' : 'recorderaudio', constants::M_COMPONENT);
                                $cellhtml .= html_writer::tag('h5', $recordertypeheading);
                                $cellhtml .= $this->fetch_feedback_player($subtypefeedback);
                            }
                            break;

                        case constants::SUBMISSIONTYPE_SCREEN:
                            //if it's a list, show a truncated version.
                            if($islist){
                                $cellhtml .= get_string('recorderscreen', constants::M_COMPONENT);
                                break;
                            }

                            $cellhtml .= html_writer::tag('h5', get_string('recorderscreen', constants::M_COMPONENT));
                            $opts = ['mediaurl' => $subtypefeedback->filename];
                            $loomplayer = $renderer->render_from_template(constants::M_COMPONENT . '/loomplayer', $opts);
                            $cellhtml .= $loomplayer;
                            break;

                        case constants::SUBMISSIONTYPE_TEXT:
                            // If it's a list, show a truncated version.
                            if ($islist){
                                $cellhtml .= shorten_text($subtypefeedback->feedbacktext, 70);
                                break;
                            }
                            $cellhtml .= html_writer::tag('h5', get_string('recorderfeedbacktext', constants::M_COMPONENT));
                            $cellhtml .= format_text($subtypefeedback->feedbacktext);
                            break;

                        case constants::SUBMISSIONTYPE_CORRECTIONS:
                            // If our text is empty we don't show it.
                            if(empty($subtypefeedback->submittedtext) || empty($subtypefeedback->correctedtext)) {
                                break;
                            }

                            // If its a list, show a truncated version.
                            if($islist){
                                $cellhtml .= shorten_text($subtypefeedback->correctedtext, 70);
                                break;
                            }

                            $correctionsopts = [];
                            $correctionsopts['submittedtext'] = aitranscriptutils::render_passage($subtypefeedback->submittedtext);
                            $correctionsopts['correctedtext'] = aitranscriptutils::render_passage($subtypefeedback->correctedtext,
                                    'corrected');
                            $cellhtml .= $renderer->render_from_template(constants::M_COMPONENT . '/correctionsfullsummary',
                                    $correctionsopts);

                            // Do js for corrections, which is where the mark up is applied.
                            $direction = 'r2l';
                            list($grammarerrors, $grammarmatches, $insertioncount) = utils::fetch_grammar_correction_diff(
                                    $subtypefeedback->submittedtext, $subtypefeedback->correctedtext, $direction);
                            // Here we set up any info we need to pass into javascript.
                            $correctionsopts = [];
                            $correctionsopts['sessionerrors'] = $grammarerrors; // These are words different from those in original.
                            $correctionsopts['sessionmatches'] = $grammarmatches; // These are words missing from the original.
                            $correctionsopts['insertioncount'] = $insertioncount;// Difference between "transcript" and "passage".
                            $correctionsopts['opts_id'] = 'assignfeedback_cloudpoodll_correctionopts';
                            $jsonstring = json_encode($correctionsopts);
                            $opts_html = html_writer::tag('input', '', ['id' => $correctionsopts['opts_id'], 'type' => 'hidden',
                                    'value' => $jsonstring]);
                            $PAGE->requires->js_call_amd('assignfeedback_cloudpoodll/correctionsmarkup', 'init',
                                    [['id' => $correctionsopts['opts_id']]]);

                            // These need to be returned and echoed to the page.
                            $cellhtml .= $opts_html;
                            break;
                    }
                }
            }
            return $cellhtml;
        }
        return '';
    }

    /**
     * Display the recording in the feedback table.
     *
     * @param stdClass $grade
     * @return string
     */
    #[\Override]
    public function view(stdClass $grade): string {
        $showviewlink = false;
        return $this->view_summary($grade, $showviewlink);
    }

    /**
     * DW: Add a description here.
     *
     * @param stdClass $feedbackcloudpoodll DW: Add a description here.
     * @return string DW: Add a description here.
     */
    public function fetch_feedback_player(stdClass $feedbackcloudpoodll): string {
        global $PAGE, $OUTPUT;

        $playerstring = '';
        if ($feedbackcloudpoodll) { // DW: This won't ever be 'falsey'. What do you want to check here? Do you need it at all?
            // The path to any media file we should play.
            $filename = $feedbackcloudpoodll->filename;
            $rawmediapath = $feedbackcloudpoodll->filename;
            if (empty($feedbackcloudpoodll->vttdata)) {
                $vttdata = false;
            } else {
                $vttdata = $feedbackcloudpoodll->vttdata;
            }

            // Are we a person who can grade?
            $isgrader = has_capability('mod/assign:grade', $this->assignment->get_context());
            // Is this a list page?
            $islist = optional_param('action', '', PARAM_TEXT) === 'grading';
        } else {
            return '';
        }
        $recordertype = $this->get_config('recordertype');
        if ($recordertype == constants::REC_FREE) {
            $recordertype = array_flip(self::SUBTYPEMAP_ALL)[$feedbackcloudpoodll->type];
        }

        // Size params for our response players/images.
        // Audio is a simple 1 or 0 for display or not.
        $size = $this->fetch_player_size($recordertype);

        // Player type.
        $playertype = constants::PLAYERTYPE_DEFAULT;
        if ($vttdata && !$islist) {
            $playertype = $isgrader ? $this->get_config('playertype') : $this->get_config('playertypestudent');
        }

        // RTL for transcripts.
        // For right to left languages we want to add the RTL direction and right justify.
        switch ($this->get_config('language')) {
            case constants::M_LANG_ARAE:
            case constants::M_LANG_ARSA:
            case constants::M_LANG_FAIR:
            case constants::M_LANG_HEIL:
                $rtl = constants::M_COMPONENT. '_rtl';
                break;
            default:
                $rtl = '';
        }

        // If this is a playback area, for teacher, show a string if no file.
        if ((empty($filename) || strlen($filename) < 3)) {
            $playerstring .= '';

        } else {

            // Prepare our response string, which will parsed and replaced with the necessary player.
            switch ($recordertype) {

                case constants::REC_AUDIO:
                    // Get player.
                    $playerid = html_writer::random_id(constants::M_COMPONENT . '_');
                    $randomid = html_writer::random_id('cloudpoodll_');
                    $containerid = html_writer::random_id(constants::M_COMPONENT . '_');
                    $container = html_writer::div('', constants::M_COMPONENT . '_transcriptcontainer ' . $rtl,
                            ['id' => $containerid]);

                    $playeropts = [
                        'playerid' => $playerid ,
                        'mediaurl' => $rawmediapath . '?cachekiller=' . $randomid,
                        'vtturl' => $rawmediapath . '.vtt',
                        'lang' => $this->get_config('language'),
                    ];
                    if ($islist) {
                        $playeropts['islist'] = $islist;
                    }
                    $audioplayer = $OUTPUT->render_from_template(constants::M_COMPONENT . '/audioplayer', $playeropts);

                    if ($size) {
                        switch ($playertype) {
                            case constants::PLAYERTYPE_DEFAULT:
                                // Just use the raw audio tags response string.
                                $playerstring .= $audioplayer;
                                break;

                            case constants::PLAYERTYPE_INTERACTIVETRANSCRIPT:
                                $playerstring .= $audioplayer . $container;

                                // Prepare AMD javascript for displaying feedback.
                                $transcriptopts = [
                                    'component' => constants::M_COMPONENT,
                                    'playerid' => $playerid,
                                    'containerid' => $containerid,
                                    'cssprefix' => constants::M_COMPONENT . '_transcript',
                                ];
                                $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/interactivetranscript', 'init',
                                        [$transcriptopts]); // DW: Just noting we are putting an array in an array for some reason. Check on this.
                                $PAGE->requires->strings_for_js(['transcripttitle'], constants::M_COMPONENT);
                                break;

                            case constants::PLAYERTYPE_STANDARDTRANSCRIPT:
                                $playerstring .= $audioplayer . $container;
                                // prepare AMD javascript for displaying feedback.
                                $transcriptopts = [
                                    'component' => constants::M_COMPONENT, 'playerid' => $playerid,
                                    'containerid' => $containerid,
                                    'cssprefix' => constants::M_COMPONENT . '_transcript',
                                    'transcripturl' => $rawmediapath . '.txt',
                                ];
                                $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/standardtranscript', 'init',
                                        [$transcriptopts]); // DW: Just noting we are putting an array in an array for some reason. Check on this.
                                $PAGE->requires->strings_for_js(['transcripttitle'], constants::M_COMPONENT);
                                break;
                        }
                    } else {
                        $playerstring = get_string('audioplaceholder', constants::M_COMPONENT);
                    }
                    break;

                case constants::REC_VIDEO:
                    if ($size) {

                        $playerid = html_writer::random_id(constants::M_COMPONENT . '_');
                        $containerid = html_writer::random_id(constants::M_COMPONENT . '_');
                        $container = html_writer::div('', constants::M_COMPONENT . '_transcriptcontainer ' . $rtl,
                                ['id' => $containerid]);

                        // Player template.
                        $randomid = html_writer::random_id('cloudpoodll_');
                        $playeropts = [
                            'playerid' => $playerid,
                            'mediaurl' => $rawmediapath . '?cachekiller=' . $randomid,
                            'lang' => $this->get_config('language'),
                        ];
                        if ($islist) {
                            $playeropts['islist'] = $islist;
                        }

                        switch ($playertype) {
                            case constants::PLAYERTYPE_INTERACTIVETRANSCRIPT:
                                if ($size->width == 0) {
                                    $playerstring = get_string('videoplaceholder', constants::M_COMPONENT);
                                    break;
                                }
                                $playeropts['vtturl'] = $rawmediapath . '.vtt';
                                $videoplayer = $OUTPUT->render_from_template(constants::M_COMPONENT . '/videoplayer', $playeropts);
                                $playerstring .= $videoplayer . $container;

                                // Prepare AMD javascript for displaying feedback.
                                $transcriptopts = ['component' => constants::M_COMPONENT, 'playerid' => $playerid,
                                        'containerid' => $containerid, 'cssprefix' => constants::M_COMPONENT . '_transcript'];
                                $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/interactivetranscript', 'init',
                                        [$transcriptopts]); // DW: Just noting we are putting an array in an array for some reason. Check on this.
                                $PAGE->requires->strings_for_js(['transcripttitle'], constants::M_COMPONENT);
                                break;

                            case constants::PLAYERTYPE_DEFAULT:
                            default:
                                if ($size->width == 0) {
                                    $playerstring = get_string('videoplaceholder', constants::M_COMPONENT);
                                    break;
                                }

                                if ($vttdata) {
                                    $playeropts['vtturl'] = $rawmediapath . '.vtt';
                                }
                                $videoplayer = $OUTPUT->render_from_template(constants::M_COMPONENT . '/videoplayer', $playeropts);
                                $playerstring .= $videoplayer;
                        }
                    } else {
                        $playerstring = get_string('videoplaceholder', constants::M_COMPONENT);
                    }
                    break;

                default:
                    $playerstring .= format_text("<a href='$rawmediapath'>$filename</a>", FORMAT_HTML);
                    break;

            }
        }
        return $playerstring;
    }

    /**
     * DW: Add a description here.
     *
     * @param string $recordertype DW: Add a description here.
     * @return string DW: Add a description here.
     */
    public function fetch_player_size(string $recordertype): string {

        // is this a list view?
        $islist = false;

        // Build our sizes array.
        $sizes = array();
        $sizes['0'] = new stdClass();
        $sizes['0']->width = 0;
        $sizes['0']->height = 0;
        $sizes['160'] = new stdClass();
        $sizes['160']->width = 160;
        $sizes['160']->height = 120;
        $sizes['320'] = new stdClass();
        $sizes['320']->width = 320;
        $sizes['320']->height = 240;
        $sizes['480'] = new stdClass();
        $sizes['480']->width = 480;
        $sizes['480']->height = 360;
        $sizes['640'] = new stdClass();
        $sizes['640']->width = 640;
        $sizes['640']->height = 480;
        $sizes['800'] = new stdClass();
        $sizes['800']->width = 800;
        $sizes['800']->height = 600;
        $sizes['1024'] = new stdClass();
        $sizes['1024']->width = 1024;
        $sizes['1024']->height = 768;

        $size = $sizes[0];
        $config = get_config(constants::M_COMPONENT);

        // Prepare our response string, which will parsed and replaced with the necessary player.
        switch ($recordertype) {
            case constants::REC_VIDEO:
                $size = $islist ? $sizes[$config->displaysize_list] : $sizes[$config->displaysize_single];
                break;
            case constants::REC_AUDIO:
                $size = $islist ? $config->displayaudioplayer_list : $config->displayaudioplayer_single;
                break;
            default:
                break;

        }
        return $size;
    }

    // DW: We can remove can_upgrade as the parent is already returning false.

    /**
     * Upgrade the settings from the old assignment to the new plugin based one
     *
     * @param context $oldcontext - the context for the old assignment
     * @param stdClass $oldassignment - the data for the old assignment
     * @param string $log - can be appended to by the upgrade
     * @return bool was it a success? (false will trigger a rollback)
     */
    #[\Override]
    public function upgrade_settings(context $oldcontext, stdClass $oldassignment, & $log): bool {
        return true;
    }

    /**
     * Upgrade the feedback from the old assignment to the new one
     *
     * @param context $oldcontext - the database for the old assignment context
     * @param stdClass $oldassignment The data record for the old assignment
     * @param stdClass $oldsubmission The data record for the old submission
     * @param stdClass $grade The data record for the new grade
     * @param string $log Record upgrade messages in the log
     * @return bool true or false - false will trigger a rollback
     */
    #[\Override]
    public function upgrade(
        context $oldcontext,
        stdClass $oldassignment,
        stdClass $oldsubmission,
        stdClass $grade,
        & $log
    ): bool {
        return true;
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     */
    #[\Override]
    public function delete_instance(): bool {
        global $DB;
        // Will throw exception on failure.
        $DB->delete_records(constants::M_TABLE, ['assignment' => $this->assignment->get_instance()->id]);
        return true;
    }

    /**
     * Returns true if there are no feedback cloudpoodll for the given grade.
     *
     * @param stdClass $grade
     * @return bool
     */
    #[\Override]
    public function is_empty(stdClass $grade): bool {
        return $this->view($grade) === '';
    }
}
