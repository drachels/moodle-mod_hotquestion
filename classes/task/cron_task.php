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

namespace mod_hotquestion\task;
defined('MOODLE_INTERNAL') || die(); // phpcs:ignore
use context_module;
use stdClass;

require_once($CFG->dirroot . '/mod/hotquestion/locallib.php');


/**
 * A main scheduled task for the hotquestion cron.
 *
 * @package   mod_hotquestion
 * @copyright 2024 AL Rachels <drachels@drachels.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_task extends \core\task\scheduled_task {
    // Use the logging trait to get the defined logging.
    use \core\task\logging_trait;

    /**
     * @var The list of courses which contain hotquestion activities with notifications to be sent.
     */
    protected $courses = [];

    /**
     * @var The list of hotquestion activities which contain questions to be sent.
     */
    protected $hotquestions = [];

    /**
     * @var The list of rounds which contain questions to be sent.
     */
    protected $rounds = [];

    /**
     * @var The list of hotquestions_questions to send notifications for.
     */
    protected $questions = [];

    /**
     * @var The list of question authors.
     */
    protected $users = [];

    /**
     * @var The list of hotquestion activity teachers.
     */
    protected $teachers = [];

    /**
     * @var The list of adhoc data for sending.
     */
    protected $adhocdata = [];

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'mod_hotquestion');
    }

    /**
     * Run hotquestion cron.
     */
    public function execute() {
        //global $CFG, $USER, $DB;
        global $CFG, $DB;

        $timenow = time();

        $endtime   = $timenow - $CFG->maxeditingtime;
        //$starttime = $endtime - (2 * DAYSECS);
        $starttime = $endtime - (2 * DAYSECS);
        $starttime = 0;
        $this->log_start("Fetching unmailed questions.");
        if (!$questions = $this->get_unmailed_questions($starttime, $endtime, $timenow)) {
            $this->log_finish("No questions found.", 1);
            return false;
        }

        //$this->log_finish("Done");

        // Process question data and turn into adhoc tasks.
        $this->process_questions_data($questions);

        // Mark new question notifications as sent.
        list($in, $params) = $DB->get_in_or_equal(array_keys($questions));
        $DB->set_field_select('hotquestion_questions', 'mailed', 1, "id {$in}", $params);
    }


    /**
     * Process all questions and convert to appropriated hoc tasks.
     *
     * @param   \stdClass[] $questions
     */
    protected function process_questions_data($questions) {
        $roundids = [];
        $hotquestionids = [];
        $courseids = [];

        $this->log_start("Processing question information");

        $start = microtime(true);
        foreach ($questions as $id => $question) {
            //$roundids[$question->round] = true;
            $hotquestionids[$question->hotquestion] = true;
            $courseids[$question->course] = true;
            $this->add_data_for_question($question);
            $this->questions[$id] = $question;
        }
        $this->log_finish(sprintf("Processed %s questions", count($this->questions)));

        if (empty($this->questions)) {
            $this->log("No questions found. Returning early.");
            return;
        }

        // Please note, this order is intentional.
        // The hotquestion cache makes use of the course.
        $this->log_start("Filling caches");

        $start = microtime(true);
        $this->log_start("Filling course cache", 1);
        $this->fill_course_cache(array_keys($courseids));
        $this->log_finish("Done", 1);

        $this->log_start("Filling hotquestion cache", 1);
        $this->fill_hotquestion_cache(array_keys($hotquestionids));
        $this->log_finish("Done", 1);

        $this->log_start("Filling round cache", 1);
        $this->fill_round_cache(array_keys($roundids));
        $this->log_finish("Done", 1);

        $this->log_start("Filling user subscription cache", 1);
        $this->fill_user_subscription_cache();
        $this->log_finish("Done", 1);

        $this->log_start("Filling digest cache", 1);
        $this->fill_digest_cache();
        $this->log_finish("Done", 1);

        $this->log_finish("All caches filled");

        $this->log_start("Queueing user tasks.");
        $this->queue_user_tasks();
        $this->log_finish("All tasks queued.");
    }

    /**
     * Fill the course cache.
     *
     * @param   int[]       $courseids
     */
    protected function fill_course_cache($courseids) {
        global $DB;

        list($in, $params) = $DB->get_in_or_equal($courseids);
        $this->courses = $DB->get_records_select('course', "id $in", $params);
    }

    /**
     * Fill the hotquestion cache.
     *
     * @param   int[]       $hotquestionids
     */
    protected function fill_hotquestion_cache($hotquestionids) {
        global $DB;

        $requiredfields = [
                'id',
                'course',
                'forcesubscribe',
                'type',
            ];
        list($in, $params) = $DB->get_in_or_equal($hotquestionids);
        $this->hotquestions = $DB->get_records_select('hotquestion', "id $in", $params, '', implode(', ', $requiredfields));
        foreach ($this->hotquestions as $id => $hotquestion) {
            \mod_hotquestion\subscriptions::fill_subscription_cache($id);
            \mod_hotquestion\subscriptions::fill_round_subscription_cache($id);
        }
    }
    /**
     * Fill the round cache.
     *
     * @param   int[]       $roundids
     */
    protected function fill_round_cache($roundids) {
        global $DB;

        if (empty($roundids)) {
            $this->rounds = [];
        } else {

            $requiredfields = [
                    'id',
                    'hotquestion',
                    'starttime',
                    'endtime',
                ];

            list($in, $params) = $DB->get_in_or_equal($roundids);
            $this->rounds = $DB->get_records_select(
                    'hotquestion_rounds', "id $in", $params, '', implode(', ', $requiredfields));
        }
    }


    /**
     * Fill the cache of user digest preferences. //Not sure if I need this function converted to use by HotQuestion
     */
/*
    protected function fill_digest_cache() {
        global $DB;

        if (empty($this->users)) {
            return;
        }
        // Get the list of hotquestion subscriptions for per-user per-hotquestion maildigest settings.
        list($in, $params) = $DB->get_in_or_equal(array_keys($this->users));
        $digestspreferences = $DB->get_recordset_select(
                'hotquestion_digests', "userid $in", $params, '', 'id, userid, hotquestion, maildigest');
        foreach ($digestspreferences as $digestpreference) {
            if (!isset($this->digestusers[$digestpreference->hotquestion])) {
                $this->digestusers[$digestpreference->hotquestion] = [];
            }
            $this->digestusers[$digestpreference->hotquestion][$digestpreference->userid] = $digestpreference->maildigest;
        }
        $digestspreferences->close();
    }
*/

    /**
     * Add dsta for the current hotquestion question to the structure of adhoc data.
     *
     * @param   \stdClass   $question
     */
    protected function add_data_for_question($question) {
        if (!isset($this->adhocdata[$question->course])) {
            $this->adhocdata[$question->course] = [];
        }

        if (!isset($this->adhocdata[$question->course][$question->hotquestion])) {
            $this->adhocdata[$question->course][$question->hotquestion] = [];
        }

        if (!isset($this->adhocdata[$question->course][$question->hotquestion][$question->round])) {
            $this->adhocdata[$question->course][$question->hotquestion][$question->round] = [];
        }

        $this->adhocdata[$question->course][$question->hotquestion][$question->round][$question->id] = $question->id;
    }

    /**
     * Fill the cache of user subscriptions. // Not sure if I will need this function. Might redo as noting whether a user has posted a question?
     */
/*
    protected function fill_user_subscription_cache() {
        foreach ($this->hotquestions as $hotquestion) {
            $cm = get_fast_modinfo($this->courses[$hotquestion->course])->instances['hotquestion'][$hotquestion->id];
            $modcontext = \context_module::instance($cm->id);

            $this->subscribedusers[$hotquestion->id] = [];
            if ($users = \mod_hotquestion\subscriptions::fetch_subscribed_users($hotquestion, 0, $modcontext, 'u.id, u.maildigest', true)) {
                foreach ($users as $user) {
                    // This user is subscribed to this hotquestion.
                    $this->subscribedusers[$hotquestion->id][$user->id] = $user->id;
                    if (!isset($this->users[$user->id])) {
                        // Store minimal user info.
                        $this->users[$user->id] = $user;
                    }
                }
                // Release memory.
                unset($users);
            }
        }
    }
*/

    /**
     * Queue the user tasks.
     */
    protected function queue_user_tasks() {
        global $CFG, $DB;

        $timenow = time();
        $sitetimezone = \core_date::get_server_timezone();
        $counts = [
            'digests' => 0,
            'individuals' => 0,
            'users' => 0,
            'ignored' => 0,
            'messages' => 0,
        ];
        $this->log("Processing " . count($this->users) . " users", 1);
        foreach ($this->users as $user) {
            $usercounts = [
                'digests' => 0,
                'messages' => 0,
            ];

            $send = false;
            // Setup this user so that the capabilities are cached, and environment matches receiving user.
            \core\cron::setup_user($user);

            list($individualquestiondata, $digestquestiondata) = $this->fetch_questions_for_user($user);

            if (!empty($digestquestiondata)) {
                // Insert all of the records for the digest.
                $DB->insert_records('hotquestion_queue', $digestquestiondata);
                $servermidnight = usergetmidnight($timenow, $sitetimezone);
                $digesttime = $servermidnight + ($CFG->digestmailtime * 3600);

                if ($digesttime < $timenow) {
                    // Digest time is in the past. Get a new time for tomorrow.
                    $servermidnight = usergetmidnight($timenow + DAYSECS, $sitetimezone);
                    $digesttime = $servermidnight + ($CFG->digestmailtime * 3600);
                }

                $task = new \mod_hotquestion\task\send_user_digests();
                $task->set_userid($user->id);
                $task->set_component('mod_hotquestion');
                $task->set_custom_data(['servermidnight' => $servermidnight]);
                $task->set_next_run_time($digesttime);
                \core\task\manager::reschedule_or_queue_adhoc_task($task);
                $usercounts['digests']++;
                $send = true;
            }

            if (!empty($individualquestiondata)) {
                $usercounts['messages'] += count($individualquestiondata);

                $task = new \mod_hotquestion\task\send_user_notifications();
                $task->set_userid($user->id);
                $task->set_custom_data($individualquestiondata);
                $task->set_component('mod_hotquestion');
                \core\task\manager::queue_adhoc_task($task);
                $counts['individuals']++;
                $send = true;
            }

            if ($send) {
                $counts['users']++;
                $counts['messages'] += $usercounts['messages'];
                $counts['digests'] += $usercounts['digests'];
            } else {
                $counts['ignored']++;
            }

            $this->log(sprintf("Queued %d digests and %d messages for %s",
                    $usercounts['digests'],
                    $usercounts['messages'],
                    $user->id
                ), 2);
        }
        $this->log(
            sprintf(
                "Queued %d digests, and %d individual tasks for %d question mails. " .
                "Unique users: %d (%d ignored)",
                $counts['digests'],
                $counts['individuals'],
                $counts['messages'],
                $counts['users'],
                $counts['ignored']
            ), 1);
    }

    /**
     * Fetch questions for this user.
     *
     * @param   \stdClass   $user The user to fetch questions for.
     */
    protected function fetch_questions_for_user($user) {
        // We maintain a mapping of user groups for each hotquestion.
        $usergroups = [];
        $digeststructure = [];

        $questionstructure = $this->adhocdata;
        $questionstosend = [];
        foreach ($questionstructure as $courseid => $hotquestionids) {
            $course = $this->courses[$courseid];
            foreach ($hotquestionids as $hotquestionid => $roundids) {
                $hotquestion = $this->hotquestions[$hotquestionid];
                $maildigest = hotquestion_get_user_maildigest_bulk($this->digestusers, $user, $hotquestionid);

                if (!isset($this->subscribedusers[$hotquestionid][$user->id])) {
                    // This user has no subscription of any kind to this hotquestion.
                    // Do not send them any questions at all.
                    unset($questionstructure[$courseid][$hotquestionid]);
                    continue;
                }

                $subscriptiontime = \mod_hotquestion\subscriptions::fetch_round_subscription($hotquestion->id, $user->id);

                $cm = get_fast_modinfo($course)->instances['hotquestion'][$hotquestionid];
                foreach ($roundids as $roundid => $questionids) {
                    $round = $this->rounds[$roundid];
                    if (!\mod_hotquestion\subscriptions::is_subscribed($user->id, $hotquestion, $roundid, $cm)) {
                        // The user does not subscribe to this hotquestion as a whole, or to this specific round.
                        unset($questionstructure[$courseid][$hotquestionid][$roundid]);
                        continue;
                    }

                    if ($round->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {
                        // This round has a groupmode set (SEPARATEGROUPS or VISIBLEGROUPS).
                        // Check whether the user can view it based on their groups.
                        if (!isset($usergroups[$hotquestion->id])) {
                            $usergroups[$hotquestion->id] = groups_get_all_groups($courseid, $user->id, $cm->groupingid);
                        }

                        if (!isset($usergroups[$hotquestion->id][$round->groupid])) {
                            // This user is not a member of this group, or the group no longer exists.

                            $modcontext = \context_module::instance($cm->id);
                            if (!has_capability('moodle/site:accessallgroups', $modcontext, $user)) {
                                // This user does not have the accessallgroups and is not a member of the group.
                                // Do not send questions from other groups when in SEPARATEGROUPS or VISIBLEGROUPS.
                                unset($questionstructure[$courseid][$hotquestionid][$roundid]);
                                continue;
                            }
                        }
                    }

                    foreach ($questionids as $questionid) {
                        $question = $this->questions[$questionid];
                        if ($subscriptiontime) {
                            // Skip questions if the user subscribed to the round after it was created.
                            $subscribedafter = isset($subscriptiontime[$question->round]);
                            $subscribedafter = $subscribedafter && ($subscriptiontime[$question->round] > $question->created);
                            if ($subscribedafter) {
                                // The user subscribed to the round/hotquestion after this question was created.
                                unset($questionstructure[$courseid][$hotquestionid][$roundid][$questionid]);
                                continue;
                            }
                        }

                        if ($maildigest > 0) {
                            // This user wants the mails to be in digest form.
                            $digeststructure[] = (object) [
                                'userid' => $user->id,
                                'roundid' => $round->id,
                                'questionid' => $question->id,
                                'timemodified' => $question->created,
                            ];
                            unset($questionstructure[$courseid][$hotquestionid][$roundid][$questionid]);
                            continue;
                        } else {
                            // Add this question to the list of questionids to be sent.
                            $questionstosend[] = $questionid;
                        }
                    }
                }

                if (empty($questionstructure[$courseid][$hotquestionid])) {
                    // This user is not subscribed to any rounds in this hotquestion at all.
                    unset($questionstructure[$courseid][$hotquestionid]);
                    continue;
                }
            }
            if (empty($questionstructure[$courseid])) {
                // This user is not subscribed to any hotquestions in this course.
                unset($questionstructure[$courseid]);
            }
        }

        return [$questionstosend, $digeststructure];
    }

    /**
     * Returns a list of all new questions that have not been mailed yet
     *
     * @param int $start time questions created after this time
     * @param int $endtime questions created before this
     * @param int $now used for timed rounds only
     * @return array
     */
    protected function get_unmailed_questions($starttime, $endtime, $now = null) {
        global $CFG, $DB;

        $sql = "SELECT hqq.*, hq.course, hq.name, hqg.hotquestion
                  FROM {hotquestion_questions} hqq
                  JOIN {hotquestion} hq ON hqq.hotquestion = hq.id
                                    JOIN {hotquestion_grades} hqg ON hq.id = hqg.hotquestion
                 WHERE hqq.mailed <> ?
                   AND hqg.timemodified < ?
                   AND hqg.timemodified > 0
                   AND hq.notifications = '1'";

        return $DB->get_records_sql($sql, [$starttime, $endtime]);

/*
        $params = array();
        //$params['mailed'] = HOTQUESTION_MAILED_PENDING;
        $params['mailed'] = 0;
        $params['ptimestart'] = $start time;
        $params['ptimeend'] = $endtime;
        $params['mailnow'] = 1;

        if (!empty($CFG->hotquestion_enabletimedquestions)) {
            if (empty($now)) {
                $now = time();
            }
            $selectsql = "AND (hqq.created >= :ptimestart OR d.timestart >= :pptimestart)";
            $params['pptimestart'] = $start time;
            $timedsql = "AND (d.timestart < :dtimestart AND (d.timeend = 0 OR d.timeend > :dtimeend))";
            $params['dtimestart'] = $now;
            $params['dtimeend'] = $now;
        } else {
            $timedsql = "";
            $selectsql = "AND p.created >= :ptimestart";
        }

        return $DB->get_records_sql(
               "SELECT
                    p.id,
                    p.round,
                    d.hotquestion,
                    d.course,
                    p.created,
                    p.parent,
                    p.userid
                  FROM {hotquestion_questions} p
                  JOIN {hotquestion_rounds} d ON d.id = p.round
                 WHERE p.mailed = :mailed
                $selectsql
                   AND (p.created < :ptimeend OR p.mailnow = :mailnow)
                $timedsql
                 ORDER BY p.modified ASC",
             $params);
*/
    }

    /**
     * Return entries that have not sent notifications.
     *
     * @param int $cutofftime
     * @return object
     */
    protected function hotquestion_get_unsent_notifications($cutofftime) {
        global $DB;

        $sql = "SELECT hqq.*, hq.course, hq.name
                  FROM {hotquestion_questions} hqq
                  JOIN {hotquestion} hq ON hqq.hotquestion = hq.id
                  JOIN (hotquestion_grades) hqg on hq.id = hqg.hotquestion
                 WHERE hqq.mailed = 0
                   AND hqg.timemodified < ?
                   AND hqg.timemodified > 0
                   AND hq.notifications = '1'";

        return $DB->get_records_sql($sql, [$cutofftime]);
    }
}
