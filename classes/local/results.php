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
 * Results utilities for Hot Question.
 *
 * 20210225 Started adding new and moving old functions from lib.php and locallib.php to here.
 *
 * @package   mod_hotquestion
 * @copyright AL Rachels (drachels@drachels.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_hotquestion\local;

defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine
define('DIARY_EVENT_TYPE_OPEN', 'open');
define('DIARY_EVENT_TYPE_CLOSE', 'close');

use stdClass;
use csv_export_writer;
use html_writer;
use context_module;
use calendar_event;
use comment;
use mod_hotquestion\event\comments_viewed;
use mod_hotquestion\event\add_question;

/**
 * Utility class for Hot Question results.
 *
 * Created 20210226.
 * @package   mod_hotquestion
 * @copyright AL Rachels (drachels@drachels.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class results {

    /**
     * Update the calendar entries for this hotquestion activity.
     *
     * Moved 20210226. Called from two places in lib.php file.
     * @param stdClass $hotquestion the row from the database table hotquestion.
     * @param int $cmid The coursemodule id
     * @return bool
     */
    public static function hotquestion_update_calendar(stdClass $hotquestion, $cmid) {
        global $DB, $CFG;

        require_once($CFG->dirroot.'/calendar/lib.php');

        // Hotquestion start calendar events.
        $event = new stdClass();
        $event->eventtype = HOTQUESTION_EVENT_TYPE_OPEN;
        // The HOTQUESTION_EVENT_TYPE_OPEN event should only be an action event if no close time is specified.
        if ($CFG->branch > 32) {
            $event->type = empty($hotquestion->timeclose) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        }
        if ($event->id = $DB->get_field('event', 'id',
            [
                'modulename' => 'hotquestion',
                'instance' => $hotquestion->id,
                'eventtype' => $event->eventtype,
                ]
            )) {
            if ((!empty($hotquestion->timeopen)) && ($hotquestion->timeopen > 0)) {
                // Calendar event exists so update it.
                $event->name = get_string('calendarstart', 'hotquestion', $hotquestion->name);
                $event->description = format_module_intro('hotquestion', $hotquestion, $cmid);
                $event->timestart = $hotquestion->timeopen;
                $event->timesort = $hotquestion->timeopen;
                $event->visible = instance_is_visible('hotquestion', $hotquestion);
                $event->timeduration = 0;

                $calendarevent = calendar_event::load($event->id);
                $calendarevent->update($event, false);
            } else {
                // Calendar event is on longer needed.
                $calendarevent = calendar_event::load($event->id);
                $calendarevent->delete();
            }
        } else {
            // Event doesn't exist so create one.
            if ((!empty($hotquestion->timeopen)) && ($hotquestion->timeopen > 0)) {
                $event->name = get_string('calendarstart', 'hotquestion', $hotquestion->name);
                $event->description = format_module_intro('hotquestion', $hotquestion, $cmid);
                $event->courseid = $hotquestion->course;
                $event->groupid = 0;
                $event->userid = 0;
                $event->modulename = 'hotquestion';
                $event->instance = $hotquestion->id;
                $event->timestart = $hotquestion->timeopen;
                $event->timesort = $hotquestion->timeopen;
                $event->visible = instance_is_visible('hotquestion', $hotquestion);
                $event->timeduration = 0;

                calendar_event::create($event, false);
            }
        }

        // Hotquestion end calendar events.
        $event = new stdClass();
        if ($CFG->branch > 32) {
            $event->type = CALENDAR_EVENT_TYPE_ACTION;
        }
        $event->eventtype = HOTQUESTION_EVENT_TYPE_CLOSE;
        if ($event->id = $DB->get_field('event', 'id',
            [
                'modulename' => 'hotquestion',
                'instance' => $hotquestion->id,
                'eventtype' => $event->eventtype,
            ]
        )) {
            if ((!empty($hotquestion->timeclose)) && ($hotquestion->timeclose > 0)) {
                // Calendar event exists so update it.
                $event->name = get_string('calendarend', 'hotquestion', $hotquestion->name);
                $event->description = format_module_intro('hotquestion', $hotquestion, $cmid);
                $event->timestart = $hotquestion->timeclose;
                $event->timesort = $hotquestion->timeclose;
                $event->visible = instance_is_visible('hotquestion', $hotquestion);
                $event->timeduration = 0;

                $calendarevent = calendar_event::load($event->id);
                $calendarevent->update($event, false);
            } else {
                // Calendar event is on longer needed.
                $calendarevent = calendar_event::load($event->id);
                $calendarevent->delete();
            }
        } else {
            // Event doesn't exist so create one.
            if ((!empty($hotquestion->timeclose)) && ($hotquestion->timeclose > 0)) {
                $event->name = get_string('calendarend', 'hotquestion', $hotquestion->name);
                $event->description = format_module_intro('hotquestion', $hotquestion, $cmid);
                $event->courseid = $hotquestion->course;
                $event->groupid = 0;
                $event->userid = 0;
                $event->modulename = 'hotquestion';
                $event->instance = $hotquestion->id;
                $event->timestart = $hotquestion->timeclose;
                $event->timesort = $hotquestion->timeclose;
                $event->visible = instance_is_visible('hotquestion', $hotquestion);
                $event->timeduration = 0;

                calendar_event::create($event, false);
            }
        }

        return true;
    }

    /**
     * Returns the hotquestion instance course_module id
     *
     * Moved 20210226. Called from this results.php file, function hotquestion_count_entries().
     * @param var $hotquestionid
     * @return object
     */
    public static function hotquestion_get_coursemodule($hotquestionid) {
        global $DB;
        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.instance = :hqid
                   AND m.name = 'hotquestion'";
        $params = [];
        $params = ['hqid' => $hotquestionid];
        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Count questions in current rounds.
     *
     * Moved 20210226. Counts all the hotquestion entries (optionally
     * in a given group) and is called from index.php.
     * @param var $hotquestion
     * @param int $groupid
     * @return nothing
     */
    public static function hotquestion_count_entries($hotquestion, $groupid = 0) {
        global $DB, $CFG, $USER;

        $cm = self::hotquestion_get_coursemodule($hotquestion->id);
        $context = context_module::instance($cm->id);
        // Get the groupmode which should be 0, 1, or 2.
        $groupmode = ($hotquestion->groupmode);

        // If user is in a group, how many users and questions in each Hot Question activity current round?
        if ($groupid && ($groupmode > '0')) {

            // Extract each group id from $groupid and process based on whether viewer is a member of the group.
            // Show user and question counts only if a member of the current group.
            foreach ($groupid as $gid) {
                $sql = "SELECT COUNT(DISTINCT hq.userid) AS ucount,
                         COUNT(DISTINCT hq.content) AS qcount
                          FROM {hotquestion_questions} hq
                          JOIN {groups_members} g ON g.userid = hq.userid
                          JOIN {user} u ON u.id = g.userid
                     LEFT JOIN {hotquestion_rounds} hr ON hr.hotquestion=hq.hotquestion
                         WHERE hq.hotquestion = :hqid
                           AND g.groupid = :gidid
                           AND hr.endtime=0
                           AND hq.time>=hr.starttime
                           AND hq.userid>0";
                $params = [];
                $params = ['hqid' => $hotquestion->id] + ['gidid' => $gid->id];
                $hotquestions = $DB->get_records_sql($sql, $params);
            }

        } else if (!$groupid && ($groupmode > '0')) {

            // Check all the entries from the whole course.
            // If not currently a group member, but group mode is set for separate groups or visible groups,
            // see if this user has posted anyway, posted before mode was changed or posted before removal from a group.
            $sql = "SELECT COUNT(DISTINCT hq.userid) AS ucount,
                           COUNT(DISTINCT hq.content) AS qcount
                      FROM {hotquestion_questions} hq
                      JOIN {user} u
                        ON u.id = hq.userid
                 LEFT JOIN {hotquestion_rounds} hr
                        ON hr.hotquestion=hq.hotquestion
                     WHERE hq.hotquestion = :hqid
                       AND hr.endtime = 0
                       AND hq.time >= hr.starttime
                       AND hq.userid = :userid";

            $params = [];
            $params = ['hqid' => $hotquestion->id] + ['userid' => $USER->id];
            $hotquestions = $DB->get_records_sql($sql, $params);

        } else {

            // Check all the users and entries from the whole course.
            $sql = "SELECT COUNT(DISTINCT hq.userid) AS ucount, COUNT(DISTINCT hq.content) AS qcount FROM {hotquestion_questions} hq
                      JOIN {user} u ON u.id = hq.userid
                 LEFT JOIN {hotquestion_rounds} hr
                        ON hr.hotquestion=hq.hotquestion
                     WHERE hq.hotquestion = :hqid
                       AND hr.endtime = 0
                       AND hq.time >= hr.starttime
                       AND hq.userid > 0";
            $params = [];
            $params = ['hqid' => $hotquestion->id];
            $hotquestions = $DB->get_records_sql($sql, $params);
        }

        if (!$hotquestions) {
            return 0;
        }
        $canadd = get_users_by_capability($context, 'mod/hotquestion:ask', 'u.id');
        $entriesmanager = get_users_by_capability($context, 'mod/hotquestion:manageentries', 'u.id');
        // If not enrolled or not an admin, teacher, or manager, then return nothing.
        if ($canadd || $entriesmanager) {
            return ($hotquestions);
        } else {
            return 0;
        }
    }
    /**
     * Get the total number of comments for a specific question.
     *
     * Added 20210307.
     * @param object $question
     * @param object $cm
     * @param object $course
     * @return string
     */
    public static function hotquestion_get_question_comment_count($question, $cm, $course) {
        // 20210313 Not in use yet. Part of future development.
        global $DB;

        if ($count = $DB->count_records('comments',
            [
                'itemid' => $question->id,
                'commentarea' => 'hotquestion_questions',
                'contextid' => $cm->id,
            ]
        )) {
            return $count;
        } else {
            return 0;
        }
    }

    /**
     * Displays all comments for a single question.
     *
     * Added 20210313.
     * @param object $question
     * @param object $cm
     * @param object $context
     * @param object $course
     */
    public static function hotquestion_display_question_comments($question, $cm, $context, $course) {
        global $CFG, $USER, $OUTPUT, $DB;
        $html = '';
        if (($question->approved) || (has_capability('mod/hotquestion:manageentries', $context))) {
            // Get question comments and display the comment box.
            $context = context_module::instance($cm->id);
            $cmt = new stdClass();
            $cmt->component = 'mod_hotquestion';
            $cmt->context = $context;
            $cmt->course = $course;
            $cmt->cm = $cm;
            $cmt->area = 'hotquestion_questions';
            $cmt->itemid = $question->id;
            $cmt->showcount = true;
            $comment = new comment($cmt);
            $html = $comment->output(true);
        } else {
            $html = html_writer::tag('div', get_string("nocommentuntilapproved", "hotquestion"));
        }
        return $html;
    }

    /**
     * Running addtional permission check on plugin, for example, plugins
     * may have switch to turn on/off comments option, this callback will
     * affect UI display, not like pluginname_comment_validate only throw
     * exceptions.
     * Capability check has been done in comment->check_permissions(), we
     * don't need to do it again here.
     *
     * @param stdClass $commentparam {
     *              context  => context the context object
     *              courseid => int course id
     *              cm       => stdClass course module object
     *              commentarea => string comment area
     *              itemid      => int itemid
     * }
     * @return array
     */
    public static function hotquestion_comment_permissions($commentparam) {
         return ['ask' => true, 'view' => true];
    }

    /**
     * Return the editor and attachment options when creating a Hot Question question.
     *
     * @param stdClass $course Course object.
     * @param stdClass $context Context object.
     * @param stdClass $entry Entry object.
     */
    public static function hotquestion_get_editor_and_attachment_options($course, $context, $entry) {
        $maxfiles = 99;
        $maxbytes = $course->maxbytes;

        $editoroptions = [
            'trusttext' => true,
            'maxfiles' => $maxfiles,
            'maxbytes' => $maxbytes,
            'context' => $context,
            'subdirs' => false,
        ];
        $attachmentoptions = [
            'subdirs' => false,
            'maxfiles' => $maxfiles,
            'maxbytes' => $maxbytes,
        ];

        return [
            $editoroptions,
            $attachmentoptions,
        ];
    }

    /**
     * Add a new question to current round.
     *
     * @param object $newentry
     * @param object $hq
     */
    public static function add_new_question($newentry, $hq) {
        global $USER, $CFG, $DB;

        // Check if approval is required for this HotQuestion activity.
        if (!($newentry->approved)) {
            // If approval is NOT required, then auto approve the question so everyone can see it.
            $newentry->approved = 1;
        } else {
            // If approval is required, then mark as not approved so only teachers can see it.
            $newentry->approved = 0;
        }
        $context = context_module::instance($hq->cm->id);
        // If marked anonymous and anonymous is allowed then change from actual userid to guest.
        if (isset($fromform->anonymous) && $fromform->anonymous && $fromform->instance->anonymouspost) {
            $newentry->anonymous = $fromform->anonymous;
            // Assume this user is guest.
            $newentry->userid = $CFG->siteguest;
        }
        if (!empty($newentry->content)) {
            // If there is some actual content, then create a new record.
            $DB->insert_record('hotquestion_questions', $newentry);
            $params = [
                'objectid' => $hq->cm->id,
                'context' => $context,
            ];
            $event = add_question::create($params);
            $event->trigger();

            // Update completion state for current user.
            $hq->update_completion_state();
            // Contrib by ecastro ULPGC update grades for question author.
            $hq->update_users_grades([$USER->id]);
            return true;
        } else {
            return false;
        }
    }
}
