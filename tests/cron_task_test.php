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

namespace mod_hotquestion;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for HotQuestion notification cron selection.
 *
 * @package   mod_hotquestion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \\mod_hotquestion\\task\\cron_task
 */
final class cron_task_test extends \advanced_testcase {
    /**
     * Only questions created after notifications were enabled should be selected for mailing.
     */
    public function test_get_unmailed_questions_ignores_questions_before_notifications_enabled(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('hotquestion', [
            'course' => $course->id,
            'notifications' => 1,
        ]);

        $baseline = time() - 200;
        $DB->set_field('hotquestion', 'notificationsenabledtime', $baseline, ['id' => $module->id]);

        $oldquestionid = $DB->insert_record('hotquestion_questions', [
            'hotquestion' => $module->id,
            'content' => 'Old question',
            'format' => FORMAT_HTML,
            'userid' => $author->id,
            'time' => $baseline - 10,
            'anonymous' => 0,
            'approved' => 1,
            'tpriority' => 0,
            'mailed' => 0,
        ]);

        $newquestionid = $DB->insert_record('hotquestion_questions', [
            'hotquestion' => $module->id,
            'content' => 'New question',
            'format' => FORMAT_HTML,
            'userid' => $author->id,
            'time' => $baseline + 10,
            'anonymous' => 0,
            'approved' => 1,
            'tpriority' => 0,
            'mailed' => 0,
        ]);

        $task = new \mod_hotquestion\task\cron_task();
        $method = new \ReflectionMethod($task, 'get_unmailed_questions');
        $method->setAccessible(true);

        ob_start();
        try {
            $questions = $method->invoke($task, time() + 60);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey($newquestionid, $questions);
        $this->assertArrayNotHasKey($oldquestionid, $questions);
    }
}