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
 * Grade Now for solo plugin
 *
 * @package    assignfeedback_cloudpoodll
 * @copyright  2019 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 namespace assignfeedback_cloudpoodll;
defined('MOODLE_INTERNAL') || die();

use assignfeedback_cloudpoodll\constants;


/**
 * AI transcript Functions used generally across this mod
 *
 * @package    assignfeedback_cloudpoodll
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aitranscriptutils {


    public static function render_passage($passage, $markuptype='passage') {
        // load the HTML document
        $doc = new \DOMDocument;
        // it will assume ISO-8859-1  encoding, so we need to hint it:
        // see: http://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly
        $safepassage = htmlspecialchars($passage, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        @$doc->loadHTML(mb_encode_numericentity($safepassage, [0x80, 0x10FFFF, 0, ~0], 'UTF-8'));

        // select all the text nodes
        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('//text()');

        // base CSS class
        if($markuptype == 'passage') {
            $cssword = constants::M_CLASS . '_grading_passageword';
            $cssspace = constants::M_CLASS . '_grading_passagespace';
        }else{
            $cssword = constants::M_CLASS . '_grading_correctionsword';
            $cssspace = constants::M_CLASS . '_grading_correctionsspace';
        }

        // original CSS classes
        // The original classes are to show the original passage word before or after the corrections word
        // because of the layout, "rewritten/added words" [corrections] will show in green, after the original words [red]
        // but "removed(omitted) words" [corrections] will show as a green space  after the original words [red]
        // so the span layout for each word in the corrections is:
        // [original_preword][correctionsword][original_postword][correctionsspace]
        // suggested word: (original)He eat apples => (corrected)He eats apples =>
        // [original_preword: "eat->"][correctionsword: "eats"][original_postword][correctionsspace]
        // removed(omitted) word: (original)He eat devours the apples=> (corrected)He devours the apples =>
        // [original_preword: ][correctionsword: "He"][original_postword: "eat->" ][correctionsspace: " "]

        $cssoriginalpreword = constants::M_CLASS . '_grading_original_preword';
        $cssoriginalpostword = constants::M_CLASS . '_grading_original_postword';

        // init the text count
        $wordcount = 0;
        foreach ($nodes as $node) {
            $trimmednode = trim($node->nodeValue);
            if (empty($trimmednode)) {
                continue;
            }

            // explode missed new lines that had been copied and pasted. eg A[newline]B was not split and was one word
            // This resulted in ai selected error words, having different index to their passage text counterpart
            $seperator = ' ';
            // $words = explode($seperator, $node->nodeValue);

            $nodevalue = self::lines_to_brs($node->nodeValue, $seperator);
            $words = preg_split('/\s+/', $nodevalue);

            foreach ($words as $word) {
                // if its a new line character from lines_to_brs we add it, but not as a word
                if ($word == '<br>') {
                    $newnode = $doc->createElement('br', $word);
                    $node->parentNode->appendChild($newnode);
                    continue;
                }

                $wordcount++;
                $newnode = $doc->createElement('span', $word);
                $spacenode = $doc->createElement('span', $seperator);
                // $newnode->appendChild($spacenode);
                // print_r($newnode);
                $newnode->setAttribute('id', $cssword . '_' . $wordcount);
                $newnode->setAttribute('data-wordnumber', $wordcount);
                $newnode->setAttribute('class', $cssword);
                $spacenode->setAttribute('id', $cssspace . '_' . $wordcount);
                $spacenode->setAttribute('data-wordnumber', $wordcount);
                $spacenode->setAttribute('class', $cssspace);
                // original pre node
                if($markuptype !== 'passage'){
                    $originalprenode = $doc->createElement('span', '');
                    $originalprenode->setAttribute('id', $cssoriginalpreword . '_' . $wordcount);
                    $originalprenode->setAttribute('data-wordnumber', $wordcount);
                    $originalprenode->setAttribute('class', $cssoriginalpreword);

                }
                // original post node
                if($markuptype !== 'passage'){
                    $originalpostnode = $doc->createElement('span', '');
                    $originalpostnode->setAttribute('id', $cssoriginalpostword . '_' . $wordcount);
                    $originalpostnode->setAttribute('data-wordnumber', $wordcount);
                    $originalpostnode->setAttribute('class', $cssoriginalpostword);

                }
                // add nodes to doc
                if($markuptype == 'passage'){
                    $node->parentNode->appendChild($newnode);
                    $node->parentNode->appendChild($spacenode);
                }else{
                    $node->parentNode->appendChild($originalprenode);
                    $node->parentNode->appendChild($newnode);
                    $node->parentNode->appendChild($originalpostnode);
                    $node->parentNode->appendChild($spacenode);
                }
                // $newnode = $doc->createElement('span', $word);
            }
            $node->nodeValue = "";
        }

        $usepassage = $doc->saveHTML();
        // remove container 'p' tags, they mess up formatting in solo
        $usepassage = str_replace('<p>', '', $usepassage);
        $usepassage = str_replace('</p>', '', $usepassage);

        if($markuptype == 'passage') {
            $ret = \html_writer::div($usepassage, constants::M_CLASS . '_grading_passagecont ' . constants::M_CLASS . '_summarytranscriptplaceholder');
        }else{
            $ret = \html_writer::div($usepassage, constants::M_CLASS . '_corrections ');
        }
        return $ret;
    }

    /*
    * Turn a passage with text "lines" into html "brs"
    *
    * @param String The passage of text to convert
    * @param String An optional pad on each replacement (needed for processing when marking up words as spans in passage)
    * @return String The converted passage of text
    */
    public static function lines_to_brs($passage, $seperator='') {
        // see https://stackoverflow.com/questions/5946114/how-to-replace-newline-or-r-n-with-br
        return str_replace("\r\n", $seperator . '<br>' . $seperator, $passage);
        // this is better but we can not pad the replacement and we need that
        // return nl2br($passage);
    }

}
