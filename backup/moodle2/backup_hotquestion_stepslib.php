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
 * Define all the backup steps that will be used by the backup_hotquestion_activity_task.
 *
 * @package mod_hotquestion
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_hotquestion_activity_task
 */

/**
 * Define the complete hotquestion structure for backup, with file and id annotations
 */

defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine

/**
 * Define the complete choice structure for backup, with file and id annotations
 *
 * @package   mod_hotquestion
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_hotquestion_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure for the hotquestion activity.
     * @return void
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $hotquestion = new backup_nested_element(
            'hotquestion',
            [
                'id',
            ],
            [
                'name',
                'intro',
                'introformat',
                'submitdirections',
                'minquestionsview',
                'maxquestionsperuser',
                'timecreated',
                'timemodified',
                'timeopen',
                'timeclose',
                'viewaftertimeclose',
                'questionlabel',
                'teacherpriorityvisibility',
                'teacherprioritylabel',
                'heatvisibility',
                'heatlabel',
                'heatlimit',
                'anonymouspost',
                'authorhide',
                'approval',
                'approvallabel',
                'removelabel',
                'scale',
                'assessed',
                'assesstimestart',
                'assesstimefinish',
                'comments',
                'grade',
                'postmaxgrade',
                'factorheat',
                'factorpriority',
                'factorvote',
                'completionpost',
                'completionvote',
                'completionpass',
                'notifications',
            ]
        );

        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element(
            'grade',
            [
                'id',
            ],
            [
                'userid',
                'rawrating',
                'timemodified',
            ]
        );

        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element(
            'question',
            [
                'id',
            ],
            [
                'content',
                'format',
                'userid',
                'time',
                'anonymous',
                'approved',
                'tpriority',
                'mailed',
            ]
        );

        $rounds = new backup_nested_element('rounds');
        $round = new backup_nested_element(
            'round',
            [
                'id',
            ],
            [
                'starttime',
                'endtime',
            ]
        );

        $comments = new backup_nested_element('comments');
        $comment = new backup_nested_element(
            'comment',
            [
                'id',
            ],
            [
                'userid',
                'content',
                'format',
                'timecreated',
            ]
        );

        $votes = new backup_nested_element('votes');
        $vote = new backup_nested_element(
            'vote',
            [
                'id',
            ],
            [
                'question',
                'voter',
            ]
        );

        // Build the tree.
        $hotquestion->add_child($grades);
        $grades->add_child($grade);

        $hotquestion->add_child($questions);
        $questions->add_child($question);
        $question->add_child($comments);
        $comments->add_child($comment);

        $hotquestion->add_child($rounds);
        $rounds->add_child($round);

        $question->add_child($votes);
        $votes->add_child($vote);

        // Define sources.
        $hotquestion->set_source_table('hotquestion', ['id' => backup::VAR_ACTIVITYID]);

        // Rounds are activity structure and should be backed up independently of user data.
        $round->set_source_table('hotquestion_rounds', ['hotquestion' => backup::VAR_PARENTID]);

        // User-related elements only happen if we are including user info.
        if ($userinfo) {
            $grade->set_source_table('hotquestion_grades', ['hotquestion' => backup::VAR_PARENTID]);
            $question->set_source_table('hotquestion_questions', ['hotquestion' => backup::VAR_PARENTID]);
            $vote->set_source_table('hotquestion_votes', ['question' => backup::VAR_PARENTID]);
            $comment->set_source_sql(
                "SELECT c.id, c.userid, c.content, c.format, c.timecreated
                   FROM {comments} c
                  WHERE c.itemid = ?
                    AND c.component = 'mod_hotquestion'
                    AND c.commentarea = 'hotquestion_questions'",
                [backup::VAR_PARENTID]
            );
        }

        // Define id annotations.
        $grade->annotate_ids('user', 'userid');
        $question->annotate_ids('user', 'userid');
        $vote->annotate_ids('user', 'voter');
        $comment->annotate_ids('user', 'userid');

        // Define file annotations.
        $hotquestion->annotate_files('mod_hotquestion', 'intro', null); // This file area hasn't itemid.

        // Return the root element (hotquestion), wrapped into standard activity structure.
        return $this->prepare_activity_structure($hotquestion);
    }
}
