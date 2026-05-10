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

defined('MOODLE_INTERNAL') || die();

/**
 * Main scheduled task for hotquestion notifications.
 *
 * @package   mod_hotquestion
 * @copyright 2026 AL Rachels <drachels@drachels.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_task extends \core\task\scheduled_task {
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'mod_hotquestion');
    }

    /**
     * Queue ad-hoc notification tasks for new questions.
     */
    public function execute() {
        global $DB, $CFG;

        $timenow = time();
        $cutoff = $timenow - $CFG->maxeditingtime;

        $this->log_start('Fetching unmailed questions.');
        $questions = $this->get_unmailed_questions($cutoff);
        if (empty($questions)) {
            $this->log_finish('No questions found.', 1);
            return;
        }

        $this->log('Found ' . count($questions) . ' questions to process.', 1);
        $queuedusers = 0;
        $queueddigests = 0;
        $queuedindividual = 0;
        $processedquestionids = [];

        foreach ($questions as $question) {
            $recipients = $this->get_notification_recipients($question);
            if (empty($recipients)) {
                $this->log('No recipients found for question id ' . $question->id . '.', 1);
                continue;
            }

            $questionqueued = false;

            foreach ($recipients as $recipient) {
                $taskdata = [$question->id];
                $digestenabled = !empty($recipient->maildigest);

                if ($digestenabled) {
                    $task = new send_user_digests();
                    $task->set_userid($recipient->id);
                    $task->set_component('mod_hotquestion');
                    $task->set_custom_data($taskdata);
                    \core\task\manager::queue_adhoc_task($task, true);
                    $queueddigests++;
                    $questionqueued = true;
                } else {
                    $task = new send_user_notifications();
                    $task->set_userid($recipient->id);
                    $task->set_component('mod_hotquestion');
                    $task->set_custom_data($taskdata);
                    \core\task\manager::queue_adhoc_task($task, true);
                    $queuedindividual++;
                    $questionqueued = true;
                }

                $queuedusers++;
            }

            if ($questionqueued) {
                $processedquestionids[] = (int) $question->id;
            }
        }

        if (!empty($processedquestionids)) {
            [$in, $params] = $DB->get_in_or_equal($processedquestionids);
            $DB->set_field_select('hotquestion_questions', 'mailed', 1, "id {$in}", $params);
        } else {
            $this->log('No notification tasks were queued; mailed flags unchanged.', 1);
        }

        $this->log_finish(
            'Queued ' . $queuedindividual . ' individual and ' .
            $queueddigests . ' digest tasks for ' . $queuedusers . ' recipients.',
            1
        );
    }

    /**
     * Get questions awaiting notification.
     *
     * @param int $cutoff
     * @return array
     */
    protected function get_unmailed_questions($cutoff) {
        global $DB;

        $sql = "SELECT hqq.id,
                   hqq.hotquestion,
                   hqq.userid,
                   hqq.time
                  FROM {hotquestion_questions} hqq
                  JOIN {hotquestion} hq ON hq.id = hqq.hotquestion
                 WHERE hqq.mailed = 0
                   AND hqq.time <= :cutoff
                   AND hq.notifications = 1";
        $this->log($sql, 1);

        return $DB->get_records_sql($sql, ['cutoff' => $cutoff]);
    }

    /**
     * Get users who should receive notifications for a question.
     *
     * @param \stdClass $question
     * @return array
     */
    protected function get_notification_recipients($question) {
        global $DB;

        $hotquestion = $DB->get_record('hotquestion', ['id' => $question->hotquestion], 'id, course', MUST_EXIST);
        $cm = get_coursemodule_from_instance('hotquestion', $hotquestion->id, $hotquestion->course, false, IGNORE_MISSING);
        if (!$cm) {
            return [];
        }

        $context = \context_module::instance($cm->id);
        $fields = 'u.id, u.username, u.email, u.maildigest, u.mailformat, u.deleted, u.suspended';
        $users = get_enrolled_users($context, 'mod/hotquestion:rate', 0, $fields);

        $recipients = [];
        foreach ($users as $user) {
            if ((int) $user->id === (int) $question->userid) {
                continue;
            }
            if (!empty($user->deleted) || !empty($user->suspended) || empty($user->email)) {
                continue;
            }
            $recipients[$user->id] = $user;
        }

        return $recipients;
    }
}
