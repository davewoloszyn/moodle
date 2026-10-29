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

namespace core_grading;

use advanced_testcase;
use context_module;
use gradingform_controller;
use gradingform_instance;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the gradingform_lib base classes.
 *
 * @package    core_grading
 * @category   test
 * @copyright  2026 Catalyst IT Australia Pty Ltd
 * @author     Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(gradingform_controller::class)]
#[CoversClass(gradingform_instance::class)]
final class formlib_test extends advanced_testcase {
    /**
     * Get a controller with an empty definition.
     *
     * @return gradingform_controller
     */
    protected function get_controller(): gradingform_controller {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course]);
        $context = context_module::instance($module->cmid);

        $gradinggenerator = \testing_util::get_data_generator()->get_plugin_generator('core_grading');
        $controller = $gradinggenerator->create_instance($context, 'mod_assign', 'submissions', 'rubric');

        // The base class only needs the definition record, not the plugin's own data.
        $DB->insert_record('grading_definitions', [
            'areaid' => $controller->get_areaid(),
            'method' => 'rubric',
            'name' => 'Test form',
            'status' => gradingform_controller::DEFINITION_STATUS_READY,
            'timecreated' => time(),
            'usercreated' => $USER->id,
            'timemodified' => time(),
            'usermodified' => $USER->id,
        ]);
        $controller->get_definition(true);

        return $controller;
    }

    /**
     * Create a gradingform instance and submit it so that it becomes the active instance.
     *
     * @param gradingform_controller $controller
     * @param int $raterid
     * @param int $itemid
     * @return gradingform_instance
     */
    protected function submit_instance(gradingform_controller $controller, int $raterid, int $itemid): gradingform_instance {
        $instance = $controller->create_instance($raterid, $itemid);
        $instance->submit_and_get_grade(['criteria' => []], $itemid);

        return $instance;
    }

    /**
     * Test changing the allow multiple raters config.
     */
    public function test_set_allow_multiple_raters(): void {
        $controller = $this->get_controller();
        $this->assertFalse($controller->allow_multiple_raters());

        $controller->set_allow_multiple_raters(true);
        $this->assertTrue($controller->allow_multiple_raters());

        $controller->set_allow_multiple_raters(false);
        $this->assertFalse($controller->allow_multiple_raters());
    }

    /**
     * Test get_current_instance() with multiple raters.
     */
    public function test_get_current_instance_with_multiple_raters(): void {
        $controller = $this->get_controller();
        $rater1 = $this->getDataGenerator()->create_user();
        $rater2 = $this->getDataGenerator()->create_user();
        $itemid = 1;
        $instance = $this->submit_instance($controller, $rater1->id, $itemid);

        // By default instances should be shared across all users.
        $this->assertEquals(
            $instance->get_id(),
            $controller->get_current_instance($rater2->id, $itemid)->get_id(),
        );

        // Instances should be user specific when multiple raters are allowed.
        $controller->set_allow_multiple_raters(true);
        $this->assertNull($controller->get_current_instance($rater2->id, $itemid));
        $this->assertEquals(
            $instance->get_id(),
            $controller->get_current_instance($rater1->id, $itemid)->get_id(),
        );
    }

    /**
     * Test get_current_instance() when multiple raters have recently been disabled.
     */
    public function test_get_current_instance_after_disabling_multiple_raters(): void {
        $controller = $this->get_controller();
        $controller->set_allow_multiple_raters(true);
        $rater1 = $this->getDataGenerator()->create_user();
        $rater2 = $this->getDataGenerator()->create_user();
        $rater3 = $this->getDataGenerator()->create_user();
        $itemid = 1;
        $instance1 = $this->submit_instance($controller, $rater1->id, $itemid);
        $instance2 = $this->submit_instance($controller, $rater2->id, $itemid);

        // Raters with a current instance should continue to get their own instance.
        $controller->set_allow_multiple_raters(false);
        $this->assertCount(2, $controller->get_current_instances($itemid));
        $this->assertEquals(
            $instance1->get_id(),
            $controller->get_current_instance($rater1->id, $itemid)->get_id(),
        );
        $this->assertEquals(
            $instance2->get_id(),
            $controller->get_current_instance($rater2->id, $itemid)->get_id(),
        );

        // Other raters should get the most recent instance, as they would with a single rater.
        $this->assertEquals(
            $instance2->get_id(),
            $controller->get_current_instance($rater3->id, $itemid)->get_id(),
        );
    }

    /**
     * Test setting a custom sort method for getting multiple gradingform instances.
     */
    public function test_set_instances_sort(): void {
        $controller = $this->get_controller();
        $controller->set_allow_multiple_raters(true);
        $rater1 = $this->getDataGenerator()->create_user();
        $rater2 = $this->getDataGenerator()->create_user();
        $itemid = 1;

        $instance1 = $this->submit_instance($controller, $rater1->id, $itemid);
        $instance2 = $this->submit_instance($controller, $rater2->id, $itemid);

        $ascending = fn($a, $b) => $a->get_id() <=> $b->get_id();
        $descending = fn($a, $b) => $b->get_id() <=> $a->get_id();

        $controller->set_instances_sort($ascending);
        $ids = array_map(fn($i) => $i->get_id(), $controller->get_active_instances($itemid));
        $this->assertEquals([$instance1->get_id(), $instance2->get_id()], $ids);

        $controller->set_instances_sort($descending);
        $ids = array_map(fn($i) => $i->get_id(), $controller->get_active_instances($itemid));
        $this->assertEquals([$instance2->get_id(), $instance1->get_id()], $ids);

        $ids = array_map(fn($i) => $i->get_id(), $controller->get_all_active_instances());
        $this->assertEquals([$instance2->get_id(), $instance1->get_id()], $ids);

        // Disabling the sort should still return all instances.
        $controller->set_instances_sort(null);
        $this->assertCount(2, $controller->get_active_instances($itemid));
    }

    /**
     * Test changing the display rater identity config.
     */
    public function test_set_display_rater_identity(): void {
        $controller = $this->get_controller();
        $this->assertFalse($controller->display_rater_identity());

        $controller->set_display_rater_identity(true);
        $this->assertTrue($controller->display_rater_identity());
    }

    /**
     * Test getting all current instances.
     */
    public function test_get_current_instances(): void {
        global $DB;

        $controller = $this->get_controller();
        $controller->set_allow_multiple_raters(true);
        $rater1 = $this->getDataGenerator()->create_user();
        $rater2 = $this->getDataGenerator()->create_user();
        $itemid = 1;

        $instance1 = $this->submit_instance($controller, $rater1->id, $itemid);
        $instance2 = $this->submit_instance($controller, $rater2->id, $itemid);
        $this->submit_instance($controller, $rater1->id, $itemid + 1);

        // Verify count of active instances.
        $instances = $controller->get_current_instances($itemid);
        $this->assertEquals([$instance1->get_id(), $instance2->get_id()], array_map(fn($i) => $i->get_id(), $instances));

        // Verify count of needupdate instances.
        $controller->mark_for_regrade();
        $instances = $controller->get_current_instances($itemid);
        $instance = reset($instances);
        $this->assertEquals(gradingform_instance::INSTANCE_STATUS_NEEDUPDATE, $instance->get_status());
        $this->assertEquals([$instance1->get_id(), $instance2->get_id()], array_map(fn($i) => $i->get_id(), $instances));
    }

    /**
     * Test restoring previous instance from the controller.
     */
    public function test_restore_previous_instance(): void {
        global $DB;

        $controller = $this->get_controller();
        $controller->set_allow_multiple_raters(true);
        $rater1 = $this->getDataGenerator()->create_user();
        $rater2 = $this->getDataGenerator()->create_user();
        $itemid = 1;

        // Nothing archived yet.
        $this->assertFalse($controller->restore_previous_instance($rater1->id, $itemid));

        $instance1 = $this->submit_instance($controller, $rater1->id, $itemid);
        $instance2 = $this->submit_instance($controller, $rater1->id, $itemid);

        // Restoring is not allowed when there is an active instance.
        $this->assertFalse($controller->restore_previous_instance($rater1->id, $itemid));

        // Archive the active instance.
        $instance2->archive();
        $this->assertNull($controller->get_current_instance($rater1->id, $itemid));

        // Restoring should restore instance 2.
        $this->assertTrue($controller->restore_previous_instance($rater1->id, $itemid));
        $current = $controller->get_current_instance($rater1->id, $itemid);
        $this->assertEquals($instance2->get_id(), $current->get_id());

        // Restoring it again should not work.
        $this->assertFalse($controller->restore_previous_instance($rater1->id, $itemid));

        // Other rater and other item have no archived instances.
        $this->assertFalse($controller->restore_previous_instance($rater2->id, $itemid));
        $this->assertFalse($controller->restore_previous_instance($rater1->id, $itemid + 1));
    }

    /**
     * Test that making an instance active archives previous instances.
     */
    public function test_make_active(): void {
        global $DB;

        $controller = $this->get_controller();
        $rater1 = $this->getDataGenerator()->create_user();
        $rater2 = $this->getDataGenerator()->create_user();
        $itemid = 1;

        // By default there should only be one current instance.
        $instance1 = $this->submit_instance($controller, $rater1->id, $itemid);
        $instance2 = $this->submit_instance($controller, $rater2->id, $itemid);
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ARCHIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance1->get_id()]),
        );
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ACTIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance2->get_id()]),
        );

        // Marks needing regrading should also get archived.
        $controller->mark_for_regrade();
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_NEEDUPDATE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance2->get_id()]),
        );

        $instance3 = $this->submit_instance($controller, $rater2->id, $itemid);
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ARCHIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance2->get_id()]),
        );
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ACTIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance3->get_id()]),
        );
    }

    /**
     * Test that making an instance active archives previous instances with multiple markers.
     */
    public function test_make_active_with_multiple_raters(): void {
        global $DB;

        $controller = $this->get_controller();
        $controller->set_allow_multiple_raters(true);
        $rater1 = $this->getDataGenerator()->create_user();
        $rater2 = $this->getDataGenerator()->create_user();
        $itemid = 1;

        // Multiple raters should each have their own instance.
        $instance1 = $this->submit_instance($controller, $rater1->id, $itemid);
        $instance2 = $this->submit_instance($controller, $rater2->id, $itemid);
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ACTIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance1->get_id()]),
        );
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ACTIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance2->get_id()]),
        );

        // Multiple raters should only archive their own instance.
        $controller->set_allow_multiple_raters(true);
        $instance3 = $this->submit_instance($controller, $rater1->id, $itemid);
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ARCHIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance1->get_id()]),
        );
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ACTIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance2->get_id()]),
        );
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ACTIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance3->get_id()]),
        );

        // If the config changes, current instances should remain until a new instance is saved.
        $controller->set_allow_multiple_raters(false);
        $this->assertCount(2, $controller->get_current_instances($itemid));

        // Once this is saved there should only be one current instance.
        $instance4 = $this->submit_instance($controller, $rater1->id, $itemid);
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ARCHIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance2->get_id()]),
        );
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ARCHIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance3->get_id()]),
        );
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ACTIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance4->get_id()]),
        );
    }

    /**
     * Test archiving gradingform instances.
     */
    public function test_archive(): void {
        global $DB;
        $controller = $this->get_controller();
        $rater = $this->getDataGenerator()->create_user();

        $instance = $this->submit_instance($controller, $rater->id, 1);
        $this->assertEquals(gradingform_instance::INSTANCE_STATUS_ACTIVE, $instance->get_status());

        $instance->archive();
        $this->assertEquals(gradingform_instance::INSTANCE_STATUS_ARCHIVE, $instance->get_status());
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ARCHIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance->get_id()]),
        );

        // Archiving again is a no-op.
        $instance->archive();
        $this->assertEquals(gradingform_instance::INSTANCE_STATUS_ARCHIVE, $instance->get_status());
    }

    /**
     * Test restoring gradingform instances.
     */
    public function test_restore(): void {
        global $DB;
        $controller = $this->get_controller();
        $rater = $this->getDataGenerator()->create_user();
        $itemid = 1;

        // Only archived instances can be restored.
        $instance = $controller->create_instance($rater->id, $itemid);
        $instance->restore();
        $this->assertEquals(gradingform_instance::INSTANCE_STATUS_INCOMPLETE, $instance->get_status());
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_INCOMPLETE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance->get_id()]),
        );
        $this->assertNull($controller->get_current_instance($rater->id, $itemid));

        // Restoring should set the status to active in both the class and DB.
        $instance->archive();
        $instance->restore();
        $this->assertEquals(gradingform_instance::INSTANCE_STATUS_ACTIVE, $instance->get_status());
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ACTIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance->get_id()]),
        );
        $this->assertEquals($instance, $controller->get_current_instance($rater->id, $itemid));

        // If there was a previously active instance it should be archived.
        $newinstance = $controller->create_instance($rater->id, $itemid);
        $newinstance->archive();
        $newinstance->restore();

        $this->assertEquals(gradingform_instance::INSTANCE_STATUS_ACTIVE, $newinstance->get_status());
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ACTIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $newinstance->get_id()]),
        );
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ARCHIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $instance->get_id()]),
        );
        $this->assertEquals($newinstance, $controller->get_current_instance($rater->id, $itemid));

        // Restoring again is a no-op.
        $newinstance->restore();
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_ACTIVE,
            $DB->get_field('grading_instances', 'status', ['id' => $newinstance->get_id()]),
        );
        $this->assertEquals($newinstance, $controller->get_current_instance($rater->id, $itemid));

        // Restoring after the definition changes should require regrading.
        $this->waitForSecond();
        $definition = file_prepare_standard_editor(clone $controller->get_definition(), 'description', []);
        $controller->update_definition($definition);
        $newinstance->archive();
        $newinstance->restore();
        $this->assertEquals(gradingform_instance::INSTANCE_STATUS_NEEDUPDATE, $newinstance->get_status());
        $this->assertEquals(
            gradingform_instance::INSTANCE_STATUS_NEEDUPDATE,
            $DB->get_field('grading_instances', 'status', ['id' => $newinstance->get_id()]),
        );
    }
}
