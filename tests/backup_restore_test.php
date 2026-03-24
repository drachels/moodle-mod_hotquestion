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
require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

/**
 * Backup and restore tests for hotquestion.
 *
 * @package   mod_hotquestion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \backup_hotquestion_activity_task
 * @covers \restore_hotquestion_activity_task
 */
final class backup_restore_test extends \restore_date_testcase {

    /**
     * Verify activity backup and restore keeps rounds, votes, and comments.
     */
    public function test_backup_restore_includes_rounds_votes_and_comments(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $hotquestion = $this->getDataGenerator()->create_module('hotquestion', [
            'course' => $course->id,
            'comments' => 1,
            'minquestionsview' => 2,
            'maxquestionsperuser' => 5,
        ]);

        $cm = get_coursemodule_from_instance('hotquestion', $hotquestion->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $roundid = $DB->insert_record('hotquestion_rounds', [
            'hotquestion' => $hotquestion->id,
            'starttime' => 1711111111,
            'endtime' => 0,
        ]);

        $questionid = $DB->insert_record('hotquestion_questions', [
            'hotquestion' => $hotquestion->id,
            'content' => 'Backup and restore me',
            'format' => FORMAT_HTML,
            'userid' => $user->id,
            'time' => 1711111112,
            'anonymous' => 0,
            'approved' => 1,
            'tpriority' => 2,
            'mailed' => 0,
        ]);

        $voteid = $DB->insert_record('hotquestion_votes', [
            'question' => $questionid,
            'voter' => $user->id,
        ]);

        $commentid = $DB->insert_record('comments', [
            'contextid' => $context->id,
            'component' => 'mod_hotquestion',
            'commentarea' => 'hotquestion_questions',
            'itemid' => $questionid,
            'content' => 'Persist this comment',
            'format' => FORMAT_HTML,
            'userid' => $user->id,
            'timecreated' => 1711111113,
        ]);

        $newcourseid = $this->backup_and_restore($course);

        $newhotquestion = $DB->get_record('hotquestion', ['course' => $newcourseid], '*', MUST_EXIST);
        $newcm = get_coursemodule_from_instance('hotquestion', $newhotquestion->id, $newcourseid, false, MUST_EXIST);
        $newcontext = \context_module::instance($newcm->id);

        $newround = $DB->get_record('hotquestion_rounds', ['hotquestion' => $newhotquestion->id], '*', MUST_EXIST);
        $newquestion = $DB->get_record('hotquestion_questions', ['hotquestion' => $newhotquestion->id], '*', MUST_EXIST);
        $newvote = $DB->get_record('hotquestion_votes', ['question' => $newquestion->id], '*', MUST_EXIST);
        $newcomment = $DB->get_record('comments', [
            'contextid' => $newcontext->id,
            'component' => 'mod_hotquestion',
            'commentarea' => 'hotquestion_questions',
            'itemid' => $newquestion->id,
        ], '*', MUST_EXIST);

        $this->assertNotEmpty($roundid);
        $this->assertNotEmpty($questionid);
        $this->assertNotEmpty($voteid);
        $this->assertNotEmpty($commentid);

        $this->assertEquals(1711111111, $newround->starttime);
        $this->assertEquals(0, $newround->endtime);
        $this->assertEquals(2, $newhotquestion->minquestionsview);
        $this->assertEquals(5, $newhotquestion->maxquestionsperuser);
        $this->assertEquals('Backup and restore me', $newquestion->content);
        $this->assertEquals(2, $newquestion->tpriority);
        $this->assertEquals($user->id, $newvote->voter);
        $this->assertEquals('Persist this comment', $newcomment->content);
        $this->assertEquals($user->id, $newcomment->userid);
    }

    /**
     * Verify restore keeps rounds but excludes user data when userinfo is disabled.
     */
    public function test_backup_restore_without_userinfo_keeps_rounds_only(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $hotquestion = $this->getDataGenerator()->create_module('hotquestion', [
            'course' => $course->id,
            'comments' => 1,
        ]);

        $cm = get_coursemodule_from_instance('hotquestion', $hotquestion->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $DB->insert_record('hotquestion_rounds', [
            'hotquestion' => $hotquestion->id,
            'starttime' => 1712222221,
            'endtime' => 0,
        ]);

        $questionid = $DB->insert_record('hotquestion_questions', [
            'hotquestion' => $hotquestion->id,
            'content' => 'This should not restore without userinfo',
            'format' => FORMAT_HTML,
            'userid' => $user->id,
            'time' => 1712222222,
            'anonymous' => 0,
            'approved' => 1,
            'tpriority' => 1,
            'mailed' => 0,
        ]);

        $DB->insert_record('hotquestion_votes', [
            'question' => $questionid,
            'voter' => $user->id,
        ]);

        $DB->insert_record('comments', [
            'contextid' => $context->id,
            'component' => 'mod_hotquestion',
            'commentarea' => 'hotquestion_questions',
            'itemid' => $questionid,
            'content' => 'This comment should not restore without userinfo',
            'format' => FORMAT_HTML,
            'userid' => $user->id,
            'timecreated' => 1712222223,
        ]);

        $newcourseid = $this->backup_and_restore_with_userinfo($course, false);
        $newhotquestion = $DB->get_record('hotquestion', ['course' => $newcourseid], '*', MUST_EXIST);
        $newcm = get_coursemodule_from_instance('hotquestion', $newhotquestion->id, $newcourseid, false, MUST_EXIST);
        $newcontext = \context_module::instance($newcm->id);

        $this->assertEquals(
            1,
            $DB->count_records('hotquestion_rounds', ['hotquestion' => $newhotquestion->id])
        );
        $this->assertEquals(
            0,
            $DB->count_records('hotquestion_questions', ['hotquestion' => $newhotquestion->id])
        );
        $this->assertEquals(
            0,
            $DB->get_field_sql(
                'SELECT COUNT(v.id)
                   FROM {hotquestion_votes} v
                   JOIN {hotquestion_questions} q ON q.id = v.question
                  WHERE q.hotquestion = ?',
                [$newhotquestion->id]
            )
        );
        $this->assertEquals(
            0,
            $DB->count_records('comments', ['contextid' => $newcontext->id, 'component' => 'mod_hotquestion'])
        );
    }

    /**
     * Back up and restore a course with explicit userinfo setting.
     *
     * @param \stdClass $srccourse Source course to back up.
     * @param bool $userinfo Include user data in backup and restore.
     * @return int Restored course id.
     */
    private function backup_and_restore_with_userinfo(\stdClass $srccourse, bool $userinfo): int {
        global $USER, $CFG;

        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $srccourse->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );

        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($userinfo);

        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course(
            $srccourse->fullname,
            $srccourse->shortname . '_restore',
            $srccourse->category
        );

        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );

        $rc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value($userinfo);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
