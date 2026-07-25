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

/**
 * Adhoc task to send digest-format hotquestion notifications.
 *
 * @package   mod_hotquestion
 * @copyright 2026 AL Rachels <drachels@drachels.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_user_digests extends \core\task\adhoc_task {
    use \core\task\logging_trait;

    /**
     * Send digest notifications.
     */
    public function execute() {
        global $DB;

        $recipient = \core_user::get_user($this->get_userid());
        if (empty($recipient) || empty($recipient->email) || !empty($recipient->deleted) || !empty($recipient->suspended)) {
            return;
        }

        $questionids = (array) $this->get_custom_data();
        $questionids = array_values(array_filter(array_map('intval', $questionids)));
        if (empty($questionids)) {
            return;
        }

        [$in, $params] = $DB->get_in_or_equal($questionids);
        $sql = "SELECT hqq.id, hqq.content, hqq.format,
                       hq.id AS hotquestionid, hq.course, hq.name
                  FROM {hotquestion_questions} hqq
                  JOIN {hotquestion} hq ON hq.id = hqq.hotquestion
                 WHERE hqq.id {$in}
              ORDER BY hq.course, hq.id, hqq.id";
        $questions = $DB->get_records_sql($sql, $params);

        if (empty($questions)) {
            return;
        }

        $lines = [];
        $courseid = 0;
        foreach ($questions as $question) {
            $cm = get_coursemodule_from_instance('hotquestion', $question->hotquestionid, $question->course, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $context = \context_module::instance($cm->id);
            if (!has_capability('mod/hotquestion:view', $context, $recipient)) {
                continue;
            }

            $courseid = $question->course;
            $content = trim(html_to_text(format_text($question->content, $question->format, ['context' => $context])));
            $lines[] = '- ' . format_string($question->name, true) . ': ' . shorten_text($content, 300);
            $lines[] = '  ' . (new \moodle_url('/mod/hotquestion/view.php', ['id' => $cm->id]))->out(false);
        }

        if (empty($lines)) {
            return;
        }

        $subject = get_string('pluginname', 'mod_hotquestion') . ': ' . get_string('messageprovider:digests', 'mod_hotquestion');
        $body = implode("\n", $lines);

        $fromuser = \core_user::get_noreply_user();
        $fromuser->customheaders = [
            'Precedence: Bulk',
            'X-Auto-Response-Suppress: All',
            'Auto-Submitted: auto-generated',
        ];

        $eventdata = new \core\message\message();
        $eventdata->courseid = $courseid ?: SITEID;
        $eventdata->component = 'mod_hotquestion';
        $eventdata->name = 'digests';
        $eventdata->userfrom = $fromuser;
        $eventdata->userto = $recipient;
        $eventdata->subject = $subject;
        $eventdata->fullmessage = $body;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = '<p>' . nl2br(s($body)) . '</p>';
        $eventdata->smallmessage = get_string('messageprovider:digests', 'mod_hotquestion');
        $eventdata->notification = 1;

        message_send($eventdata);
    }
}
