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
 * This file defines the admin settings for this plugin
 *
 * @package   assignfeedback_cloudpoodll
 * @copyright 2019 Justin Hunt {@link https://poodll.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignfeedback_cloudpoodll\constants;
use assignfeedback_cloudpoodll\utils;

// enable by default.
$settings->add(new admin_setting_configcheckbox(constants::M_COMPONENT . '/default',
        get_string('default', constants::M_COMPONENT),
        get_string('default_help', constants::M_COMPONENT), 0));

$settings->add(new admin_setting_configtext(constants::M_COMPONENT . '/apiuser',
        get_string('apiuser', constants::M_COMPONENT), get_string('apiuser_details', constants::M_COMPONENT), '', PARAM_TEXT));


$cloudpoodll_apiuser=get_config(constants::M_COMPONENT,'apiuser');
$cloudpoodll_apisecret=get_config(constants::M_COMPONENT,'apisecret');
$show_below_apisecret='';
//if we have an API user and secret we fetch token
if(!empty($cloudpoodll_apiuser) && !empty($cloudpoodll_apisecret)) {
    $tokeninfo = utils::fetch_token_for_display($cloudpoodll_apiuser,$cloudpoodll_apisecret);
    $show_below_apisecret=$tokeninfo;
//if we have no API user and secret we show a "fetch from elsewhere on site" or "take a free trial" link
}else{
    $amddata=['apppath'=>$CFG->wwwroot . '/' .constants::M_URL];
    $cp_components=['filter_poodll','qtype_cloudpoodll','mod_readaloud','mod_wordcards','mod_solo','mod_minilesson','mod_englishcentral','mod_pchat',
        'atto_cloudpoodll','tinymce_cloudpoodll', 'assignsubmission_cloudpoodll','assignfeedback_cloudpoodll'];
    foreach($cp_components as $cp_component){
        switch($cp_component){
            case 'filter_poodll':
                $apiusersetting='cpapiuser';
                $apisecretsetting='cpapisecret';
                break;
            case 'mod_englishcentral':
                $apiusersetting='poodllapiuser';
                $apisecretsetting='poodllapisecret';
                break;
            default:
                $apiusersetting='apiuser';
                $apisecretsetting='apisecret';
        }
        $cloudpoodll_apiuser=get_config($cp_component,$apiusersetting);
        if(!empty($cloudpoodll_apiuser)){
            $cloudpoodll_apisecret=get_config($cp_component,$apisecretsetting);
            if(!empty($cloudpoodll_apisecret)){
                $amddata['apiuser']=$cloudpoodll_apiuser;
                $amddata['apisecret']=$cloudpoodll_apisecret;
                break;
            }
        }
    }
    $show_below_apisecret=$OUTPUT->render_from_template( constants::M_COMPONENT . '/managecreds',$amddata);
}

// get_string('apisecret_details', constants::M_COMPONENT).
$settings->add(new admin_setting_configtext(constants::M_COMPONENT . '/apisecret',
        get_string('apisecret', constants::M_COMPONENT), $show_below_apisecret, '', PARAM_TEXT));

$regions = utils::get_region_options();
$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/awsregion',
        get_string('awsregion', constants::M_COMPONENT),
        get_string('loomunavailable', constants::M_COMPONENT), constants::REGION_USEAST1, $regions));

$expiredays = utils::get_expiredays_options();
$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/expiredays',
        get_string('expiredays', constants::M_COMPONENT), '', '365', $expiredays));

// transcode settings.
$settings->add(new admin_setting_configcheckbox(constants::M_COMPONENT . '/enabletranscode',
        get_string('enabletranscode', constants::M_COMPONENT), get_string('enabletranscode_details', constants::M_COMPONENT), 1));

// player type.
$playertype_options = utils::fetch_options_interactivetranscript();
$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/defaultplayertype',
        get_string('defaultplayertype', constants::M_COMPONENT),
        get_string('defaultplayertypedetails', constants::M_COMPONENT), constants::PLAYERTYPE_INTERACTIVETRANSCRIPT,
        $playertype_options));

// student player type.
$playertype_options = utils::fetch_options_interactivetranscript();
$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/defaultplayertypestudent',
        get_string('defaultplayertypestudent', constants::M_COMPONENT),
        get_string('defaultplayertypedetails', constants::M_COMPONENT), constants::PLAYERTYPE_INTERACTIVETRANSCRIPT,
        $playertype_options));

// transcription settings.
$transcriber_options = utils::get_transcriber_options();
$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/enabletranscription',
        get_string('enabletranscription_details', constants::M_COMPONENT), '', constants::TRANSCRIBER_NONE,
        $transcriber_options));

$langoptions = utils::get_lang_options();
$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/language', get_string('language', constants::M_COMPONENT),
        '', 'en-US', $langoptions));

// Default recorders.
$rec_options = utils::fetch_options_recorders();
$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/defaultrecorder',
        get_string('defaultrecorder', constants::M_COMPONENT),
        get_string('defaultrecorderdetails', constants::M_COMPONENT), constants::REC_AUDIO, $rec_options));

//corrections language
$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/correctionslanguage',
    get_string('correctionslanguage', constants::M_COMPONENT),
    get_string('correctionslanguagedetails', constants::M_COMPONENT), 'en-US', $langoptions));

// Default html5 fallback.
$fallback_options = utils::fetch_options_fallback();
$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/fallback',
        get_string('fallback', constants::M_COMPONENT),
        get_string('fallbackdetails', constants::M_COMPONENT), constants::FALLBACK_IOSUPLOAD, $fallback_options));

// show current submission on submission form.
$yesno_options = array(0 => get_string("no", constants::M_COMPONENT),
        1 => get_string("yes", constants::M_COMPONENT));
/*
$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/showcurrentsubmission',
        get_string('showcurrentsubmission', constants::M_COMPONENT),
        get_string('showcurrentsubmissiondetails', constants::M_COMPONENT), 1, $yesno_options));
*/

//allow user to set a custom name for the plugin as displayed to users
$settings->add(new admin_setting_configtext(constants::M_COMPONENT . '/customname',
        get_string('customname', constants::M_COMPONENT),
        get_string('customnamedetails', constants::M_COMPONENT),
        '', PARAM_TEXT));

// Settings for audio recordings.
$settings->add(new admin_setting_heading(constants::M_COMPONENT . '/audio_heading',
        get_string('setting_audio_heading', constants::M_COMPONENT),
        get_string('setting_audio_heading_details', constants::M_COMPONENT)));

$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/displayaudioplayer_single',
        get_string('displayaudioplayersingle', constants::M_COMPONENT),
        '', '1', $yesno_options));

$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/displayaudioplayer_list',
        get_string('displayaudioplayerlist', constants::M_COMPONENT),
        '', '1', $yesno_options));

// Settings for video recordings.
$settings->add(new admin_setting_heading(constants::M_COMPONENT . '/video_heading',
        get_string('setting_video_heading', constants::M_COMPONENT),
        get_string('setting_video_heading_details', constants::M_COMPONENT)));

// The size of the video player on the various screens.
$size_options = array('0' => get_string('shownovideo', constants::M_COMPONENT),
        '160' => '160x120', '320' => '320x240', '480' => '480x360',
        '640' => '640x480', '800' => '800x600', '1024' => '1024x768');

$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/displaysize_single',
        get_string('displaysizesingle', constants::M_COMPONENT),
        get_string('displaysizesingledetails', constants::M_COMPONENT), '320', $size_options));

$settings->add(new admin_setting_configselect(constants::M_COMPONENT . '/displaysize_list',
        get_string('displaysizelist', constants::M_COMPONENT),
        get_string('displaysizelistdetails', constants::M_COMPONENT), '0', $size_options));
