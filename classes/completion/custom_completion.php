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

declare(strict_types=1);

namespace mod_hotquestion\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the hotquestion activity.
 *
 * Class for defining mod_hotquestion's custom completion rules and fetching the completion statuses
 * of the custom completion rules for a given hotquestion instance and a user.
 *
 * @package mod_hotquestion
 * @copyright AL Rachels <drachels@drachels.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);
        $userid = $this->userid;
        $hotquestionid = $this->cm->instance;

        if (!$hotquestion = $DB->get_record('hotquestion', ['id' => $hotquestionid])) {
            throw new moodle_exception(get_string('incorrectmodule', 'hotquestion'));
        }

        $status = COMPLETION_INCOMPLETE;
        $questioncountparams = ['userid' => $userid, 'hotquestionid' => $hotquestionid];
        $questionvoteparams = ['userid' => $userid, 'hotquestionid' => $hotquestionid];

        $questionpostsql = "SELECT COUNT(hqq.id)
                           FROM {hotquestion_questions} hqq
                          WHERE hqq.hotquestion = :hotquestionid
                            AND hqq.userid = :userid";

        $questionvotesql = "SELECT COUNT(hv.id)
                           FROM {hotquestion_votes} hv
                           JOIN {hotquestion_questions} hqq ON hqq.id = hv.question
                          WHERE hqq.hotquestion = :hotquestionid
                            AND hv.voter = :userid";

        $questiongradesql = "SELECT *
                           FROM {hotquestion_grades} hqg
                           JOIN {hotquestion_questions} hqq ON hqg.hotquestion = hqq.id
                           JOIN {hotquestion} hq ON hq.id = hqg.hotquestion
                          WHERE hqg.userid = :userid
                            AND hqq.id = :hotquestionid";

        if ($rule == 'completionpost') {
            if ($status = $hotquestion->completionpost <=
                $DB->get_field_sql($questionpostsql, $questioncountparams)) {
                $status = $hotquestion->completionpost = 1;
            } else {
                $status = $hotquestion->completionpost = 0;
            }
        } else if ($rule == 'completionvote') {
            if ($status = $hotquestion->completionvote <=
                $DB->get_field_sql($questionvotesql, $questionvoteparams)) {
                $status = $hotquestion->completionvote = 1;
            } else {
                $status = $hotquestion->completionvote = 0;
            }
        } else if ($rule == 'completionpass') {
            if ($status = $hotquestion->completionpass <=
                $DB->get_field_sql($questioncountsql.
                    ' AND hqg.userid = $userid AND hqg.rawrating >= hqgrade',
                    $questioncountparams)) {
                $status = $hotquestion->completionpass = 1;
            } else {
                $status = $hotquestion->completionpass = 0;
            }
        }
        return $status ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completionpost',
            'completionvote',
            'completionpass',
        ];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $completionpost = $this->cm->customdata['customcompletionrules']['completionpost'] ?? 0;
        $completionvote = $this->cm->customdata['customcompletionrules']['completionvote'] ?? 0;
        $completionpass = $this->cm->customdata['customcompletionrules']['completionpass'] ?? 0;
        return [
            'completionpost' => get_string('completiondetail:post', 'hotquestion', $completionpost),
            'completionvote' => get_string('completiondetail:vote', 'hotquestion', $completionvote),
            'completionpass' => get_string('completiondetail:pass', 'hotquestion', $completionpass),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionpost',
            'completionvote',
            'completionpass',
            'completionusegrade',
            'completionpassgrade',
        ];
    }
}
