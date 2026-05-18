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

namespace mod_hotquestion\output;

/**
 * Tests for HotQuestion mobile output sorting behavior.
 *
 * @package   mod_hotquestion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_hotquestion\output\mobile::mobile_course_view
 */
final class mobile_output_test extends \advanced_testcase {
    /**
     * Mobile sorting updates preferences and affects question ordering.
     */
    public function test_mobile_course_view_sorting_preferences_and_order(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $module = $this->getDataGenerator()->create_module('hotquestion', [
            'course' => $course->id,
            'teacherpriorityvisibility' => 1,
            'heatvisibility' => 1,
            'heatlimit' => 3,
        ]);

        $this->setUser($user);

        $hq = new \mod_hotquestion($module->cmid);
        $round = $hq->get_currentround();
        $basetime = $round->starttime + 10;

        $alphaid = $DB->insert_record('hotquestion_questions', [
            'hotquestion' => $module->id,
            'content' => 'Alpha question',
            'format' => FORMAT_HTML,
            'userid' => $user->id,
            'time' => $basetime,
            'anonymous' => 0,
            'approved' => 1,
            'tpriority' => 0,
            'mailed' => 0,
        ]);

        $zuluid = $DB->insert_record('hotquestion_questions', [
            'hotquestion' => $module->id,
            'content' => 'Zulu question',
            'format' => FORMAT_HTML,
            'userid' => $user->id,
            'time' => $basetime + 1,
            'anonymous' => 0,
            'approved' => 1,
            'tpriority' => 0,
            'mailed' => 0,
        ]);

        $defaultresult = mobile::mobile_course_view([
            'cmid' => (int)$module->cmid,
            'courseid' => (int)$course->id,
        ]);

        $defaulthtml = $defaultresult['templates'][0]['html'];
        $defaultalphapos = strpos($defaulthtml, 'Alpha question');
        $defaultzulupos = strpos($defaulthtml, 'Zulu question');

        $this->assertNotFalse($defaultalphapos);
        $this->assertNotFalse($defaultzulupos);
        $this->assertLessThan($defaultalphapos, $defaultzulupos);

        $sortedresult = mobile::mobile_course_view([
            'cmid' => (int)$module->cmid,
            'courseid' => (int)$course->id,
            'sortby' => 'question',
            'sortdir' => 'asc',
        ]);

        $sortedhtml = $sortedresult['templates'][0]['html'];
        $sortedalphapos = strpos($sortedhtml, 'Alpha question');
        $sortedzulupos = strpos($sortedhtml, 'Zulu question');

        $this->assertNotFalse($sortedalphapos);
        $this->assertNotFalse($sortedzulupos);
        $this->assertLessThan($sortedalphapos, $sortedzulupos);

        $sortbyprefkey = 'hotquestion_sortby' . $module->id;
        $sortdirprefkey = 'hotquestion_sortdir' . $module->id;

        $this->assertEquals('question', get_user_preferences($sortbyprefkey, ''));
        $this->assertEquals('asc', get_user_preferences($sortdirprefkey, ''));

        $this->assertGreaterThan(0, $alphaid);
        $this->assertGreaterThan(0, $zuluid);
    }
}