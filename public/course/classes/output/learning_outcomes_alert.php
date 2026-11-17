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

namespace core_course\output;

use core\context\course as context_course;
use core\output\react_component_renderable;
use core\output\renderer_base;
use core\url;
use renderable;
use stdClass;

/**
 * A banner nudging teachers to set up learning outcomes in their course.
 *
 * Rendered by the core_course/LearningOutcomesAlert React component. The setup prompt can be dismissed, and then
 * stays dismissed for that user in that course. The recommended minimum reminder cannot be dismissed.
 *
 * @package    core_course
 * @copyright  2026 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_outcomes_alert implements react_component_renderable, renderable {
    /** @var string Prefix of the user preference set when the setup prompt is dismissed, followed by the course ID. */
    public const PREFERENCE_SETUP = 'core_course_learningoutcomes_setup_dismissed_';

    /** @var int The recommended minimum number of learning outcomes per course, when the site has not set one. */
    public const DEFAULT_RECOMMENDED = 3;

    /** @var int The highest recommended minimum number of learning outcomes per course a site can set. */
    public const MAX_RECOMMENDED = 10;

    /**
     * Constructor.
     *
     * @param string $message The banner message.
     * @param string|null $preference The name of the user preference to set when the banner is dismissed,
     *     or null if the banner cannot be dismissed.
     * @param string|null $linklabel The text of the link shown below the message, if any.
     * @param url|null $linkurl Where the link goes, if any.
     */
    protected function __construct(
        /** @var string The banner message. */
        protected readonly string $message,
        /** @var string|null The name of the user preference to set when the banner is dismissed, if it can be. */
        protected readonly ?string $preference = null,
        /** @var string|null The text of the link shown below the message, if any. */
        protected readonly ?string $linklabel = null,
        /** @var url|null Where the link goes, if any. */
        protected readonly ?url $linkurl = null,
    ) {
    }

    /**
     * Get the setup prompt, letting teachers know that learning outcomes can be set up in the course.
     *
     * The prompt is not shown once the course has at least one learning outcome.
     *
     * @param stdClass $course The course.
     * @return self|null The banner, or null if the current user should not see it.
     */
    public static function setup_prompt(stdClass $course): ?self {
        global $DB;

        if (!self::can_show($course)) {
            return null;
        }
        $preference = self::PREFERENCE_SETUP . $course->id;
        if (get_user_preferences($preference, 0)) {
            // The user has dismissed the prompt in this course.
            return null;
        }
        if ($DB->record_exists('grade_outcomes_courses', ['courseid' => $course->id])) {
            // Learning outcomes have already been set up in the course.
            return null;
        }

        return new self(
            message: get_string('learningoutcomessetupprompt', 'core_course'),
            preference: $preference,
            linklabel: get_string('learningoutcomessetup', 'core_course'),
            linkurl: new url('/course/learningoutcomes.php', ['id' => $course->id]),
        );
    }

    /**
     * Get the recommended minimum reminder, prompting teachers to meet the recommended minimum number of learning outcomes.
     *
     * The reminder cannot be dismissed. It is shown until the course meets the recommended minimum.
     *
     * @param stdClass $course The course.
     * @param int $outcomecount The number of learning outcomes applied to the course.
     * @return self|null The banner, or null if the current user should not see it.
     */
    public static function recommended_minimum_reminder(stdClass $course, int $outcomecount): ?self {
        $recommended = self::get_recommended_minimum();
        if ($outcomecount >= $recommended) {
            // Either the course meets the recommendation, or the site does not recommend a minimum.
            return null;
        }

        if (!self::can_show($course)) {
            return null;
        }

        $message = $recommended === 1
            ? get_string('learningoutcomesrecommendedone', 'core_course')
            : get_string('learningoutcomesrecommended', 'core_course', $recommended);

        return new self($message);
    }

    /**
     * Get the minimum number of learning outcomes per course recommended by the site.
     *
     * @return int The recommended minimum, or 0 if the site does not recommend one.
     */
    public static function get_recommended_minimum(): int {
        global $CFG;

        return max(0, (int) ($CFG->recommendedlearningoutcomes ?? self::DEFAULT_RECOMMENDED));
    }

    /**
     * Whether the current user can be shown the learning outcomes banners in the course.
     *
     * @param stdClass $course The course.
     * @return bool
     */
    protected static function can_show(stdClass $course): bool {
        global $CFG;

        if (empty($CFG->enableoutcomes)) {
            return false;
        }

        return has_capability('moodle/grade:manageoutcomes', context_course::instance($course->id));
    }

    #[\Override]
    public function get_react_component_name(): string {
        return 'core_course/LearningOutcomesAlert';
    }

    #[\Override]
    public function get_react_component_props(renderer_base $renderer): stdClass {
        $props = ['message' => $this->message];
        if ($this->preference !== null) {
            $props['preference'] = $this->preference;
            $props['dismisslabel'] = get_string('dismissnotification', 'core');
        }
        if ($this->linklabel !== null && $this->linkurl !== null) {
            $props['linklabel'] = $this->linklabel;
            $props['linkurl'] = $this->linkurl->out(false);
        }

        return (object) $props;
    }
}
