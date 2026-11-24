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

use core\url;
use core_user;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the learning outcomes banners.
 *
 * @package    core_course
 * @category   test
 * @copyright  2026 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(learning_outcomes_alert::class)]
#[CoversFunction('core_course_user_preferences')]
final class learning_outcomes_alert_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass A teacher in the course. */
    private \stdClass $teacher;

    /** @var \stdClass A student in the course. */
    private \stdClass $student;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enableoutcomes', 1);

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->student = $generator->create_and_enrol($this->course, 'student');
    }

    /**
     * Teachers see the setup prompt, linking to the learning outcomes page, even with no recommended minimum.
     */
    public function test_setup_prompt(): void {
        global $PAGE;

        set_config('recommendedlearningoutcomes', 0);
        $this->setUser($this->teacher);

        $alert = learning_outcomes_alert::setup_prompt($this->course);

        $this->assertSame('core_course/LearningOutcomesAlert', $alert->get_react_component_name());
        $this->assertEquals((object) [
            'message' => 'Did you know you can add learning outcomes to this course?',
            'preference' => 'core_course_learningoutcomes_setup_dismissed_' . $this->course->id,
            'dismisslabel' => 'Dismiss this notification',
            'linklabel' => 'Set up learning outcomes',
            'linkurl' => (new url('/course/learningoutcomes.php', ['id' => $this->course->id]))->out(false),
        ], $alert->get_react_component_props($PAGE->get_renderer('core')));
    }

    /**
     * The setup prompt is only for those who can manage learning outcomes, when they are enabled.
     */
    public function test_setup_prompt_visibility(): void {
        $this->setUser($this->student);
        $this->assertNull(learning_outcomes_alert::setup_prompt($this->course));

        $this->setUser($this->teacher);
        $this->assertNotNull(learning_outcomes_alert::setup_prompt($this->course));

        set_config('enableoutcomes', 0);
        $this->assertNull(learning_outcomes_alert::setup_prompt($this->course));
    }

    /**
     * The setup prompt is not shown once the course has a learning outcome, while other courses still show it.
     */
    public function test_setup_prompt_not_shown_with_outcomes(): void {
        $othercourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->teacher->id, $othercourse->id, 'editingteacher');
        $this->setUser($this->teacher);

        $this->getDataGenerator()->create_grade_outcome([
            'courseid' => $this->course->id,
            'fullname' => 'Outcome',
            'shortname' => 'outcome',
        ]);

        $this->assertNull(learning_outcomes_alert::setup_prompt($this->course));
        $this->assertNotNull(learning_outcomes_alert::setup_prompt($othercourse));
    }

    /**
     * The recommended minimum reminder is shown while the course has fewer learning outcomes than recommended.
     *
     * It has no dismissal preference, so it cannot be dismissed.
     *
     * @param int|null $recommended The site's recommended minimum, or null if it has not been set.
     * @param int $outcomecount The number of learning outcomes in the course.
     * @param string|null $expectedmessage The expected banner message, or null if the banner should not be shown.
     */
    #[DataProvider('recommended_minimum_reminder_provider')]
    public function test_recommended_minimum_reminder(?int $recommended, int $outcomecount, ?string $expectedmessage): void {
        global $PAGE;

        if ($recommended !== null) {
            set_config('recommendedlearningoutcomes', $recommended);
        }
        $this->setUser($this->teacher);

        $alert = learning_outcomes_alert::recommended_minimum_reminder($this->course, $outcomecount);

        if ($expectedmessage === null) {
            $this->assertNull($alert);
            return;
        }
        $this->assertEquals((object) [
            'message' => $expectedmessage,
        ], $alert->get_react_component_props($PAGE->get_renderer('core')));
    }

    /**
     * Data provider for {@see test_recommended_minimum_reminder()}.
     *
     * @return array[]
     */
    public static function recommended_minimum_reminder_provider(): array {
        return [
            'Default recommendation, no outcomes' => [
                'recommended' => null,
                'outcomecount' => 0,
                'expectedmessage' => 'Your organisation recommends at least 3 learning outcomes for each course.',
            ],
            'Default recommendation, too few outcomes' => [
                'recommended' => null,
                'outcomecount' => 2,
                'expectedmessage' => 'Your organisation recommends at least 3 learning outcomes for each course.',
            ],
            'Default recommendation, met' => [
                'recommended' => null,
                'outcomecount' => 3,
                'expectedmessage' => null,
            ],
            'Custom recommendation, too few outcomes' => [
                'recommended' => 5,
                'outcomecount' => 4,
                'expectedmessage' => 'Your organisation recommends at least 5 learning outcomes for each course.',
            ],
            'Custom recommendation, exceeded' => [
                'recommended' => 5,
                'outcomecount' => 6,
                'expectedmessage' => null,
            ],
            'Recommendation of one, no outcomes' => [
                'recommended' => 1,
                'outcomecount' => 0,
                'expectedmessage' => 'Your organisation recommends at least 1 learning outcome for each course.',
            ],
            'No recommendation, no outcomes' => [
                'recommended' => 0,
                'outcomecount' => 0,
                'expectedmessage' => null,
            ],
        ];
    }

    /**
     * The recommended minimum reminder is only for those who can manage learning outcomes, when they are enabled.
     */
    public function test_recommended_minimum_reminder_visibility(): void {
        $this->setUser($this->student);
        $this->assertNull(learning_outcomes_alert::recommended_minimum_reminder($this->course, 0));

        $this->setUser($this->teacher);
        $this->assertNotNull(learning_outcomes_alert::recommended_minimum_reminder($this->course, 0));

        set_config('enableoutcomes', 0);
        $this->assertNull(learning_outcomes_alert::recommended_minimum_reminder($this->course, 0));
    }

    /**
     * Dismissing the setup prompt only dismisses it for that user, in that course, and leaves the reminder showing.
     */
    public function test_dismissal(): void {
        $othercourse = $this->getDataGenerator()->create_course();
        $otherteacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $othercourse->id, 'editingteacher');

        $this->setUser($this->teacher);
        set_user_preference(learning_outcomes_alert::PREFERENCE_SETUP . $this->course->id, 1);

        $this->assertNull(learning_outcomes_alert::setup_prompt($this->course));
        $this->assertNotNull(learning_outcomes_alert::recommended_minimum_reminder($this->course, 0));
        $this->assertNotNull(learning_outcomes_alert::setup_prompt($othercourse));

        $this->setUser($otherteacher);
        $this->assertNotNull(learning_outcomes_alert::setup_prompt($this->course));
    }

    /**
     * Deleting a course deletes the dismissals of its setup prompt, and leaves those of other courses alone.
     */
    public function test_dismissals_deleted_with_course(): void {
        global $DB;

        $othercourse = $this->getDataGenerator()->create_course();
        $setup = learning_outcomes_alert::PREFERENCE_SETUP;
        set_user_preference($setup . $this->course->id, 1, $this->teacher);
        set_user_preference($setup . $othercourse->id, 1, $this->teacher);

        delete_course($this->course, false, false);

        $this->assertFalse($DB->record_exists('user_preferences', ['name' => $setup . $this->course->id]));
        $this->assertTrue($DB->record_exists('user_preferences', ['name' => $setup . $othercourse->id]));
    }

    /**
     * Users can update their own setup prompt dismissal preferences, which is what allows the prompt to save them.
     *
     * @param string $preference The preference name, with a {courseid} placeholder.
     * @param bool $expected Whether the preference is one a user can update.
     */
    #[DataProvider('user_preferences_provider')]
    public function test_user_preferences(string $preference, bool $expected): void {
        $this->setUser($this->teacher);
        $preference = str_replace('{courseid}', $this->course->id, $preference);

        $this->assertSame($expected, core_user::can_edit_preference($preference, $this->teacher));
        if ($expected) {
            $this->assertSame(1, core_user::clean_preference('1', $preference));
            $this->assertFalse(core_user::can_edit_preference($preference, $this->student));
        }
    }

    /**
     * Data provider for {@see test_user_preferences()}.
     *
     * @return array[]
     */
    public static function user_preferences_provider(): array {
        return [
            'Setup prompt' => [
                'preference' => 'core_course_learningoutcomes_setup_dismissed_{courseid}',
                'expected' => true,
            ],
            'Recommended minimum reminder, which cannot be dismissed' => [
                'preference' => 'core_course_learningoutcomes_recommended_dismissed_{courseid}',
                'expected' => false,
            ],
            'No course ID' => [
                'preference' => 'core_course_learningoutcomes_setup_dismissed_',
                'expected' => false,
            ],
            'Not a course ID' => [
                'preference' => 'core_course_learningoutcomes_setup_dismissed_abc',
                'expected' => false,
            ],
            'Unknown banner' => [
                'preference' => 'core_course_learningoutcomes_other_dismissed_{courseid}',
                'expected' => false,
            ],
        ];
    }
}
