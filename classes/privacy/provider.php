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
 * Data provider.
 *
 * @package    mod_hotquestion
 * @copyright  2016 AL Rachels
 * @author     AL Rachels < drachels@drachels.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hotquestion\privacy;

use context;
use context_helper;
use context_user;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\{writer,
                                transform,
                                helper,
                                contextlist,
                                approved_contextlist,
                                approved_userlist,
                                userlist,
                                deletion_criteria};

defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine

/**
 * Data provider class for the Hot Question activity module.
 *
 * @package    mod_hotquestion
 * @copyright  2018 AL Rachels
 * @author     AL Rachels <drachels@drachels.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider,
                          \core_privacy\local\request\plugin\provider,
                          \core_privacy\local\request\core_userlist_provider {
    /**
     * Get a description of the data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection of metadata items.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'hotquestion_questions',
            [
                'id' => 'privacy:metadata:hotquestion_questions:id',
                'hotquestion' => 'privacy:metadata:hotquestion_questions:hotquestion',
                'content' => 'privacy:metadata:hotquestion_questions:content',
                'userid' => 'privacy:metadata:hotquestion_questions:userid',
                'time' => 'privacy:metadata:hotquestion_questions:time',
                'anonymous' => 'privacy:metadata:hotquestion_questions:anonymous',
                'approved' => 'privacy:metadata:hotquestion_questions:approved',
                'tpriority' => 'privacy:metadata:hotquestion_questions:tpriority',
            ],
            'privacy:metadata:hotquestion_questions'
        );
        $collection->add_database_table(
            'hotquestion_votes',
            [
                'id' => 'privacy:metadata:hotquestion_votes:id',
                'question' => 'privacy:metadata:hotquestion_votes:question',
                'voter' => 'privacy:metadata:hotquestion_votes:voter',
            ],
            'privacy:metadata:hotquestion_votes'
        );
        $collection->add_database_table(
            'hotquestion_grades',
            [
                'userid' => 'privacy:metadata:hotquestion_grades:userid',
                'rawrating' => 'privacy:metadata:hotquestion_grades:rawrating',
            ],
            'privacy:metadata:hotquestion_grades'
        );
        return $collection;
    }

    /** @var int */
    private static $modid;

    /**
     * Get the module id for the 'hotquestion' module.
     * @return false|mixed
     * @throws \dml_exception
     */
    private static function get_modid() {
        global $DB;
        if (self::$modid === null) {
            self::$modid = $DB->get_field('modules', 'id', ['name' => 'hotquestion']);
        }
        return self::$modid;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();
        $modid = self::get_modid();
        if (!$modid) {
            return $contextlist; // Hot Question module not installed.
        }

        $params = [
            'modid' => $modid,
            'contextlevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid,
        ];

        // User-created hotquestion entries.
        $sql = "
            SELECT c.id
              FROM {context} c
              JOIN {course_modules} cm
                ON cm.id = c.instanceid
               AND c.contextlevel = :contextlevel
               AND cm.module = :modid
              JOIN {hotquestion} h
                ON h.id = cm.instance
              JOIN {hotquestion_questions} hq
                ON hq.hotquestion = h.id
         LEFT JOIN {hotquestion_votes} hv
                ON hv.question = hq.id
             WHERE (hq.userid = :userid1)
                OR (hv.voter = :userid2)";

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $modid = self::get_modid();
        if (!$modid) {
            return; // HotQuestion module not installed.
        }

        $params = [
            'modid' => $modid,
            'contextlevel' => CONTEXT_MODULE,
            'contextid' => $context->id,
        ];

        // Find users with hotquestion_question entries.
        $sql = "
            SELECT hq.userid
              FROM {hotquestion_questions} hq
              JOIN {hotquestion} h
                ON h.id = hq.hotquestion
              JOIN {course_modules} cm
                ON cm.instance = h.id
               AND cm.module = :modid
              JOIN {context} ctx
                ON ctx.instanceid = cm.id
               AND ctx.contextlevel = :contextlevel
             WHERE ctx.id = :contextid
        ";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "
            SELECT hqg.userid
              FROM {hotquestion_grades} hqg
              JOIN {hotquestion_questions} hq
                ON hq.id = hqg.hotquestion
              JOIN {hotquestion} h
                ON h.id = hq.hotquestion
              JOIN {course_modules} cm
                ON cm.instance = h.id
               AND cm.module = :modid
              JOIN {context} ctx
                ON ctx.instanceid = cm.id
               AND ctx.contextlevel = :contextlevel
             WHERE ctx.id = :contextid
        ";
        $userlist->add_from_sql('userid', $sql, $params);

    }
    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $user = $contextlist->get_user();
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        // Export the votes.
        $sql = "SELECT cm.id AS cmid,
                       hq.id AS id,
                       hq.hotquestion AS hotquestion,
                       hv.id AS id,
                       hv.question AS question,
                       hv.voter AS voter
                  FROM {context} c
            INNER JOIN {course_modules} cm
                    ON cm.id = c.instanceid
             LEFT JOIN {hotquestion_questions} hq
                    ON hq.hotquestion = cm.instance
             LEFT JOIN {hotquestion_votes} hv
                    ON hv.question = hq.id
                 WHERE c.id $contextsql
                   AND hv.voter = :userid2
              ORDER BY cm.id, hq.id DESC";

        $params = [
            'userid1' => $user->id,
            'userid2' => $user->id,
        ] + $contextparams;
        $lastcmid = null;
        $itemdata = [];

        $votes = $DB->get_recordset_sql($sql, $params);

        foreach ($votes as $vote) {
            if ($lastcmid !== $vote->cmid) {
                if ($itemdata) {
                    self::export_hqvote_data_for_user($itemdata, $lastcmid, $user);
                }
                $itemdata = [];
                $lastcmid = $vote->cmid;
            }
            $itemdata[] = (object)[
                'id' => $vote->id,
                'question' => $vote->question,
                'voter' => $vote->voter,
            ];
        }
        $votes->close();
        if ($itemdata) {
            self::export_hotquestion_data_for_user($itemdata, $lastcmid, $user);
        }

        // Export the questions.
        $sql = "SELECT cm.id AS cmid,
                       hq.hotquestion as hotquestion,
                       hq.content as content,
                       hq.time as time,
                       hq.anonymous as anonymous,
                       hq.approved as approved,
                       hq.tpriority as tpriority
                  FROM {context} c
            INNER JOIN {course_modules} cm
                    ON cm.id = c.instanceid
            INNER JOIN {hotquestion_questions} hq
                    ON hq.hotquestion = cm.instance
                 WHERE c.id $contextsql
                   AND hq.userid = :userid
              ORDER BY cm.id";

        $params = ['userid' => $user->id] + $contextparams;
        $questions = $DB->get_recordset_sql($sql, $params);

        foreach ($questions as $question) {
            if ($lastcmid !== $question->cmid) {
                if ($itemdata) {
                    self::export_hotquestion_data_for_user($itemdata, $lastcmid, $user);
                }
                $itemdata = [];
                $lastcmid = $question->cmid;
            }

            $itemdata[] = (object)[
                'hotquestion' => $question->hotquestion,
                'content' => $question->content,
                'time' => transform::datetime($question->time),
                'anonymous' => transform::yesno($question->anonymous),
                'approved' => transform::yesno($question->approved),
                'tpriority' => $question->tpriority,
            ];
        }
        $questions->close();
        if ($itemdata) {
            self::export_hotquestion_data_for_user($itemdata, $lastcmid, $user);
        }

        // Export grades.
        $user = $contextlist->get_user();
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT hqg.id AS id,
                       cm.id AS cmid,
                       hqg.hotquestion AS hotquestion,
                       hqg.id AS id,
                       hqg.userid AS userid,
                       hqg.rawrating as rating,
                       hqg.timemodified as timemodified
                  FROM {context} c
            INNER JOIN {course_modules} cm
                    ON cm.id = c.instanceid
             LEFT JOIN {hotquestion_questions} hq
                    ON hq.hotquestion = cm.instance
             LEFT JOIN {hotquestion_grades} hqg
                    ON hqg.hotquestion = hq.id
                 WHERE c.id $contextsql
                   AND hqg.userid = :userid
              ORDER BY cm.id, hq.id DESC";

        $params = [
                'userid' => $user->id,
            ] + $contextparams;

        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            $context = \context_module::instance($record->cmid);
            $data = (object)[
              'hotquestion' => $record->hotquestion,
              'grade' => $record->rating,
              'time' => transform::datetime($record->timemodified)
            ];
            writer::with_context($context)->export_data([get_string('privacy:metadata:hotquestion_grades:path',
                'mod_hotquestion')], $data);
        }

    }


    /**
     * Export the supplied personal vote data for a single hotquestion activity.
     *
     * @param array $items The vote data for each of the items in the hot question.
     * @param int $cmid
     * @param \stdClass $user The user record.
     */
    public static function export_hqvote_data_for_user(array $items, int $cmid, \stdClass $user) {

        // Fetch the generic module data for the hotquestion.
        $context = \context_module::instance($cmid);
        $contextdata = helper::get_context_data($context, $user);

        // Merge with hotquestion vote data and write it.
        $contextdata = (object)array_merge((array)$contextdata, ['items' => $items]);
        writer::with_context($context)->export_data([], $contextdata);

        // Write generic module intro files.
        helper::export_context_files($context, $user);
    }





    /**
     * Export the supplied personal data for a single hotquestion activity, along with any generic data or area files.
     *
     * @param array $items The data for each of the items in the hot question.
     * @param int $cmid
     * @param \stdClass $user The user record.
     */
    public static function export_hotquestion_data_for_user(array $items, int $cmid, \stdClass $user) {

        // Fetch the generic module data for the hotquestion.
        $context = \context_module::instance($cmid);
        $contextdata = helper::get_context_data($context, $user);

        // Merge with hotquestion data and write it.
        $contextdata = (object)array_merge((array)$contextdata, ['items' => $items]);
        writer::with_context($context)->export_data([], $contextdata);

        // Write generic module intro files.
        helper::export_context_files($context, $user);
    }

    /**
     * Delete all data for all users in the specified context.
     * Called when retention period for the context has expired.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context) {
            return;
        }

        // This should not happen, but just in case.
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        if (!$cm = get_coursemodule_from_id('hotquestion', $context->instanceid)) {
            return;
        }

        // Delete the hotquestion entries.
        $itemids = $DB->get_fieldset_select('hotquestion_questions', 'id', 'hotquestion = ?', [$cm->instance]);
        if ($itemids) {
            $DB->delete_records('hotquestion_questions', ['hotquestionid' => $cm->instance]);
            $DB->delete_records('hotquestion_votes', ['hotquestionid' => $cm->instance]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            if (!$cm = get_coursemodule_from_id('hotquestion', $context->instanceid)) {
                continue;
            }
            $itemids = $DB->get_fieldset_select('hotquestion_questions', 'id', 'hotquestion = ?', [$cm->instance]);
            if ($itemids) {
                list($isql, $params) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
                $params['userid'] = $userid;
                $DB->delete_records_select('hotquestion_votes', "id $isql AND voter = :userid", $params);
                $DB->delete_records_select('hotquestion_grades', "id $isql AND userid = :userid", $params);
                $params = ['instanceid' => $cm->instance, 'userid' => $userid];
                $DB->delete_records_select('hotquestion_questions', 'hotquestion = :instanceid AND userid = :userid', $params);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!is_a($context, \context_module::class)) {
            return;
        }
        $modid = self::get_modid();
        if (!$modid) {
            return; // HotQuestion module not installed.
        }
        if (!$cm = get_coursemodule_from_id('hotquestion', $context->instanceid)) {
            return;
        }

        // Prepare SQL to gather all completed IDs.
        $itemids = $DB->get_fieldset_select('hotquestion_questions', 'id', 'hotquestion = ?', [$cm->instance]);
        list($itsql, $itparams) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $userids = $userlist->get_userids();
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete user-created personal hotquestion items.
        $DB->delete_records_select(
            'hotquestion_questions',
            "userid $insql AND hotquestion = :hotquestionid",
            array_merge($inparams, ['hotquestionid' => $cm->instance])
        );

        // Delete comments made by a teacher about a particular item for a student.
        $DB->delete_records_select(
            'hotquestion_votes',
            "voter $insql AND id $itsql",
            array_merge($inparams, $itparams)
        );

        // Delete hot questions grades.
        $DB->delete_records_select('hotquestion_grades',
            "userid $insql AND hotquestion $itsql",
            array_merge($inparams, $itparams)
        );
    }
}
