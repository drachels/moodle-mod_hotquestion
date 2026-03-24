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
 * Define all the restore steps that will be used by the restore_hotquestion_activity_task.
 *
 * @package mod_hotquestion
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one hotquestion activity.
 */

defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine

/**
 * Define the complete hotquestion structure for restore, with file and id annotations
 *
 * @package   mod_hotquestion
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_hotquestion_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the structure of the restore workflow.
     *
     * @return restore_path_element $structure
     */
    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('hotquestion', '/activity/hotquestion');
        $paths[] = new restore_path_element('hotquestion_round', '/activity/hotquestion/rounds/round');
        if ($userinfo) {
            $paths[] = new restore_path_element('hotquestion_grade', '/activity/hotquestion/grades/grade');
            $paths[] = new restore_path_element('hotquestion_question', '/activity/hotquestion/questions/question');
            $paths[] = new restore_path_element('hotquestion_comment', '/activity/hotquestion/questions/question/comments/comment');
            $paths[] = new restore_path_element('hotquestion_vote', '/activity/hotquestion/questions/question/votes/vote');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process a hotquestion restore.
     *
     * @param object $data The data in object form.
     * @return void
     */
    protected function process_hotquestion($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the hotquestion record.
        $newitemid = $DB->insert_record('hotquestion', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process a grade restore.
     * @param object $data The data in object form.
     * @return void
     */
    protected function process_hotquestion_grade($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->hotquestion = $this->get_new_parentid('hotquestion');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('hotquestion_grades', $data);
        $this->set_mapping('hotquestion_grade', $oldid, $newitemid);
    }

    /**
     * Process a question restore.
     * @param object $data The data in object form.
     * @return void
     */
    protected function process_hotquestion_question($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->hotquestion = $this->get_new_parentid('hotquestion');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('hotquestion_questions', $data);
        $this->set_mapping('hotquestion_question', $oldid, $newitemid);
    }

    /**
     * Process a round restore.
     * @param object $data The data in object form.
     * @return void
     */
    protected function process_hotquestion_round($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->hotquestion = $this->get_new_parentid('hotquestion');

        $newitemid = $DB->insert_record('hotquestion_rounds', $data);
    }

    /**
     * Process a comment restore.
     *
     * @param object $data The data in object form.
     * @return void
     */
    protected function process_hotquestion_comment($data) {
        global $DB;

        $data = (object)$data;
        $questionid = $this->get_new_parentid('hotquestion_question');
        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return;
        }

        $contextid = context_module::instance($this->task->get_moduleid())->id;
        $record = (object)[
            'contextid' => $contextid,
            'component' => 'mod_hotquestion',
            'commentarea' => 'hotquestion_questions',
            'itemid' => $questionid,
            'content' => $data->content,
            'format' => $data->format,
            'userid' => $userid,
            'timecreated' => $this->apply_date_offset($data->timecreated),
        ];
        $DB->insert_record('comments', $record);
    }

    /**
     * Process a vote restore.
     * @param object $data The data in object form.
     * @return void
     */
    protected function process_hotquestion_vote($data) {
        global $DB;

        $data = (object)$data;

        $data->question = $this->get_new_parentid('hotquestion_question');
        $data->voter = $this->get_mappingid('user', $data->voter);

        $newitemid = $DB->insert_record('hotquestion_votes', $data);
    }

    /**
     * Once the database tables have been fully restored, restore the files.
     * @return void
     */
    protected function after_execute() {
        // Add hotquestion related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_hotquestion', 'intro', null);
    }
}
