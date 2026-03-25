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

global $CFG;
require_once($CFG->dirroot . '/mod/hotquestion/lib.php');
require_once($CFG->dirroot . '/mod/hotquestion/locallib.php');

/**
 * Tests for hotquestion locallib behavior.
 *
 * @package   mod_hotquestion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_hotquestion::get_questions
 */
final class locallib_test extends \advanced_testcase {
    /**
     * When grading is enabled, teacher priority must not change question ordering.
     */
    public function test_get_questions_ignores_teacher_priority_when_grading_enabled(): void {
        [$hq, $firstid, $secondid] = $this->create_hotquestion_with_prioritized_questions(100);

        $questions = array_values($hq->get_questions());

        $this->assertCount(2, $questions);
        $this->assertEquals($secondid, $questions[0]->id);
        $this->assertEquals($firstid, $questions[1]->id);
    }

    /**
     * When grading is disabled, teacher priority continues to affect ordering.
     */
    public function test_get_questions_keeps_teacher_priority_when_grading_disabled(): void {
        [$hq, $firstid, $secondid] = $this->create_hotquestion_with_prioritized_questions(0);

        $questions = array_values($hq->get_questions());

        $this->assertCount(2, $questions);
        $this->assertEquals($firstid, $questions[0]->id);
        $this->assertEquals($secondid, $questions[1]->id);
    }

    /**
     * Course reset must remove Hot Question comments together with questions.
     */
    public function test_hotquestion_reset_userdata_removes_question_comments(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('hotquestion', [
            'course' => $course->id,
            'comments' => 1,
        ]);

        $cm = get_coursemodule_from_instance('hotquestion', $module->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $questionid = $DB->insert_record('hotquestion_questions', [
            'hotquestion' => $module->id,
            'content' => 'Reset me',
            'format' => FORMAT_HTML,
            'userid' => $user->id,
            'time' => time(),
            'anonymous' => 0,
            'approved' => 1,
            'tpriority' => 0,
            'mailed' => 0,
        ]);

        $DB->insert_record('comments', [
            'contextid' => $context->id,
            'component' => 'mod_hotquestion',
            'commentarea' => 'hotquestion_questions',
            'itemid' => $questionid,
            'content' => 'Remove this during reset',
            'format' => FORMAT_HTML,
            'userid' => $user->id,
            'timecreated' => time(),
        ]);

        $this->assertEquals(1, $DB->count_records('comments', [
            'contextid' => $context->id,
            'component' => 'mod_hotquestion',
            'commentarea' => 'hotquestion_questions',
            'itemid' => $questionid,
        ]));

        $status = \hotquestion_reset_userdata((object) [
            'courseid' => $course->id,
            'reset_hotquestion' => 1,
        ]);

        $this->assertCount(1, $status);
        $this->assertFalse($status[0]['error']);
        $this->assertEquals(0, $DB->count_records('hotquestion_questions', ['hotquestion' => $module->id]));
        $this->assertEquals(0, $DB->count_records('comments', [
            'contextid' => $context->id,
            'component' => 'mod_hotquestion',
            'commentarea' => 'hotquestion_questions',
        ]));
    }

    /**
     * User question counts for limits must include only the current round.
     */
    public function test_get_user_question_count_in_current_round_counts_only_active_round(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('hotquestion', [
            'course' => $course->id,
        ]);

        $hq = new \mod_hotquestion($module->cmid);
        $currentround = $hq->get_currentround();

        $DB->insert_record('hotquestion_questions', [
            'hotquestion' => $module->id,
            'content' => 'Current round question',
            'format' => FORMAT_HTML,
            'userid' => $user->id,
            'time' => $currentround->starttime + 10,
            'anonymous' => 0,
            'approved' => 1,
            'tpriority' => 0,
            'mailed' => 0,
        ]);

        // Add an older closed round entry that must not be counted.
        $DB->insert_record('hotquestion_rounds', [
            'hotquestion' => $module->id,
            'starttime' => $currentround->starttime - 200,
            'endtime' => $currentround->starttime - 100,
        ]);
        $DB->insert_record('hotquestion_questions', [
            'hotquestion' => $module->id,
            'content' => 'Old round question',
            'format' => FORMAT_HTML,
            'userid' => $user->id,
            'time' => $currentround->starttime - 150,
            'anonymous' => 0,
            'approved' => 1,
            'tpriority' => 0,
            'mailed' => 0,
        ]);

        $this->assertEquals(1, $hq->get_user_question_count_in_current_round($user->id));
    }

    /**
     * Create an activity with two questions whose ordering reveals tpriority influence.
     *
     * @param int $grade Activity grade setting. Non-zero means grading enabled.
     * @return array
     */
    private function create_hotquestion_with_prioritized_questions(int $grade): array {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('hotquestion', [
            'course' => $course->id,
            'grade' => $grade,
        ]);

        $hq = new \mod_hotquestion($module->cmid);
        $round = $hq->get_currentround();
        $basetime = $round->starttime + 10;

        // Older, higher teacher-priority question.
        $firstid = $DB->insert_record('hotquestion_questions', [
            'hotquestion' => $module->id,
            'content' => 'High priority older',
            'format' => FORMAT_HTML,
            'userid' => $user->id,
            'time' => $basetime,
            'anonymous' => 0,
            'approved' => 1,
            'tpriority' => 10,
            'mailed' => 0,
        ]);

        // Newer, lower teacher-priority question.
        $secondid = $DB->insert_record('hotquestion_questions', [
            'hotquestion' => $module->id,
            'content' => 'Low priority newer',
            'format' => FORMAT_HTML,
            'userid' => $user->id,
            'time' => $basetime + 1,
            'anonymous' => 0,
            'approved' => 1,
            'tpriority' => 0,
            'mailed' => 0,
        ]);

        return [$hq, $firstid, $secondid];
    }
}
