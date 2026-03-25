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
 * This file defines an adhoc task to send notifications.
 *
 * @package    mod_hotquestion
 * @copyright  2026 AL Rachels <drachels@drachels.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hotquestion\task;

/**
 * Adhoc task to send user hotquestion notifications.
 *
 * @package    mod_hotquestion
 * @copyright  2026 AL Rachels <drachels@drachels.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_user_notifications extends \core\task\adhoc_task {
    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * @var \stdClass   A shortcut to $USER.
     */
    protected $recipient;

    /**
     * @var \stdClass[] List of courses the questions are in, indexed by courseid.
     */
    protected $courses = [];

    /**
     * @var \stdClass[] List of hotquestions the questions are in, indexed by courseid.
     */
    protected $hotquestions = [];

    /**
     * @var int[] List of IDs for hotquestions in each course.
     */
    protected $coursehotquestions = [];

    /**
     * @var \stdClass[] List of rounds the questions are in, indexed by roundid.
     */
    protected $rounds = [];

    /**
     * @var int[] List of round IDs for each hotquestion.
     */
    protected $hotquestionrounds = [];

    /**
     * @var \stdClass[] List of questions the questions are in, indexed by roundid.
     */
    protected $questions = [];

    /**
     * @var bool[] Whether the user can view fullnames for each hotquestion.
     */
    protected $viewfullnames = [];

    /**
     * @var bool[] Whether the user can question in each round.
     */
    protected $canquestionto = [];

    /**
     * @var \renderer[] The renderers.
     */
    protected $renderers = [];

    /**
     * @var \core\message\inbound\address_manager The inbound message address manager.
     */
    protected $inboundmanager;

    /**
     * @var array List of users.
     */
    protected $users = [];

    /**
     * Send out messages.
     * @throws \moodle_exception
     */
    public function execute() {
        global $CFG;

        // Raise the time limit for each round.
        \core_php_time_limit::raise(120);

        $this->recipient = \core_user::get_user($this->get_userid());

        $data = $this->get_custom_data();

        $this->prepare_data((array) $data);

        $failedquestions = [];
        $markquestions = [];
        $errorcount = 0;
        $sentcount = 0;
        $this->log_start("Sending messages to {$this->recipient->username} ({$this->recipient->id})");
        foreach ($this->courses as $course) {
            $coursecontext = \context_course::instance($course->id);
            if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
                // The course is hidden and the user does not have access to it.
                // Permissions may have changed since it was queued.
                continue;
            }
            foreach ($this->coursehotquestions[$course->id] as $hotquestionid) {
                $hotquestion = $this->hotquestions[$hotquestionid];

                $cm = get_fast_modinfo($course)->instances['hotquestion'][$hotquestionid];
                $modcontext = \context_module::instance($cm->id);

                foreach (array_values($this->hotquestionrounds[$hotquestionid]) as $roundid) {
                    $round = $this->rounds[$roundid];

                    if (!hotquestion_user_can_see_round($hotquestion, $round, $modcontext, $this->recipient)) {
                        // User cannot see this round.
                        // Permissions may have changed since it was queued.
                        continue;
                    }

                    if (!\mod_hotquestion\subscriptions::is_subscribed($this->recipient->id, $hotquestion, $roundid, $cm)) {
                        // The user does not subscribe to this hotquestion as a whole, or to this specific round.
                        continue;
                    }

                    foreach ($this->questions[$roundid] as $question) {
                        if (!hotquestion_user_can_see_question($hotquestion, $round, $question, $this->recipient, $cm)) {
                            // User cannot see this question.
                            // Permissions may have changed since it was queued.
                            continue;
                        }

                        if ($this->send_question($course, $hotquestion, $round, $question, $cm, $modcontext)) {
                            $this->log("question {$question->id} sent", 1);
                            // Mark question as read if hotquestion_usermarksread is set off.
                            if (!$CFG->hotquestion_usermarksread) {
                                $markquestions[$question->id] = true;
                            }
                            $sentcount++;
                        } else {
                            $this->log("Failed to send question {$question->id}", 1);
                            $failedquestions[] = $question->id;
                            $errorcount++;
                        }
                    }
                }
            }
        }

        $this->log_finish("Sent {$sentcount} messages with {$errorcount} failures");
        if (!empty($markquestions)) {
            if (get_user_preferences('hotquestion_markasreadonnotification', 1, $this->recipient->id) == 1) {
                $this->log_start("Marking questions as read");
                $count = count($markquestions);
                hotquestion_tp_mark_questions_read($this->recipient, array_keys($markquestions));
                $this->log_finish("Marked {$count} questions as read");
            }
        }

        if ($errorcount > 0 && $sentcount === 0) {
            // All messages errored. So fail.
            // Checking if the task failed because of empty email address so that it doesn't get rescheduled.
            if (!empty($this->recipient->email)) {
                throw new \moodle_exception('Error sending questions.');
            } else {
                mtrace("Failed to send emails for the user with ID " .
                    $this->recipient->id . " due to an empty email address. Skipping re-queuing of the task.");
            }
        } else if ($errorcount > 0) {
            // Requeue failed messages as a new task.
            $task = new send_user_notifications();
            $task->set_userid($this->recipient->id);
            $task->set_custom_data($failedquestions);
            $task->set_component('mod_hotquestion');
            $task->set_next_run_time(time() + MINSECS);
            $task->set_fail_delay(MINSECS);
            \core\task\manager::reschedule_or_queue_adhoc_task($task);
        }
    }

    /**
     * Prepare all data for this run.
     *
     * Take all question ids, and fetch the relevant authors, hotquestions, hotquestions_questions, and courses for them.
     *
     * @param   int[]   $questionids The list of question IDs
     */
    protected function prepare_data(array $questionids) {
        global $DB;

        if (empty($questionids)) {
            return;
        }

        [$in, $params] = $DB->get_in_or_equal(array_values($questionids));
        $sql = "SELECT hqq.*, hq.id AS hotquestion, hq.course
                  FROM {hotquestion_questions} hqq
            INNER JOIN {hotquestion_rounds} d ON d.id = hqq.round
            INNER JOIN {hotquestion} hq ON hq.id = d.hotquestion
                 WHERE hqq.id {$in}";

        $questions = $DB->get_recordset_sql($sql, $params);
        $roundids = [];
        $hotquestionids = [];
        $courseids = [];
        $userids = [];
        foreach ($questions as $question) {
            $roundids[] = $question->round;
            $hotquestionids[] = $question->hotquestion;
            $courseids[] = $question->course;
            $userids[] = $question->userid;
            unset($question->hotquestion);
            if (!isset($this->questions[$question->round])) {
                $this->questions[$question->round] = [];
            }
            $this->questions[$question->round][$question->id] = $question;
        }
        $questions->close();

        if (empty($roundids)) {
            // All questions have been removed since the task was queued.
            return;
        }

        // Fetch all rounds.
        [$in, $params] = $DB->get_in_or_equal(array_values($roundids));
        $this->rounds = $DB->get_records_select('hotquestion_rounds', "id {$in}", $params);
        foreach ($this->rounds as $round) {
            if (empty($this->hotquestionrounds[$round->hotquestion])) {
                $this->hotquestionrounds[$round->hotquestion] = [];
            }
            $this->hotquestionrounds[$round->hotquestion][] = $round->id;
        }

        // Fetch all hotquestions.
        [$in, $params] = $DB->get_in_or_equal(array_values($hotquestionids));
        $this->hotquestions = $DB->get_records_select('hotquestion', "id {$in}", $params);
        foreach ($this->hotquestions as $hotquestion) {
            if (empty($this->coursehotquestions[$hotquestion->course])) {
                $this->coursehotquestions[$hotquestion->course] = [];
            }
            $this->coursehotquestions[$hotquestion->course][] = $hotquestion->id;
        }

        // Fetch all courses.
        [$in, $params] = $DB->get_in_or_equal(array_values($courseids));
        $this->courses = $DB->get_records_select('course', "id $in", $params);

        // Fetch all authors.
        [$in, $params] = $DB->get_in_or_equal(array_values($userids));
        $users = $DB->get_recordset_select('user', "id $in", $params);
        foreach ($users as $user) {
            $this->minimise_user_record($user);
            $this->users[$user->id] = $user;
        }
        $users->close();

        // Fill subscription caches for each hotquestion.
        // These are per-user.
        foreach (array_values($hotquestionids) as $id) {
            \mod_hotquestion\subscriptions::fill_subscription_cache($id);
            \mod_hotquestion\subscriptions::fill_round_subscription_cache($id);
        }
    }

    /**
     * Send the specified question for the current user.
     *
     * @param   \stdClass   $course
     * @param   \stdClass   $hotquestion
     * @param   \stdClass   $round
     * @param   \stdClass   $question
     * @param   \stdClass   $cm
     * @param   \context    $context
     */
    protected function send_question($course, $hotquestion, $round, $question, $cm, $context) {
        global $CFG, $PAGE;

        $author = $this->get_question_author($question->userid, $course, $hotquestion, $cm, $context);
        if (empty($author)) {
            return false;
        }

        // Prepare to actually send the question now, and build up the content.
        $cleanhotquestionname = str_replace('"', "'", strip_tags(format_string($hotquestion->name)));

        $shortname = format_string($course->shortname, true, [
                'context' => \context_course::instance($course->id),
            ]);

        // Generate a reply-to address from using the Inbound Message handler.
        $replyaddress = $this->get_reply_address($course, $hotquestion, $round, $question, $cm, $context);

        $data = new \mod_hotquestion\output\hotquestion_question_email(
            $course,
            $cm,
            $hotquestion,
            $round,
            $question,
            $author,
            $this->recipient,
            $this->can_question($course, $hotquestion, $round, $question, $cm, $context)
        );
        $data->viewfullnames = $this->can_view_fullnames($course, $hotquestion, $round, $question, $cm, $context);

        // Not all of these variables are used in the default string but are made available to support custom subjects.
        $site = get_site();
        $a = (object) [
            'subject' => $data->get_subject(),
            'hotquestionname' => $cleanhotquestionname,
            'sitefullname' => format_string($site->fullname),
            'siteshortname' => format_string($site->shortname),
            'courseidnumber' => $data->get_courseidnumber(),
            'coursefullname' => $data->get_coursefullname(),
            'courseshortname' => $data->get_coursename(),
        ];
        $questionsubject = html_to_text(get_string('questionmailsubject', 'hotquestion', $a), 0);

        // Message headers are stored against the message author.
        $author->customheaders = $this->get_message_headers($course, $hotquestion, $round, $question, $a, $data);

        $eventdata = new \core\message\message();
        $eventdata->courseid            = $course->id;
        $eventdata->component           = 'mod_hotquestion';
        $eventdata->name                = 'questions';
        $eventdata->userfrom            = $author;
        $eventdata->userto              = $this->recipient;
        $eventdata->subject             = $questionsubject;
        $eventdata->fullmessage         = $this->get_renderer()->render($data);
        $eventdata->fullmessageformat   = FORMAT_PLAIN;
        $eventdata->fullmessagehtml     = $this->get_renderer(true)->render($data);
        $eventdata->notification        = 1;
        $eventdata->replyto             = $replyaddress;
        if (!empty($replyaddress)) {
            // Add extra text to email messages if they can reply back.
            $eventdata->set_additional_content('email', [
                    'fullmessage' => [
                        'footer' => "\n\n" . get_string('replytoquestionbyemail', 'mod_hotquestion'),
                    ],
                    'fullmessagehtml' => [
                        'footer' => \html_writer::tag('p', get_string('replytoquestionbyemail', 'mod_hotquestion')),
                    ],
                ]);
        }

        $eventdata->smallmessage = get_string('smallmessage', 'hotquestion', (object) [
                'user' => fullname($author),
                'hotquestionname' => "$shortname: " . format_string($hotquestion->name, true) . ": " . $round->name,
                'message' => $question->message,
            ]);

        $contexturl = new \moodle_url('/mod/hotquestion/discuss.php', ['d' => $round->id], "p{$question->id}");
        $eventdata->contexturl = $contexturl->out();
        $eventdata->contexturlname = $round->name;
        // User image.
        $userpicture = new \user_picture($author);
        $userpicture->size = 1; // Use f1 size.
        $userpicture->includetoken = $this->recipient->id; // Generate an out-of-session token for the user receiving the message.
        $eventdata->customdata = [
            'cmid' => $cm->id,
            'instance' => $hotquestion->id,
            'roundid' => $round->id,
            'questionid' => $question->id,
            'notificationiconurl' => $userpicture->get_url($PAGE)->out(false),
            'actionbuttons' => [
                'reply' => get_string_manager()->get_string('reply', 'hotquestion', null, $eventdata->userto->lang),
            ],
        ];

        return message_send($eventdata);
    }

    /**
     * Fetch and initialise the question author.
     *
     * @param   int         $userid The id of the user to fetch
     * @param   \stdClass   $course
     * @param   \stdClass   $hotquestion
     * @param   \stdClass   $cm
     * @param   \context    $context
     * @return  \stdClass
     */
    protected function get_question_author($userid, $course, $hotquestion, $cm, $context) {
        if (!isset($this->users[$userid])) {
            // This user no longer exists.
            return false;
        }

        $user = $this->users[$userid];

        if (!isset($user->groups)) {
            // Initialise the groups list.
            $user->groups = [];
        }

        if (!isset($user->groups[$hotquestion->id])) {
            $user->groups[$hotquestion->id] = groups_get_all_groups($course->id, $user->id, $cm->groupingid);
        }

        // Clone the user object to prevent leaks between messages.
        return (object) (array) $user;
    }

    /**
     * Helper to fetch the required renderer, instantiating as required.
     *
     * @param   bool    $html Whether to fetch the HTML renderer
     * @return  \core_renderer
     */
    protected function get_renderer($html = false) {
        global $PAGE;

        $target = $html ? 'htmlemail' : 'textemail';

        if (!isset($this->renderers[$target])) {
            $this->renderers[$target] = $PAGE->get_renderer('mod_hotquestion', 'email', $target);
        }

        return $this->renderers[$target];
    }

    /**
     * Get the list of message headers.
     *
     * @param   \stdClass   $course
     * @param   \stdClass   $hotquestion
     * @param   \stdClass   $round
     * @param   \stdClass   $question
     * @param   \stdClass   $a The list of strings for this  question
     * @param   \core\message\message $message The message to be sent
     * @return  \stdClass
     */
    protected function get_message_headers($course, $hotquestion, $round, $question, $a, $message) {
        $cleanhotquestionname = str_replace('"', "'", strip_tags(format_string($hotquestion->name)));
        $viewurl = new \moodle_url('/mod/hotquestion/view.php', ['f' => $hotquestion->id]);

        $headers = [
            // Headers to make emails easier to track.
            'List-Id: "' . $cleanhotquestionname . '" ' . generate_email_messageid('moodlehotquestion' . $hotquestion->id),
            'List-Help: ' . $viewurl->out(),
            'Message-ID: ' . hotquestion_get_email_message_id($question->id, $this->recipient->id),
            'X-Course-Id: ' . $course->id,
            'X-Course-Name: ' . format_string($course->fullname, true),

            // Headers to help prevent auto-responders.
            'Precedence: Bulk',
            'X-Auto-Response-Suppress: All',
            'Auto-Submitted: auto-generated',
            'List-Unsubscribe: <' . $message->get_unsubscriberoundlink() . '>',
        ];

        $rootid = hotquestion_get_email_message_id($round->firstquestion, $this->recipient->id);

        if ($question->parent) {
            // This question is a reply, so add reply header (RFC 2822).
            $parentid = hotquestion_get_email_message_id($question->parent, $this->recipient->id);
            $headers[] = "In-Reply-To: $parentid";

            // If the question is deeply nested we also reference the parent message id and
            // the root message id (if different) to aid threading when parts of the email
            // conversation have been deleted (RFC1036).
            if ($question->parent != $round->firstquestion) {
                $headers[] = "References: $rootid $parentid";
            } else {
                $headers[] = "References: $parentid";
            }
        } else {
            // If the message IDs that Moodle creates are overwritten then referencing these
            // IDs here will enable then to still thread correctly with the first email.
            $headers[] = "In-Reply-To: $rootid";
            $headers[] = "References: $rootid";
        }

        // MS Outlook / Office uses poorly documented and non standard headers, including
        // Thread-Topic which overrides the Subject and shouldn't contain Re: or Fwd: etc.
        $aclone = (object) (array) $a;
        $aclone->subject = $round->name;
        $threadtopic = html_to_text(get_string('questionmailsubject', 'hotquestion', $aclone), 0);
        $headers[] = "Thread-Topic: $threadtopic";
        $headers[] = "Thread-Index: " . substr($rootid, 1, 28);

        return $headers;
    }

    /**
     * Get a no-reply address for this user to reply to the current question.
     *
     * @param   \stdClass   $course
     * @param   \stdClass   $hotquestion
     * @param   \stdClass   $round
     * @param   \stdClass   $question
     * @param   \stdClass   $cm
     * @param   \context    $context
     * @return  string
     */
    protected function get_reply_address($course, $hotquestion, $round, $question, $cm, $context) {
        if ($this->can_question($course, $hotquestion, $round, $question, $cm, $context)) {
            // Generate a reply-to address from using the Inbound Message handler.
            $this->inboundmanager->set_data($question->id);
            return $this->inboundmanager->generate($this->recipient->id);
        }

        // This will be controlled by the event.
        return null;
    }

    /**
     * Check whether the user can question.
     *
     * @param   \stdClass   $course
     * @param   \stdClass   $hotquestion
     * @param   \stdClass   $round
     * @param   \stdClass   $question
     * @param   \stdClass   $cm
     * @param   \context    $context
     * @return  bool
     */
    protected function can_question($course, $hotquestion, $round, $question, $cm, $context) {
        if (!isset($this->canquestionto[$round->id])) {
            $this->canquestionto[$round->id] = hotquestion_user_can_question(
                $hotquestion,
                $round,
                $this->recipient,
                $cm,
                $course,
                $context
            );
        }
        return $this->canquestionto[$round->id];
    }

    /**
     * Check whether the user can view full names of other users.
     *
     * @param   \stdClass   $course
     * @param   \stdClass   $hotquestion
     * @param   \stdClass   $round
     * @param   \stdClass   $question
     * @param   \stdClass   $cm
     * @param   \context    $context
     * @return  bool
     */
    protected function can_view_fullnames($course, $hotquestion, $round, $question, $cm, $context) {
        if (!isset($this->viewfullnames[$hotquestion->id])) {
            $this->viewfullnames[$hotquestion->id] = has_capability('moodle/site:viewfullnames', $context, $this->recipient->id);
        }

        return $this->viewfullnames[$hotquestion->id];
    }

    /**
     * Removes properties from user record that are not necessary for sending question notifications.
     *
     * @param   \stdClass   $user
     */
    protected function minimise_user_record(\stdClass $user) {
        // We store large amount of users in one huge array, make sure we do not store info there we do not actually
        // need in mail generation code or messaging.
        unset($user->institution);
        unset($user->department);
        unset($user->address);
        unset($user->city);
        unset($user->currentlogin);
        unset($user->description);
        unset($user->descriptionformat);
    }
}
