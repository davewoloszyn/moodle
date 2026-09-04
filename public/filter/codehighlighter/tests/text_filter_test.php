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

namespace filter_codehighlighter;

/**
 * Unit tests for the codehighlighter text filter.
 *
 * @package    filter_codehighlighter
 * @category   test
 * @copyright  2026 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \filter_codehighlighter\text_filter
 */
final class text_filter_test extends \advanced_testcase {
    /**
     * Bare <pre><code> blocks (no "language-*"/"lang-*" class of their own) must be
     * tagged with the generic "language-none" fallback, regardless of what content
     * format they came from (raw HTML typed into an editor, or untouched raw HTML
     * left in place by markdown_to_html()). Without this, Prism.js walks up the DOM
     * looking for a language class and mislabels the block using Moodle's
     * "lang-xx" body class.
     */
    public function test_bare_pre_code_is_tagged_language_none(): void {
        $filter = new text_filter(\core\context\system::instance(), []);

        $text = '<pre><code>xxxxx</code></pre>';
        $result = $filter->filter($text, ['originalformat' => FORMAT_HTML]);

        $this->assertSame('<pre class="language-none"><code>xxxxx</code></pre>', $result);
    }

    /**
     * Blocks that already carry an explicit language class must be left untouched.
     */
    public function test_existing_language_class_is_not_overwritten(): void {
        $filter = new text_filter(\core\context\system::instance(), []);

        $text = '<pre class="language-php"><code>echo 1;</code></pre>';
        $result = $filter->filter($text, ['originalformat' => FORMAT_HTML]);

        $this->assertSame($text, $result);
    }

    /**
     * Without an "originalformat" option the filter must not touch the text at all.
     */
    public function test_no_originalformat_option_leaves_text_untouched(): void {
        $filter = new text_filter(\core\context\system::instance(), []);

        $text = '<pre><code>xxxxx</code></pre>';
        $result = $filter->filter($text, []);

        $this->assertSame($text, $result);
    }
}
