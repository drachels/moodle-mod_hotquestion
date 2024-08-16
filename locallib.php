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
 * Internal library of functions for module hotquestion.
 *
 * All the hotquestion specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_hotquestion\event\remove_vote;
use mod_hotquestion\event\update_vote;
use mod_hotquestion\event\add_question;
use mod_hotquestion\event\add_round;
use mod_hotquestion\event\remove_question;
use mod_hotquestion\event\remove_round;
use mod_hotquestion\event\download_questions;
use mod_hotquestion\local\results;

defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine
define('HOTQUESTION_EVENT_TYPE_OPEN', 'open');
define('HOTQUESTION_EVENT_TYPE_CLOSE', 'close');
/**
 * Standard base class for mod_hotquestion.
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_hotquestion {

    /** @var int callback arg - the instance of the current hotquestion activity */
    public $instance;
    /** @var int callback arg - the instance of the current hotquestion module */
    public $cm;
    /** @var int callback arg - the instance of the current hotquestion course */
    public $course;

    /** @var int callback arg - the id of current round of questions */
    protected $currentround;
    /** @var int callback arg - the id of previous round of questions */
    protected $prevround;
    /** @var int callback arg - the id of next round of questions */
    protected $nextround;
    /** @var int callback arg - the total round count in this hot question */
    protected $roundcount;
    /** @var int callback arg - the round being looked at in this hot question */
    protected $currentroundx;

    /**
     * Constructor for the base hotquestion class.
     *
     * Note: For $coursemodule you can supply a stdclass if you like, but it
     * will be more efficient to supply a cm_info object.
     *
     * @param mixed $cmid
     * @param mixed $roundid
     */
    public function __construct($cmid, $roundid = -1) {
        global $DB;
        $this->cm = get_coursemodule_from_id('hotquestion', $cmid, 0, false, MUST_EXIST);
        $this->course = $DB->get_record('course', ['id' => $this->cm->course], '*', MUST_EXIST);
        $this->instance = $DB->get_record('hotquestion', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $this->set_currentround($roundid);

        // Contrib by ecastro ULPGC, for grades callbacks.
        $this->instance->cmid = $cmid;
        $this->instance->cmidumber = $this->cm->idnumber;
    }

    /**
     * Return whether the user has voted on specified question.
     *
     * Called from function vote_on($question).
     * @param int $question question id
     * @param int $user user id. -1 means current user
     * @return boolean
     */
    public function has_voted($question, $user = -1) {
        global $USER, $DB;
        if ($user == -1) {
            $user = $USER->id;
        }
        return $DB->record_exists('hotquestion_votes', ['question' => $question, 'voter' => $user]);
    }

    /**
     * Vote on question.
     *
     * Called from view.php.
     * @param int $question The question id.
     */
    public function vote_on($question) {
        global $CFG, $DB, $USER;
        $votes = new StdClass();
        $context = context_module::instance($this->cm->id);
        $question = $DB->get_record('hotquestion_questions', ['id' => $question]);

        if ($question && $this->can_vote_on($question)) {
            // Trigger and log a vote event.
            $params = [
                'objectid' => $this->cm->id,
                'context' => $context,
            ];
            $event = update_vote::create($params);
            $event->trigger();

            if (!$this->has_voted($question->id)) {
                $votes->question = $question->id;
                $votes->voter = $USER->id;
                $DB->insert_record('hotquestion_votes', $votes);
            }
            // Update viewed completion state for current user.
            $this->update_completion_state();
        }
        // Contrib by ecastro ULPGC, update grades for questions author and voters.
        // 20220623 Moved so entering viewgrades.php page always updates to the latest grade.
        $this->update_users_grades([$question->userid, $USER->id]);
    }

    /**
     * Remove vote on question.
     *
     * Called from view.php.
     * @param int $question The question id.
     */
    public function remove_vote($question) {
        global $CFG, $DB, $USER;
        $votes = new StdClass();
        $context = context_module::instance($this->cm->id);
        $question = $DB->get_record('hotquestion_questions', ['id' => $question]);
        if ($question && $this->can_vote_on($question)) {

            // Trigger and log a remove_vote event.
            $params = [
                'objectid' => $this->cm->id,
                'context' => $context,
            ];
            $event = remove_vote::create($params);
            $event->trigger();

            if ($this->has_voted($question->id)) {
                $DB->delete_records('hotquestion_votes', ['question' => $question->id, 'voter' => $USER->id]);
            }
            // Update viewed completion state for current user.
            $this->update_completion_state();
            // Contrib by ecastro ULPGC, update grades for question author and voters.
            $this->update_users_grades([$question->userid, $USER->id]);
        }
    }

    /**
     * Whether can vote on the question.
     *
     * @param int $question
     * @param stdClass $user null means current user
     */
    public function can_vote_on($question, $user = null) {
        global $USER, $DB;

        if (is_int($question)) {
            $question = $DB->get_record('hotquestion_questions', ['id' => $question]);
        }
        if (empty($user)) {
            $user = $USER;
        }

        // Is this question in last round?
        $rounds = $DB->get_records('hotquestion_rounds', ['hotquestion' => $this->instance->id], 'id DESC', '*', 0, 1);
        $lastround = reset($rounds);
        $inlastround = $question->time >= $lastround->starttime;

        return $question->userid != $user->id && $inlastround;
    }

    /**
     * Calculates number of remaining votes (heat) available to this user in the current round.
     *
     * Added 20200529.
     * @param int $hq
     * @param int $user
     */
    public function heat_tally($hq, $user = null) {
        global $USER, $CFG, $DB;

        $params = [
            $hq->currentround->id,
            $hq->currentround->hotquestion,
            $USER->id,
        ];

        $sql = "SELECT hqq.id AS questionid,
                       COUNT(hqv.voter) AS heat,
                       hqq.hotquestion AS hotquestionid,
                       hqr.id AS round,
                       hqq.content AS content,
                       hqq.userid AS userid,
                       hqv.voter AS voter,
                       hqq.time AS time,
                       hqq.anonymous AS anonymous,
                       hqq.tpriority AS tpriority,
                       hqq.approved AS approved
                  FROM {hotquestion_rounds} hqr
             LEFT JOIN {hotquestion_questions} hqq ON hqr.hotquestion=hqq.hotquestion
             LEFT JOIN {hotquestion_votes} hqv ON hqv.question=hqq.id
                  JOIN {user} u ON u.id = hqq.userid
                 WHERE hqr.id = ?
                   AND hqr.hotquestion = ?
                   AND hqq.time > hqr.starttime
                   AND hqr.endtime = 0
                   AND hqv.voter = ?
              GROUP BY hqq.id, hqr.id, hqv.voter, hqq.hotquestion, hqq.content,
                        hqq.userid, hqv.voter, hqq.time, hqq.anonymous, hqq.tpriority,
                        hqq.approved
              ORDER BY hqq.hotquestion ASC, tpriority DESC, heat DESC";

        $tally = count($DB->get_records_sql($sql, $params));

        $results = ($hq->instance->heatlimit - $tally);
        return $results;
    }

    /**
     * Open a new round and close the old one.
     */
    public function add_new_round() {
        global $USER, $CFG, $DB;

        // Close the latest round.
        $rounds = $DB->get_records('hotquestion_rounds', ['hotquestion' => $this->instance->id], 'id DESC', '*', 0, 1);
        $old = array_pop($rounds);
        $old->endtime = time();
        $DB->update_record('hotquestion_rounds', $old);

        // Open a new round.
        $new = new StdClass();
        $new->hotquestion = $this->instance->id;
        $new->starttime = time();
        $new->endtime = 0;
        $context = context_module::instance($this->cm->id);
        $rid = $DB->insert_record('hotquestion_rounds', $new);

        // Trigger add new round event.
        $params = [
            'objectid' => $this->cm->id,
            'context' => $context,
        ];
        $event = add_round::create($params);
        $event->trigger();
    }

    /**
     * Set current round to show.
     * @param int $roundid
     */
    public function set_currentround($roundid = -1) {
        global $DB;

        // Get all the rounds for this Hot Question.
        $rounds = $DB->get_records('hotquestion_rounds', ['hotquestion' => $this->instance->id], 'id ASC');

        // 20210214 Get total number of rounds for the current Hot Question activity.
        $this->roundcount = (count($rounds));

        // If there are no rounds, it is a new Hot Question activity and we need to create the first round for it.
        if (empty($rounds)) {
            // Create the first round.
            $round = new StdClass();
            $round->starttime = time();
            $round->endtime = 0;
            $round->hotquestion = $this->instance->id;
            $round->id = $DB->insert_record('hotquestion_rounds', $round);
            $rounds[] = $round;
        }

        if ($roundid != -1 && array_key_exists($roundid, $rounds)) {
            $this->currentround = $rounds[$roundid];
            $ids = array_keys($rounds);
            // Search previous round.
            $currentkey = array_search($roundid, $ids);
            // 20210215 $currentkey contains the virtual number - 1 of the current round being looked at.
            // Correct the virtual number by adding 1.
            $this->currentroundx = $currentkey + 1;

            if (array_key_exists($currentkey - 1, $ids)) {
                $this->prevround = $rounds[$ids[$currentkey - 1]];
            } else {
                $this->prevround = null;
            }

            // Search next round.
            if (array_key_exists($currentkey + 1, $ids)) {
                $this->nextround = $rounds[$ids[$currentkey + 1]];
            } else {
                $this->nextround = null;
            }
        } else {
            // Use the last round.
            $this->currentround = array_pop($rounds);
            $this->prevround = array_pop($rounds);
            $this->nextround = null;
        }
        return $roundid;
    }

    /**
     * Return current round.
     *
     * @return object
     */
    public function get_currentround() {
        return $this->currentround;
    }

    /**
     * Return previous round.
     *
     * @return object
     */
    public function get_prevround() {
        return $this->prevround;
    }

    /**
     * Return next round.
     *
     * @return object
     */
    public function get_nextround() {
        return $this->nextround;
    }

    /**
     * Return next round.
     *
     * @return object
     */
    public function get_roundcount() {
        return $this->roundcount;
    }

    /**
     * Return next round.
     *
     * @return object
     */
    public function get_currentroundx() {
        return $this->currentroundx;
    }

    /**
     * Return questions according to $currentround.
     *
     * Sort order is priority descending, votecount descending,
     * and time descending from most recent to oldest.
     * @return all questions with vote count in current round.
     */
    public function get_questions() {
        global $DB;

        if ($this->currentround->endtime == 0) {
            $this->currentround->endtime = 0xFFFFFFFF;  // Hack.
        }
        $params = [
            $this->instance->id,
            $this->currentround->starttime,
            $this->currentround->endtime,
        ];
        // 20210306 Added format to the selection.
        return $DB->get_records_sql('SELECT q.id, q.hotquestion, q.content, q.format, q.userid, q.time,
            q.anonymous, q.approved, q.tpriority, count(v.voter) as votecount
            FROM {hotquestion_questions} q
            LEFT JOIN {hotquestion_votes} v
            ON v.question = q.id
            WHERE q.hotquestion = ?
            AND q.time >= ?
            AND q.time <= ?
            GROUP BY q.id, q.hotquestion, q.content, q.userid, q.time,
                     q.anonymous, q.approved, q.tpriority
            ORDER BY q.approved DESC, tpriority DESC, votecount DESC, q.time DESC', $params);
    }

    /**
     * Remove selected question and any votes that it might have.
     *
     * @return object
     */
    public function remove_question() {
        global $CFG, $DB;

        $data = new StdClass();
        $data->hotquestion = $this->instance->id;
        $context = context_module::instance($this->cm->id);
        // Trigger remove_question event.
        $params = [
            'objectid' => $this->cm->id,
            'context' => $context,
        ];
        $event = remove_question::create($params);
        $event->trigger();

        if (null !== (required_param('q', PARAM_INT))) {
            $questionid = required_param('q', PARAM_INT);
            $itemid = required_param('q', PARAM_INT);
            $dbquestion = $DB->get_record('hotquestion_questions', ['id' => $questionid]);

            // Contrib by ecastro ULPGC.
            $users = $this->get_question_voters($questionid);
            $users[] = $dbquestion->userid;
            // Contrib by ecastro ULPGC.
            $DB->delete_records('hotquestion_questions', ['id' => $dbquestion->id]);
            // 20220510 Deleted $dbvote line of code.
            // Delete all votes on the question that was just deleted.
            $DB->delete_records('hotquestion_votes', ['question' => $dbquestion->id]);
            // 20220510 Delete all comments on the question that was just deleted.
            $DB->delete_records('comments', ['itemid' => $itemid, 'component' => 'mod_hotquestion']);

            // Update completion state for question creator.
            $this->update_completion_state($dbquestion->userid);
            // Update completion state for voters.
            foreach ($users as $user) {
                $this->update_completion_state($user);
            }
            // Contrib by ecastro ULPGC, update grades for question author and voters.
            $this->update_users_grades($users);
        }
        return $this->currentround;
    }

    /**
     * If the currently being viewed round is empty, delete it.
     * Otherwise, remove any questions in the round currently being viewed,
     * remove any votes for each question being removed,
     * then remove the currently being viewed round.
     * @return nothing
     */
    public function remove_round() {
        global $CFG, $DB;

        $data = new StdClass();
        $data->hotquestion = $this->instance->id;
        $context = context_module::instance($this->cm->id);
        // Trigger remove_question event.
        $params = [
            'objectid' => $this->cm->id,
            'context' => $context,
        ];
        $event = remove_round::create($params);
        $event->trigger();

        $roundid = required_param('round', PARAM_INT);
        if ($this->currentround->endtime == 0) {
            $this->currentround->endtime = 0xFFFFFFFF;  // Hack.
        }
        $params = [
            $this->instance->id,
            $this->currentround->starttime,
            $this->currentround->endtime,
        ];
        $questions = $DB->get_records_sql('SELECT q.id, q.hotquestion, q.content, q.userid, q.time,
            q.anonymous, q.approved, q.tpriority, count(v.voter) as votecount
            FROM {hotquestion_questions} q
            LEFT JOIN {hotquestion_votes} v
            ON v.question = q.id
            WHERE q.hotquestion = ?
            AND q.time >= ?
            AND q.time <= ?
            GROUP BY q.id, q.hotquestion, q.content, q.userid, q.time, q.anonymous,
                     q.approved, q.tpriority
            ORDER BY votecount DESC, q.time DESC', $params);

        if ($questions) {
            foreach ($questions as $q) {
                $questionid = $q->id; // Get id of first question on the page to delete.
                $dbquestion = $DB->get_record('hotquestion_questions', ['id' => $questionid]);
                // Contrib by ecastro ULPGC.
                $users = $this->get_question_voters($questionid);
                $users[] = $dbquestion->userid;
                // Contrib by ecastro ULPGC.
                $DB->delete_records('hotquestion_questions', ['id' => $dbquestion->id]);
                // Get an array of all votes on the question that was just deleted, then delete them.
                $dbvote = $DB->get_records('hotquestion_votes', ['question' => $questionid]);
                $DB->delete_records('hotquestion_votes', ['question' => $dbquestion->id]);

                // Update completion state for question creator.
                $this->update_completion_state($dbquestion->userid);
                // Update completion state for voters.
                foreach ($users as $user) {
                    $this->update_completion_state($user);
                }
                // Contrib by ecastro ULPGC, update grades for question author and voters.
                $this->update_users_grades($users);
            }
            // Now that all questions and votes are gone, remove the round.
            $dbround = $DB->get_record('hotquestion_rounds', ['id' => $roundid]);
            $DB->delete_records('hotquestion_rounds', ['id' => $dbround->id]);
        } else {
            // This round is empty so delete without having to remove questions and votes.
            $dbround = $DB->get_record('hotquestion_rounds', ['id' => $roundid]);
            $DB->delete_records('hotquestion_rounds', ['id' => $dbround->id]);
        }
        // Now we need to see if we need a new round or have one we can still use.
        $rounds = $DB->get_records('hotquestion_rounds', ['hotquestion' => $this->instance->id], 'id DESC');

        foreach ($rounds as $rnd) {
            if ($rnd->endtime == 0) {
                // Deleted a closed round so just return.
                return;
            } else {
                // Deleted our open round so create a new round.
                $round = new StdClass();
                $round->starttime = time();
                $round->endtime = 0;
                $round->hotquestion = $this->instance->id;
                $round->id = $DB->insert_record('hotquestion_rounds', $round);
                $rounds[] = $round;
                return;
            }
        }
        return $this->currentround;
    }

    /**
     * Download questions.
     * @param array $chq
     * @param string $filename - The filename to use.
     * @param string $delimiter - The character to use as a delimiter.
     * @return nothing
     */
    public function download_questions($chq, $filename = "export.csv", $delimiter=";") {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir.'/csvlib.class.php');

        $context = context_module::instance($this->cm->id);

        // Trigger download_questions event.
        $params = [
            'objectid' => $this->cm->id,
            'context' => $context,
        ];
        $event = download_questions::create($params);
        $event->trigger();

        // Construct sql query and filename based on admin or teacher/manager.
        // Add filename details based on course and HQ activity name.
        $csv = new csv_export_writer();
        $strhotquestion = get_string('hotquestion', 'hotquestion');
        $fields = [];

        if (is_siteadmin($USER->id)) {
            // For an admin, we want every hotquestion activity.
            $whichhqs = ('AND hq.hotquestion IS NOT NULL');
            $csv->filename = clean_filename(get_string('exportfilenamep1', 'hotquestion'));
        } else {
            // For a teacher, we want only the current hotquestion activity.
            $whichhqs = ('AND hq.hotquestion = ');
            $whichhqs .= (':thisinstid');

            $csv->filename = clean_filename(($this->course->shortname).'_');
            $csv->filename .= clean_filename(($this->instance->name));
        }
            // Add fields with the column labels for ONLY the current HQ activity.
        $fields = [
            get_string('firstname'),
            get_string('lastname'),
            get_string('userid', 'hotquestion'),
            get_string('hotquestion', 'hotquestion').' ID',
            get_string('question', 'hotquestion').' ID',
            get_string('time', 'hotquestion'),
            get_string('anonymous', 'hotquestion'),
            $this->instance->teacherprioritylabel,
            $this->instance->heatlabel,
            $this->instance->approvallabel,
            $this->instance->questionlabel,
            get_string('comments'),
        ];
        $csv->filename .= clean_filename(get_string('exportfilenamep2', 'hotquestion').gmdate("Ymd_Hi").'GMT.csv');

        // Now add this instance id that's needed in the sql for teachers and managers downloads.
        $fields = [$fields, 'thisinstid' => $this->instance->id];

        if ($CFG->dbtype == 'pgsql') {
            $sql = "SELECT hq.id AS question,
                      CASE
                           WHEN u.firstname = 'Guest user'
                           THEN u.lastname || 'Anonymous'
                           ELSE u.firstname
                       END AS firstname,
                           u.lastname AS lastname,
                           hq.hotquestion AS hotquestion,
                           hq.content AS content,
                           hq.userid AS userid,
                           to_char(to_timestamp(hq.time), 'YYYY-MM-DD HH24:MI:SS') AS time,
                           hq.anonymous AS anonymous,
                           hq.tpriority AS tpriority,
                           COUNT(hv.voter) AS heat,
                           hq.approved AS approved,
                           h.course AS course,
                           h.teacherprioritylabel AS teacherprioritylabel,
                           h.heatlabel AS heatlabel,
                           h.approvallabel AS approvallabel,
                           h.questionlabel AS questionlabel
                     FROM {hotquestion_questions} hq
                LEFT JOIN {hotquestion_votes} hv ON hv.question=hq.id
                     JOIN {hotquestion} h ON h.id = hq.hotquestion
                     JOIN {user} u ON u.id = hq.userid
                    WHERE hq.userid > 0 ";
        } else {
            $sql = "SELECT hq.id AS question,
                      CASE
                           WHEN u.firstname = 'Guest user'
                           THEN CONCAT(u.lastname, 'Anonymous')
                           ELSE u.firstname
                       END AS 'firstname',
                           u.lastname AS 'lastname',
                           hq.hotquestion AS hotquestion,
                           hq.content AS content,
                           hq.userid AS userid,
                           FROM_UNIXTIME(hq.time) AS TIME,
                           hq.anonymous AS anonymous,
                           hq.tpriority AS tpriority,
                           COUNT(hv.voter) AS heat,
                           hq.approved AS approved,
                           h.course AS course,
                           h.teacherprioritylabel AS teacherprioritylabel,
                           h.heatlabel AS heatlabel,
                           h.approvallabel AS approvallabel,
                           h.questionlabel AS questionlabel
                     FROM {hotquestion_questions} hq
                LEFT JOIN {hotquestion_votes} hv ON hv.question = hq.id
                     JOIN {hotquestion} h ON h.id = hq.hotquestion
                     JOIN {user} u ON u.id = hq.userid
                    WHERE hq.userid > 0 ";
        }

        $sql .= ($whichhqs);
        $sql .= " GROUP BY u.lastname, u.firstname, hq.hotquestion, hq.id, hq.content,
                            hq.userid, hq.time, hq.anonymous, hq.tpriority, hq.approved,
                            h.course, h.teacherprioritylabel, h.heatlabel, h.approvallabel,h.questionlabel
                  ORDER BY hq.hotquestion ASC, u.lastname ASC, u.firstname ASC, hq.id ASC, tpriority DESC, heat";

        // Add the list of users and HotQuestions to our data array.
        if ($hqs = $DB->get_records_sql($sql, $fields)) {
            $firstrowflag = 1;
            if (is_siteadmin($USER->id)) {
                $currenthqhotquestion = !empty($hqs[1]->hotquestion) ? $hqs[1]->hotquestion : 'Hotquestion';
            } else {
                $currenthqhotquestion = '';
            }
            foreach ($hqs as $q) {
                $fields2 = [
                    get_string('firstname'),
                    get_string('lastname'),
                    get_string('userid', 'hotquestion'),
                    get_string('hotquestion', 'hotquestion').' ID',
                    get_string('question', 'hotquestion').' ID',
                    get_string('time', 'hotquestion'),
                    get_string('anonymous', 'hotquestion'),
                    $q->teacherprioritylabel,
                    $q->heatlabel,
                    $q->approvallabel,
                    $q->questionlabel,
                    get_string('comments'),
                ];

                // 20220818 Initialize variable for any comments for the next question.
                $comment = '';
                // 20220818 If there are any, get the comments for each question to add in the export file.
                // 20221124 Modified to include the component.
                if ($cmts = $DB->get_records('comments', [
                    'itemid' => $q->question,
                    'component' => 'mod_hotquestion',
                    ],
                    'userid,
                    content,
                    timecreated'
                    )) {

                    $temp = count($cmts);
                    $comment .= '('.$temp.' '.get_string('comments').') ';
                    foreach ($cmts as $cmt) {
                        $comment .= get_string('user').' '.$cmt->userid.' commented: '.$cmt->content.' | ';
                    }
                }
                // 20220819 Split admins output into sections by HotQuestions activities.
                if ((($currenthqhotquestion <> $q->hotquestion) && (is_siteadmin($USER->id))) || ($firstrowflag)) {
                    $currenthqhotquestion = $q->hotquestion;
                    // 20220819 Add the course shortname and the HQ activity name to our data array.
                    $currentcrsname = $DB->get_record('course', ['id' => $q->course], 'shortname');
                    $currenthqname = $DB->get_record('hotquestion', ['id' => $q->hotquestion], 'name');
                    $blankrow = [' ', null];

                    // 20220820 Only include filename, date, and URL only on the first row of the export.
                    // 20220820 Add a blank line before each HQ activity output, except for the first HQ activity.
                    if (!$firstrowflag) {
                        $csv->add_data($blankrow);
                        $activityinfo = [
                            get_string('course').': '.$currentcrsname->shortname,
                            get_string('activity').': '.$currenthqname->name,
                        ];
                    } else {
                        $activityinfo = [
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            get_string('exportfilenamep2', 'hotquestion').
                                gmdate("Ymd_Hi").get_string('for', 'hotquestion').
                                $CFG->wwwroot,
                        ];
                        $csv->add_data($activityinfo);
                        $activityinfo = [
                            get_string('course').': '.$currentcrsname->shortname,
                            get_string('activity').': '.$currenthqname->name,
                        ];
                    }
                    $csv->add_data($activityinfo);
                    $csv->add_data($fields2);
                    $firstrowflag = 0;
                }
                // 20220821 Cleaning the content to remove all the paragraph tags to make things easier to read.
                $cleanedcontent = format_string($q->content,
                                                $striplinks = true,
                                                $options = null);
                $output = [
                    $q->firstname,
                    $q->lastname,
                    $q->userid,
                    $q->hotquestion,
                    $q->question,
                    $q->time,
                    $q->anonymous,
                    $q->tpriority,
                    $q->heat,
                    $q->approved,
                    $cleanedcontent,
                    $comment,
                ];
                $csv->add_data($output);
            }
        }
        // Download the completed array of questions and comments.
        $csv->download_file();
        exit;
    }

    /**
     * Toggle approval go/stop of current question in current round.
     *
     * @param var $question
     * @return nothing
     */
    public function approve_question($question) {
        global $CFG, $DB, $USER;
        $context = context_module::instance($this->cm->id);
        $question = $DB->get_record('hotquestion_questions', ['id' => $question]);

        if ($question->approved) {
            // If currently approved, toggle to disapproved.
            $question->approved = '0';
            $DB->update_record('hotquestion_questions', $question);
        } else {
            // If currently disapproved, toggle to approved.
            $question->approved = '1';
            $DB->update_record('hotquestion_questions', $question);
        }
        $this->update_users_grades([$question->userid, $USER->id]);
        return;
    }

    /**
     * Set teacher priority of current question in current round.
     *
     * @param int $u The priority up(1) or down(0) flag.
     * @param int $question The question id to change the teacher priority for.
     */
    public function tpriority_change($u, $question) {
        global $CFG, $DB, $USER;

        $context = context_module::instance($this->cm->id);
        $question = $DB->get_record('hotquestion_questions', ['id' => $question]);

        if ($u) {
            // If priority flag is 1, increase priority by 1.
            $question->tpriority = ++$question->tpriority;
            $DB->update_record('hotquestion_questions', $question);
        } else {
            // If priority flag is 0, decrease priority by 1.
            $question->tpriority = --$question->tpriority;
            $DB->update_record('hotquestion_questions', $question);
        }
        $this->update_users_grades([$question->userid, $USER->id]);
    }

    // Contrib by ecastro ULPGC.

    /**
     * Get the user rating in this activity, by posts and heat/votes.
     *
     * Function is called ONLY when a user is on view.php but via renderer.php page.
     *
     * @param int $userid The single user to calculate the rating for.
     * @return float $rating number
     */
    public function calculate_user_ratings($userid = null): float {
        global $DB, $USER;

        if (!$userid) {
            $userid = $USER->id;
        }
        // Get any questions by this user and any votes if they have some.
        $sql = "SELECT q.id, q.approved, q.tpriority, count(v.voter) as votes
                  FROM {hotquestion_questions} q
             LEFT JOIN {hotquestion_votes} v ON v.question = q.id
                 WHERE q.hotquestion = ? AND q.userid = ? AND q.anonymous = 0
              GROUP BY q.id ";
        $params = [$this->instance->id, $userid];
        $questions = $DB->get_records_sql($sql, $params);
        $grade = 0;
        // If the user added any questions, add to the current user rating.
        foreach ($questions as $question) {
            // 20220623 Add to the current user rating only if the question is approved.
            if ($question->approved) {
                // Add/subtract to the current user rating based on teacher priority.
                $qrate = ($question->tpriority) ? $question->tpriority : $this->instance->factorpriority / 100;

                // Add/subtract to the current user rating based on teacher priority and heat given.
                $grade += $qrate + $question->votes * $this->instance->factorheat / 100;
            }
        }
        // Get any votes made by this user.
        $sql = "SELECT COUNT(v.id)
                  FROM {hotquestion_votes} v
                  JOIN {hotquestion_questions} q ON v.question = q.id
                 WHERE q.hotquestion = ? AND v.voter = ? ";
        $params = [$this->instance->id, $userid];
        $votes = $DB->count_records_sql($sql, $params);
        // If the user voted, add to the current user rating.
        if ($votes > 0) {
            // Add/subtract to the current user rating based on heat received.
            $grade += $votes * $this->instance->factorvote / 100;
        }

        return $grade;
    }

    /**
     * Gets the users that had voted a given question.
     *
     * @param int $questionid The question id.
     * @return array Array of int userids or empty if none.
     */
    public function get_question_voters(int $questionid): array {
        global $DB;

        $voters = $DB->get_records_menu('hotquestion_votes', ['question' => $questionid], '', 'id, voter');

        if ($voters) {
            return array_values($voters);
        }
        return [];
    }

    /**
     * Recalculates ratings and grades for users related to a question.
     * The author of the question and the voters.
     *
     * @param array $users The userids of users to update.
     */
    public function update_users_grades(array $users) {
        global $DB;

        if (empty($users)) {
            return false;
        }

        list($insql, $params) = $DB->get_in_or_equal($users);
        $select = "userid $insql AND hotquestion = ? ";
        $params[] = $this->instance->id;
        $grades = $DB->get_records_select('hotquestion_grades',
                                          $select,
                                          $params,
                                          '',
                                          'userid,
                                          id,
                                          hotquestion,
                                          rawrating,
                                          timemodified');

        $now = time();
        $newgrade = new stdClass();
        $newgrade->hotquestion = $this->instance->id;
        $newgrade->timemodified = $now;
        foreach ($users as $userid) {
            $rating = $this->calculate_user_ratings($userid);
            if (isset($grades[$userid])) {
                // Existing user grade, update if changed.
                $grade = $grades[$userid];
                if ($rating != $grade->rawrating) {
                    $grade->rawrating = $rating;
                    $grade->timemodified = $now;
                    $DB->update_record('hotquestion_grades', $grade);
                }
            } else {
                // New user grade, create.
                $newgrade->rawrating = $rating;
                $newgrade->userid = $userid;
                $DB->insert_record('hotquestion_grades', $newgrade);
            }
            // Calling the function in lib.php at about line 807.
            hotquestion_update_grades($this->instance, $userid);
        }
    }

    /**
     * Update completion state.
     *
     * @param int $user
     * @return void
     */
    public function update_completion_state($user = null) {
        $completion = new completion_info($this->course);
        if (!$completion->is_enabled($this->cm)) {
            return;
        }
        $completion->set_module_viewed($this->cm);

        // 20240706 Added to update completion state after a user adds heat or teacher adds to a students priority/grade.
        $cm = $this->cm;
        $ci = new completion_info($this->course);
        if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $ci->update_state($cm, COMPLETION_UNKNOWN, null);
        }
    }

    /**
     * Finds all hotquestion notifications that have yet to be mailed out, and mails them.
     *
     * Cron function to be run periodically according to the moodle cron.
     *
     * @return bool
     */
    public function cron() {
        $completion = new completion_info($this->course);
        if (!$completion->is_enabled($this->cm)) {
            return;
        }
        $completion->set_module_viewed($this->cm);
    }
}
