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
 *
 *
 * @package   assignfeedback_cloudpoodll
 * @copyright 2018 Justin Hunt {@link http://www.poodll.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignfeedback_cloudpoodll;

defined('MOODLE_INTERNAL') || die();

class constants {

    const M_COMPONENT = 'assignfeedback_cloudpoodll';

    const M_CLASS='asf_cp';
    const M_TABLE = 'assignfeedback_cloudpoodll';
    const M_FILEAREA = 'feedbacks_cloudpoodll';
    const M_URL = '/mod/assign/feedback/cloudpoodll';
    const M_SUBPLUGIN = 'cloudpoodll';
    const M_PLUGINSETTINGS = '/admin/settings.php?section=assignfeedback_cloudpoodll';

    const APPID = 'assignfeedback_cloudpoodll';

    const REC_FREE = 'free';
    const REC_AUDIO = 'audio';
    const REC_VIDEO = 'video';

    const REC_TEXT = 'text';
    const REC_SCREEN = 'screen';
    const REC_CORRECTIONS = 'corrections';

    const SKIN_PLAIN = 'standard';
    const SKIN_BMR = 'bmr';
    const SKIN_123 = 'onetwothree';
    const SKIN_FRESH = 'fresh';
    const SKIN_ONCE = 'once';
    const SKIN_UPLOAD = 'upload';
    const SKIN_SCREEN = 'screen';

    const FALLBACK_UPLOAD = 'upload';
    const FALLBACK_IOSUPLOAD = 'iosupload';
    const FALLBACK_WARNING = 'warning';

    const PLAYERTYPE_DEFAULT = 'default';
    const PLAYERTYPE_INTERACTIVETRANSCRIPT = 'transcript';
    const PLAYERTYPE_STANDARDTRANSCRIPT = 'standardtranscript';

    const CLASS_REC_CONTAINER = 'assignfeedback_cloudpoodll_rec_cont';
    const CLASS_REC_OUTER = 'assignfeedback_cloudpoodll_rec_outer';
    const ID_REC = 'assignfeedback_cloudpoodll_therecorder';
    const ID_UPDATE_CONTROL = 'assignfeedback_cloudpoodll_updatecontrol';
    const NAME_UPDATE_CONTROL = 'filename';

    const REGION_USEAST1 = 'useast1';
    const REGION_TOKYO = 'tokyo';
    const REGION_DUBLIN = 'dublin';
    const REGION_SYDNEY = 'sydney';
    const REGION_OTTAWA = 'ottawa';
    const REGION_SAOPAULO = 'saopaulo';
    const REGION_FRANKFURT = 'frankfurt';
    const REGION_LONDON = 'london';
    const REGION_SINGAPORE = 'singapore';
    const REGION_MUMBAI = 'mumbai';
    const REGION_CAPETOWN = 'capetown';
    const REGION_BAHRAIN = 'bahrain';

    const TRANSCRIBER_NONE = 0;
    const TRANSCRIBER_AMAZONTRANSCRIBE = 1;
    const TRANSCRIBER_GOOGLECLOUDSPEECH = 2;

    const M_LANG_ENUS = 'en-US';
    const M_LANG_ENGB = 'en-GB';
    const M_LANG_ENAU = 'en-AU';
    const M_LANG_ESUS = 'es-US';
    const M_LANG_FRCA = 'fr-CA';
    const M_LANG_FRFR = 'fr-FR';
    const M_LANG_ITIT = 'it-IT';
    const M_LANG_PTBR = 'pt-BR';
    const M_LANG_KOKR = 'ko-KR';
    const M_LANG_DEDE = 'de-DE';
    const M_LANG_HIIN = 'hi-IN';
    const M_LANG_ENIN = 'en-IN';
    const M_LANG_ESES = 'es-ES';

    const M_LANG_ARAE ='ar-AE';
    const M_LANG_ARSA ='ar-SA';
    const M_LANG_ZHCN ='zh-CN';
    const M_LANG_NLNL ='nl-NL';
    const M_LANG_ENIE ='en-IE';
    const M_LANG_ENWL ='en-WL';
    const M_LANG_ENAB ='en-AB';
    const M_LANG_FAIR ='fa-IR';
    const M_LANG_DECH ='de-CH';
    const M_LANG_HEIL ='he-IL';
    const M_LANG_IDID ='id-ID';
    const M_LANG_JAJP ='ja-JP';
    const M_LANG_MSMY ='ms-MY';
    const M_LANG_PTPT ='pt-PT';
    const M_LANG_RURU ='ru-RU';
    const M_LANG_TAIN ='ta-IN';
    const M_LANG_TEIN ='te-IN';
    const M_LANG_TRTR ='tr-TR';

    const M_LANG_NONO ='no-NO';
    const M_LANG_NBNO ='nb-NO';
    const M_LANG_PLPL ='pl-PL';
    const M_LANG_RORO ='ro-RO';
    const M_LANG_SVSE ='sv-SE';
    const M_LANG_UKUA ='uk-UA';
    const M_LANG_EUES ='eu-ES';
    const M_LANG_FIFI ='fi-FI';
    const M_LANG_HUHU ='hu-HU';
    const M_LANG_MINZ ='mi-NZ';
    const M_LANG_BGBG = 'bg-BG';
    const M_LANG_CSCZ = 'cs-CZ';
    const M_LANG_ELGR = 'el-GR';
    const M_LANG_HRHR = 'hr-HR';
    const M_LANG_LTLT = 'lt-LT';
    const M_LANG_LVLV = 'lv-LV';
    const M_LANG_SKSK = 'sk-SK';
    const M_LANG_SLSI = 'sl-SI';
    const M_LANG_ISIS = 'is-IS';
    const M_LANG_MKMK = 'mk-MK';
    const M_LANG_SRRS = 'sr-RS';
    const M_LANG_VIVN ='vi-VN';

    const SUBMISSIONTYPE_UNCLASSIFIED = 0;
    const SUBMISSIONTYPE_VIDEO = 1;
    const SUBMISSIONTYPE_AUDIO = 2;
    const SUBMISSIONTYPE_TEXT = 3;
    const SUBMISSIONTYPE_SCREEN = 4;
    const SUBMISSIONTYPE_CORRECTIONS = 5;

    const TYPE_TEXT = 'feedbacktext';
    const TYPE_SCREEN = 'feedbackscreen';
    const TYPE_CORRECTIONS = 'feedbackcorrections';
}