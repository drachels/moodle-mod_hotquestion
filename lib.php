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
 * Library of interface functions and constants for module hotquestion.
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the hotquestion specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine
use mod_hotquestion\local\results;

define('HOTQUESTION_DEFAULT_PAGE_COUNT', 25);
/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $hotquestion An object from the form in mod_form.php
 * @return int The id of the newly inserted hotquestion record
 */
function hotquestion_add_instance($hotquestion) {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/mod/hotquestion/locallib.php');

    $hotquestion->timecreated = time();
    // Fixed instance error 02/15/19.
    $hotquestion->id = $DB->insert_record('hotquestion', $hotquestion);

    // You may have to add extra stuff in here.
    // Added next line for behat test 2/11/19.
    $cmid = $hotquestion->coursemodule;

    results::hotquestion_update_calendar($hotquestion, $cmid);

    // Contrib by ecastro ULPGC.
    hotquestion_grade_item_update($hotquestion);
    $completiontimeexpected = !empty($hotquestion->completionexpected) ? $hotquestion->completionexpected : null;
    \core_completion\api::update_completion_date_event($hotquestion->coursemodule,
        'hotquestion', $hotquestion->id, $completiontimeexpected);
    // Contrib by ecastro ULPGC.
    return $hotquestion->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $hotquestion An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function hotquestion_update_instance($hotquestion) {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/mod/hotquestion/locallib.php');

    if (empty($hotquestion->timeopen)) {
        $hotquestion->timeopen = 0;
    }
    if (empty($hotquestion->timeclose)) {
        $hotquestion->timeclose = 0;
    }

    $cmid = $hotquestion->coursemodule;
    $cmidnumber = $hotquestion->cmidnumber;
    $courseid = $hotquestion->course;

    $hotquestion->id = $hotquestion->instance;

    $context = context_module::instance($cmid);

    $hotquestion->timemodified = time();
    $hotquestion->id = $hotquestion->instance;

    // Contrib by ecastro ULPGC.
    // Check if grades need recalculation due to changed factor(s).
    $recalculate = hotquestion_check_ratings_recalculation($hotquestion);
    // You may have to add extra stuff in here.
    results::hotquestion_update_calendar($hotquestion, $cmid);

    $DB->update_record('hotquestion', $hotquestion);

    // Contrib by ecastro ULPGC.
    $hotquestion->cmid = $cmid;
    hotquestion_grade_item_update($hotquestion);
    // If the grade factors were changed, then recalculate all the grades.
    if ($recalculate) {
        hotquestion_recalculate_rating_grades($cmid);
    }
    hotquestion_update_grades($hotquestion);
    $completiontimeexpected = !empty($hotquestion->completionexpected) ? $hotquestion->completionexpected : null;
    \core_completion\api::update_completion_date_event($hotquestion->coursemodule,
        'hotquestion', $hotquestion->id, $completiontimeexpected);

    return true;
    // Contrib by ecastro ULPGC.
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function hotquestion_delete_instance($id) {
    global $DB;

    if (! $hotquestion = $DB->get_record('hotquestion', ['id' => $id])) {
        return false;
    }

    if (! reset_instance($hotquestion->id)) {
        return false;
    }

    // Contrib by ecastro ULPGC.
    hotquestion_grade_item_delete($hotquestion);

    if (!$cm = get_coursemodule_from_instance('hotquestion', $id)) {
        return false;
    }
    \core_completion\api::update_completion_date_event($cm->id, 'hotquestion', $hotquestion->id, null);
    // Contrib by ecastro ULPGC.
    if (! $DB->delete_records('hotquestion', ['id' => $hotquestion->id])) {
        return false;
    }

    return true;
}

/**
 * Clear all questions and votes.
 *
 * @param int $hotquestionid
 * @return boolean Success/Failure
 */
function reset_instance($hotquestionid) {
    global $DB;
    // 20220810 Added to fix git hub issue #65.
    $hotquestion = $DB->get_record('hotquestion', ['id' => $hotquestionid]);
    $questions = $DB->get_records('hotquestion_questions', ['hotquestion' => $hotquestionid]);
    foreach ($questions as $question) {
        if (! $DB->delete_records('hotquestion_votes', ['question' => $question->id])) {
            return false;
        }
    }

    if (! $DB->delete_records('hotquestion_questions', ['hotquestion' => $hotquestionid])) {
        return false;
    }

    if (! $DB->delete_records('hotquestion_rounds', ['hotquestion' => $hotquestionid])) {
        return false;
    }

    // Contrib by ecastro ULPGC.
    if (! $DB->delete_records('hotquestion_grades', ['hotquestion' => $hotquestionid])) {
        return false;
    }

    hotquestion_grade_item_update($hotquestion, 'reset');
    // Contrib by ecastro ULPGC.
    return true;
}

/**
 * Get all questions into an array for export as csv file.
 *
 * @param int $hotquestionid
 * @return boolean Success/Failure
 */
function get_question_list($hotquestionid) {
    global $CFG, $USER, $DB;
    $params = [];
    $toreturn = [];
    $questionstblname = $CFG->prefix."hotquestion_questions";
    $userstblname = $CFG->prefix."user";
    $sql = 'SELECT COUNT(*) FROM {hotquestion_questions} WHERE userid>0';
    return $DB->get_records_sql($sql, [$USER->id]);
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * @param int $course
 * @param int $user
 * @param int $mod
 * @param int $hotquestion
 * $return->time = the time they did it
 * $return->info = a short text description
 * @return null
 */
function hotquestion_user_outline($course, $user, $mod, $hotquestion) {
    $return = new stdClass;
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 * @param int $course
 * @param int $user
 * @param int $mod
 * @param int $hotquestion
 * @return boolean
 */
function hotquestion_user_complete($course, $user, $mod, $hotquestion) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in hotquestion activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @param stdClass $course
 * @param bool $viewfullnames
 * @param int $timestart
 * @return boolean
 */
function hotquestion_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    $dbparams = [$timestart, $course->id, 'hotquestion'];
    $userfieldsapi = \core_user\fields::for_userpic();
    $namefields = $userfieldsapi->get_sql('u', false, '', 'userid', false)->selects;

    $sql = "SELECT hqq.id,
                   hqq.time,
                   cm.id AS cmid,
                   $namefields
              FROM {hotquestion_questions} hqq
              JOIN {hotquestion} hq
                ON hq.id = hqq.hotquestion
              JOIN {course_modules} cm
                ON cm.instance = hq.id
              JOIN {modules} md
                ON md.id = cm.module
              JOIN {user} u
                ON u.id = hqq.userid
             WHERE hqq.time > ?
               AND hq.course = ?
               AND md.name = ?
          ORDER BY hqq.time ASC
    ";

    $newentries = $DB->get_records_sql($sql, $dbparams);

    $modinfo = get_fast_modinfo($course);
    $show = [];
    $grader = [];
    $showrecententries = get_config('hotquestion', 'showrecentactivity');

    foreach ($newentries as $anentry) {
        if (!array_key_exists($anentry->cmid, $modinfo->get_cms())) {
            continue;
        }
        $cm = $modinfo->get_cm($anentry->cmid);

        if (!$cm->uservisible) {
            continue;
        }
        if ($anentry->userid == $USER->id) {
            $show[] = $anentry;
            continue;
        }
        $context = context_module::instance($anentry->cmid);

        // The act of submitting of entries may be considered private -
        // only graders will see it if specified.
        if (empty($showrecententries)) {
            if (!array_key_exists($cm->id, $grader)) {
                $grader[$cm->id] = has_capability('moodle/grade:viewall', $context);
            }
            if (!$grader[$cm->id]) {
                continue;
            }
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode == SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups',  $context)) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups = groups_get_all_groups($course->id, $anentry->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $anentry;
    }

    if (empty($show)) {
        return false;
    }

    echo $OUTPUT->heading(get_string('modulenameplural', 'hotquestion').':', 6);

    foreach ($show as $submission) {
        $cm = $modinfo->get_cm($submission->cmid);
        $context = context_module::instance($submission->cmid);
        $link = $CFG->wwwroot.'/mod/hotquestion/view.php?id='.$cm->id;
        $name = $cm->name;
        print_recent_activity_note($submission->time,
                                   $submission,
                                   $name,
                                   $link,
                                   false,
                                   $viewfullnames);
    }
    return true;
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 **/
function hotquestion_cron () {
    return true;
}

/**
 * Must return an array of users who are participants for a given instance
 * of hotquestion. Must include every user involved in the instance,
 * independient of his role (student, teacher, admin...). The returned
 * objects must contain at least id property.
 * See other modules as example.
 * @param int $hotquestionid
 * @return boolean|array false if no participants, array of stdClasss otherwise.
 */
function hotquestion_get_participants($hotquestionid) {
    return false;
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified hotquestion
 * and clean up any related data.
 *
 * @param stdClass $data
 * @return array
 */
function hotquestion_reset_userdata($data) {
    global $DB;

    $status = [];
    if (!empty($data->reset_hotquestion)) {
        $instances = $DB->get_records('hotquestion', ['course' => $data->courseid]);
        foreach ($instances as $instance) {
            if (reset_instance($instance->id)) {
                $status[] = [
                    'component' => get_string('modulenameplural', 'hotquestion'),
                    'item' => get_string('resethotquestion', 'hotquestion').': '.$instance->name,
                    'error' => false,
                ];
            }
        }
    }

    return $status;
}

/**
 * Called by course/reset.php
 *
 * @param stdClass $mform
 */
function hotquestion_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'hotquestionheader', get_string('modulenameplural', 'hotquestion'));
    $mform->addElement('checkbox', 'reset_hotquestion', get_string('resethotquestion', 'hotquestion'));
}

/**
 * Indicates API features that the hotquestion supports.
 *
 * @uses FEATURE_MOD_PURPOSE
 * @uses FEATURE_BACKUP_MOODLE2
 * @uses FEATURE_COMMENT
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_RATE
 * @uses FEATURE_SHOW_DESCRIPTION
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function hotquestion_supports($feature) {
    global $CFG;
    if ((int)$CFG->branch > 311) {
        if ($feature === FEATURE_MOD_PURPOSE) {
            return MOD_PURPOSE_COLLABORATION;
        }
    }
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMMENT:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_RATE:
            return false;
        case FEATURE_SHOW_DESCRIPTION:
            return true;

        default:
            return null;
    }
}
    /**
     * Validate comment parameter before perform other comments actions.
     *
     * @param stdClass $commentparam {
     *              context  => context the context object
     *              courseid => int course id
     *              cm       => stdClass course module object
     *              commentarea => string comment area
     *              itemid      => int itemid
     * }
     * @return boolean
     */
function hotquestion_comment_validate($commentparam) {
    global $DB;
    // Validate comment area.
    if ($commentparam->commentarea != 'hotquestion_questions') {
        throw new comment_exception('invalidcommentarea');
    }
    if (!$record = $DB->get_record('hotquestion_questions', ['id' => $commentparam->itemid])) {
        throw new comment_exception('invalidcommentitemid');
    }
    if (!$hotquestion = $DB->get_record('hotquestion', ['id' => $record->hotquestion])) {
        throw new comment_exception('invalidid', 'data');
    }
    if (!$course = $DB->get_record('course', ['id' => $hotquestion->course])) {
        throw new comment_exception('coursemisconf');
    }
    if (!$cm = get_coursemodule_from_instance('hotquestion', $hotquestion->id, $course->id)) {
        throw new comment_exception('invalidcoursemodule');
    }
    $context = context_module::instance($cm->id);

    if ($hotquestion->approval && !$record->approved && !has_capability('mod/hotquestion:manageentries', $context)) {
        throw new comment_exception('notapproved', 'hotquestion');
    }
    // Validate context id.
    if ($context->id != $commentparam->context->id) {
        throw new comment_exception('invalidcontext');
    }
    // Validation for comment deletion.
    if (!empty($commentparam->commentid)) {
        if ($comment = $DB->get_record('comments', ['id' => $commentparam->commentid])) {
            if ($comment->commentarea != 'hotquestion_questions') {
                throw new comment_exception('invalidcommentarea');
            }
            if ($comment->contextid != $commentparam->context->id) {
                throw new comment_exception('invalidcontext');
            }
            if ($comment->itemid != $commentparam->itemid) {
                throw new comment_exception('invalidcommentitemid');
            }
        } else {
            throw new comment_exception('invalidcommentid');
        }
    }
    return true;
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
function hotquestion_comment_permissions($commentparam) {
    return ['post' => true, 'view' => true];
}

/**
 * Returns all other caps used in module.
 * @return array
 */
function hotquestion_get_extra_capabilities() {
    return [
        'moodle/comment:post',
        'moodle/comment:view',
        'moodle/site:viewfullnames',
        'moodle/site:trustcontent',
        'moodle/site:accessallgroups',
    ];
}

// Contrib by ecastro ULPGC.

/**
 * Obtains the automatic completion state for this hotquestion on any conditions
 * in hotquestion settings, such as if all attempts are used or a certain grade is achieved.
 *
 * @param stdClass $course Course
 * @param stdClass $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function hotquestion_get_completion_state($course, $cm, $userid, $type) {
    global $DB, $CFG;

    // Get hotquestion details.
    $hotquestion = $DB->get_record('hotquestion', ['id' => $cm->instance], '*', MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false.
    if (!$hotquestion->completionpost && !$hotquestion->completionvote && !$hotquestion->completionpass) {
        return $type;
    }

    $result = $type; // Default return value.

    // Check if the user has made required number of attempts.
    if ($hotquestion->completionpost) {
        $value = $hotquestion->completionpost <=
            $DB->count_records('hotquestion_questions', [
                'hotquestion' => $hotquestion->id,
                'userid' => $userid,
            ]
        );
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }

    // Check if the user has used up all heat attempts.
    if ($hotquestion->completionvote) {
        $sql = "SELECT COUNT(v.id)
                  FROM {hotquestion_votes} v
                  JOIN {hotquestion_questions} q ON q.id = v.question
                 WHERE q.hotquestion = :hotquestion AND v.voter = :userid ";
        $params = [
            'hotquestion' => $hotquestion->id,
            'userid' => $userid,
        ];
        $value = $hotquestion->completionvote <= $DB->count_records_sql($sql, $params);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }

    // Check for passing grade.
    if ($hotquestion->completionpass) {
        require_once($CFG->libdir . '/gradelib.php');
        $item = grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'hotquestion',
            'iteminstance' => $cm->instance,
            'outcomeid' => null,
        ]);
        if ($item) {
            $grades = grade_grade::fetch_users_grades($item, [$userid], false);
            if (!empty($grades[$userid])) {
                return $grades[$userid]->is_passed($item);
            }
        }
    }

    return $result;
}

/**
 * Add a get_coursemodule_info function in case any hotquestion type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module stdClass, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule stdClass (record).
 * @return cached_cm_info An stdClass on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function hotquestion_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionpost, completionvote, completionpass, timeopen, timeclose';
    if (!$hotquestion = $DB->get_record('hotquestion', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $hotquestion->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('hotquestion', $hotquestion, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionpost'] = $hotquestion->completionpost;
        $result->customdata['customcompletionrules']['completionvote'] = $hotquestion->completionvote;
        $result->customdata['customcompletionrules']['completionpass'] = $hotquestion->completionpass;
    }

    // Populate some other values that can be used in calendar or on dashboard.
    if ($hotquestion->timeopen) {
        $result->customdata['timeopen'] = $hotquestion->timeopen;
    }
    if ($hotquestion->timeclose) {
        $result->customdata['timeclose'] = $hotquestion->timeclose;
    }
    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm stdClass with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_hotquestion_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionpost':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionpostdesc', 'hotquestion', $val);
                }
                break;
            case 'completionvote':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionvotedesc', 'hotquestion', $val);
                }
                break;
            case 'completionpass':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionpassdesc', 'hotquestion', $val);
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * Update activity grades.
 *
 * @category grade
 * @param stdClass $hotquestion
 * @param int $userid Specific user only, 0 means all.
 * @param bool $nullifnone If true and the user has no grade then a grade item with rawgrade == null will be inserted.
 */
function hotquestion_update_grades($hotquestion, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if (!$hotquestion->grade) {
        hotquestion_grade_item_update($hotquestion);

    } else if ($grades = hotquestion_get_user_grades($hotquestion, $userid)) {
        hotquestion_grade_item_update($hotquestion, $grades);

    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        hotquestion_grade_item_update($hotquestion, $grade);

    } else {
        hotquestion_grade_item_update($hotquestion);
    }
}

/**
 * Create/update grade item for given hotquestion.
 *
 * @category grade
 * @param stdClass $hotquestion stdClass with extra cmidnumber
 * @param mixed $grades Optional array/stdClass of grade(s); 'reset' means reset grades in gradebook
 * @return int, 0 if ok, error code otherwise
 */
function hotquestion_grade_item_update($hotquestion, $grades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    // Hot Question does not use the Moodle rating, only the whole Hot Question grade.
    $item = [];
    $item['itemname'] = clean_param($hotquestion->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if (isset($hotquestion->grade) && $hotquestion->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = $hotquestion->grade;
        $item['grademin'] = 0;
    } else if (isset($hotquestion->grade) && $hotquestion->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid'] = -$hotquestion->grade;

    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }
    grade_update('mod/hotquestion',
                 $hotquestion->course,
                 'mod',
                 'hotquestion',
                 $hotquestion->id,
                 0,
                 $grades,
                 $item);
}

/**
 * Return grade for given user or all users.
 *
 * @param stdclass $hotquestion The id of hotquestion.
 * @param int $userid Optional user id, 0 means all users.
 * @return array array of grades, false if none. These are raw grades. They should
 * be processed with hotquestion_format_grade for display.
 */
function hotquestion_get_user_grades(stdclass $hotquestion, int $userid = 0) {
    global $CFG, $DB;

    // 20220429 Added to fix error when $hotquestion->cmid is null.
    if (!(isset($hotquestion->cmid))) {
        $cm = get_coursemodule_from_instance('hotquestion', $hotquestion->id, $hotquestion->course, false, MUST_EXIST);
        $hotquestion->cmid = $cm->id;
    }

    $context = context_module::instance($hotquestion->cmid);

    list($esql, $params) = get_enrolled_sql($context, 'mod/hotquestion:ask', 0, true);
    $sql = "SELECT u.id, u.username, u.idnumber, g.userid, g.rawrating, g.timemodified
              FROM {user} u
              JOIN ($esql) je ON je.id = u.id
         LEFT JOIN {hotquestion_grades} g ON g.userid = u.id AND g.hotquestion = :instance
             WHERE 1 = 1 ";
    $params['instance'] = $hotquestion->id;
    $userwhere = '';
    if ($userid) {
        $userwhere = ' AND u.id = :userid ';
        $params['userid'] = $userid;
    }
    $users = $DB->get_records_sql($sql.$userwhere, $params);

    $grades = [];
    $now = time();
    $grade = new stdClass();
    $grade->dategraded = $now;
    $grade->datesubmitted = $now;
    $grade->rawgrade = null;
    foreach ($users as $userid => $rating) {
        if (isset($rating->rawrating)) {
            $factor = $rating->rawrating / max($hotquestion->postmaxgrade, 1);
            if ($factor > 1.0) {
                $factor = 1.0;
            }

            // There was an error here! $hotquestion->grade and later $hotquestion->grade_hotquestion,
            // when a negative number, it is actually the id of the scale that is being used.
            if ($hotquestion->grade > 0) {
                $grade->rawgrade = $hotquestion->grade * $factor;
            } else if ($hotquestion->grade < 0) {
                $grade->rawgrade = min($rating->rawrating, $hotquestion->postmaxgrade);
            }
        }
        $grade->id = $userid;
        $grade->userid = $userid;
        $grade->dategraded = $rating->timemodified;
        $grades[$userid] = clone $grade;
    }
    return $grades;
}
/**
 * Delete grade item for given hotquestion
 *
 * @category grade
 * @param stdClass $hotquestion stdClass
 */
function hotquestion_grade_item_delete($hotquestion) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/hotquestion',
        $hotquestion->course,
        'mod',
        'hotquestion',
        $hotquestion->id,
        1,
        null,
        ['deleted' => 1]
    );
}


/**
 * Rescale all grades for this activity and push the new grades to the gradebook.
 *
 * @param stdClass $course Course db record
 * @param stdClass $cm Course module db record
 * @param float $oldmin
 * @param float $oldmax
 * @param float $newmin
 * @param float $newmax
 */
function hotquestion_rescale_activity_grades(stdClass $course, stdClass $cm, float $oldmin,
        float $oldmax, float $newmin, float $newmax): bool {
    global $DB;

    $dbparams = ['id' => $cm->instance];
    $hotquestion = $DB->get_record('hotquestion', $dbparams);
    $hotquestion->cmid = $cm->id;
    $hotquestion->cmidnumber = $cm->idnumber;
    hotquestion_update_grades($hotquestion);

    return true;
}

/**
 * Checks if grade parameter settings have changed so grades need recalculation.
 * Must be called by update_instance BEFORE storing new data.
 *
 * @param stdClass $hotquestion stdClass.
 */
function hotquestion_check_ratings_recalculation(stdClass $hotquestion): bool {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/mod/hotquestion/locallib.php');

    $oldrecord = $DB->get_record('hotquestion', ['id' => $hotquestion->id]);
    $fields = ['factorheat', 'factorpriority', 'factorvote'];

    foreach ($fields as $field) {
        if (!isset($oldrecord->$field) || !isset($hotquestion->$field) ||
            $oldrecord->$field != $hotquestion->$field) {
            return true;
        }
    }
    return false;
}

/**
 * Performs a complete recalculation of ratings & grades for all users with a grade.
 * Must be called by update_instance BEFORE storing new data.
 *
 * @param int $cmid the course modlule ID
 */
function hotquestion_recalculate_rating_grades(int $cmid) {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/mod/hotquestion/locallib.php');

    $hq = new mod_hotquestion($cmid);

    $params = ['hotquestion' => $hq->instance->id];
    $users = $DB->get_records_menu('hotquestion_questions', $params, 'userid', 'id, userid');
    $graded = $DB->get_records_menu('hotquestion_grades', $params, 'userid', 'id, userid');
    $users = array_unique($users + $graded);

    unset($graded);
    $sql = "SELECT v.id, v.voter
              FROM {hotquestion_votes} v
              JOIN {hotquestion_questions} q ON q.id = v.question AND q.hotquestion = :hotquestion
             WHERE NOT EXISTS (SELECT 1
              FROM {hotquestion_questions} qq
             WHERE qq.hotquestion = q.hotquestion AND qq.userid = v.voter)";
    $voters = $DB->get_records_sql_menu($sql, $params);
    $users = array_unique($users + $voters);

    unset($voters);

    $hq->update_users_grades($users);
}
