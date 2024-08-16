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
 * Prints a particular instance of hotquestion
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels (drachels@drachels.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use mod_hotquestion\local\results;
use mod_hotquestion\local\hqavailable;
use mod_hotquestion\event\course_module_viewed;

require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");
require_once("mod_form.php");
require_once($CFG->dirroot . '/comment/lib.php');
comment::init();

$id = required_param('id', PARAM_INT);                  // Course_module ID.
$ajax = optional_param('ajax', 0, PARAM_BOOL);          // Asychronous form request.
$action = optional_param('action', '', PARAM_ACTION);  // Action(vote, newround).
$roundid = optional_param('round', -1, PARAM_INT);      // Round id.
$group = optional_param('group', -1, PARAM_INT);  // Choose the current group.

if (! $cm = get_coursemodule_from_id('hotquestion', $id)) {
    throw new moodle_exception(get_string('incorrectmodule', 'hotquestion'));
}

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

// Construct hotquestion instance.
$hq = new mod_hotquestion($id, $roundid);

// Confirm login.
require_login($hq->course, true, $hq->cm);

$context = context_module::instance($hq->cm->id);

$entriesmanager = has_capability('mod/hotquestion:manageentries', $context);
$canrate = has_capability('mod/hotquestion:rate', $context);
$canask = has_capability('mod/hotquestion:ask', $context);
$canview = has_capability('mod/hotquestion:view', $context);

if (!$entriesmanager && !$canrate && !$canask && !$canview) {
    throw new moodle_exception(get_string('accessdenied', 'hotquestion'));
}

if (!$hotquestion = $DB->get_record("hotquestion", ["id" => $cm->instance])) {
    throw new moodle_exception(get_string('incorrectmodule', 'hotquestion'));
}

if (!$cw = $DB->get_record("course_sections", ["id" => $cm->section])) {
    throw new moodle_exception(get_string('incorrectmodule', 'hotquestion'));
}

// 20230519 Get a user preference, set to zero if it does not already exist.
$oldvispreference = get_user_preferences('hotquestion_seeunapproved'.$hotquestion->id, 0);
$vispreference = optional_param('vispreference', $oldvispreference, PARAM_INT);

// 20230517 Added selector for visibility view. 20230531 First time access will default to,
// Preference not set, and show the list of questions anyway.
if (!($oldvispreference)) {
    set_user_preference('hotquestion_seeunapproved'.$hotquestion->id, 1);
} else {
    set_user_preference('hotquestion_seeunapproved'.$hotquestion->id, $vispreference);
}

// Trigger module viewed event.
$params = [
    'objectid' => $hq->cm->id,
    'context' => $context,
];

$event = course_module_viewed::create($params);
$event->trigger();

// Code for Completion, View complete.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// 20240701 Added to update completion state after a user posts a question. Does not work for adds heat.
$ci = new completion_info($course);
if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
    $ci->update_state($cm, COMPLETION_UNKNOWN, null);
}

// Set page.
if (!$ajax) {
    $PAGE->set_url('/mod/hotquestion/view.php', ['id' => $hq->cm->id]);
    $PAGE->set_title($hq->instance->name);
    $PAGE->set_heading($hq->course->shortname);
    $PAGE->set_context($context);
    $PAGE->set_cm($hq->cm);
    $PAGE->add_body_class('hotquestion');
}

require_capability('mod/hotquestion:view', $context);

// Get local renderer.
$output = $PAGE->get_renderer('mod_hotquestion');
$output->init($hq);

// 20230522 Changed to $canask. Process submitted question.
if ($canask) {
    $mform = new hotquestion_form(null, [$hq->instance->anonymouspost, $hq->cm]);
    // 20230520 Needed isset so changing unapproved question views do not cause an error.
    if (($fromform = $mform->get_data()) && (isset($fromform->submitbutton))) {
        // If there is a post, $fromform will contain text, format, id, and submitbutton.
        // 20210314 Prevent CSFR.
        confirm_sesskey();
        $timenow = time();

        // This will be overwritten after we have the entryid.
        // Data for all the fields for a question, plus the submit button status.
        $newentry = new stdClass();
        $newentry->hotquestion = $hq->instance->id;
        $newentry->content = $fromform->text_editor['text'];
        $newentry->format = $fromform->text_editor['format'];
        $newentry->userid = $USER->id;
        $newentry->time = $timenow;
        if (isset($fromform->anonymous)) {
            $newentry->anonymous = $fromform->anonymous;
        } else {
            $newentry->anonymous = 0;
        }
        $newentry->approved = $hq->instance->approval;
        $newentry->tpriority = 0;
        $newentry->submitbutton = $fromform->submitbutton;

        // From this point, need to process the question and save it.
        if (!results::add_new_question($newentry, $hq)) { // Returns 1 if valid question submitted.
            redirect('view.php?id='.$hq->cm->id, get_string('invalidquestion', 'hotquestion'));
        }
        if (!$ajax) {
            redirect('view.php?id='.$hq->cm->id, get_string('questionsubmitted', 'hotquestion'));
        }
        die;
    }
}

// Handle priority, vote, newround, removeround, remove question, download questions, and approve question.
if (!empty($action)) {
    switch ($action) {
        case 'tpriority':
            if (has_capability('mod/hotquestion:rate', $context)) {
                $u = required_param('u',  PARAM_INT);  // Flag to change priority up or down.
                $q = required_param('q',  PARAM_INT);  // Question id to change priority.
                $hq->tpriority_change($u, $q);
                redirect('view.php?id='.$hq->cm->id, null); // Needed to prevent priority change on page reload.
            }
            break;
        case 'vote':
            if (has_capability('mod/hotquestion:vote', $context)) {
                // 20230122 Prevent voting when closed.
                if ((hqavailable::is_hotquestion_active($hq))
                    || (has_capability('mod/hotquestion:rate', $context))
                    || ((!hqavailable::is_hotquestion_active($hq))
                        && !$hotquestion->viewaftertimeclose)
                    ) {
                    $q = required_param('q',  PARAM_INT);  // Question id to vote.
                    $hq->vote_on($q);
                }
            }
            break;
        case 'removevote':
            if (has_capability('mod/hotquestion:vote', $context)) {
                // 20230122 Prevent vote remove when closed.
                if ((hqavailable::is_hotquestion_active($hq))
                    || (has_capability('mod/hotquestion:rate', $context))
                    || ((!hqavailable::is_hotquestion_active($hq))
                        && !$hotquestion->viewaftertimeclose)
                    ) {
                    $q = required_param('q',  PARAM_INT);  // Question id to vote.
                    $hq->remove_vote($q);
                }
            }
            break;
        case 'newround':
            if (has_capability('mod/hotquestion:rate', $context)) {
                $hq->add_new_round();
                // Added to make new empty round start without having to click the Reload icon.
                redirect('view.php?id='.$hq->cm->id, get_string('newroundsuccess', 'hotquestion'));
            }
            break;
        case 'removeround':
            if (has_capability('mod/hotquestion:manageentries', $context)) {
                $hq->remove_round();
                // Added to show round has been removed.
                redirect('view.php?id='.$hq->cm->id, get_string('removedround', 'hotquestion'));
            }
            break;
        case 'remove':
            if (has_capability('mod/hotquestion:manageentries', $context)) {
                $q = required_param('q',  PARAM_INT);  // Question id to remove.
                // Call remove_question function in locallib.
                $hq->remove_question($q);
                // Need redirect that goes to the round where removing question.
                redirect('view.php?id='.$hq->cm->id, get_string('questionremovesuccess', 'hotquestion'));
                // Does work without it as it just defaults to current round.
            }
            break;
        case 'download':
            if (has_capability('mod/hotquestion:manageentries', $context)) {
                $q = $cm->instance; // Course module to download questions from.
                // Call download question function in locallib.
                $hq->download_questions($q);
            }
            break;
        case 'approve':
            if (has_capability('mod/hotquestion:manageentries', $context) || has_capability('mod/hotquestion:rate', $context)) {
                $q = required_param('q',  PARAM_INT);  // Question id to approve.
                // Call approve question function in locallib.
                $hq->approve_question($q);
                redirect('view.php?id='.$hq->cm->id, null); // Needed to prevent toggle on page reload.
            }
            break;
    }
}

// Start print page.
if (!$ajax) {
    // Added code to include the activity name, 10/05/16.
    $hotquestionname = format_string($hotquestion->name, true, ['context' => $context]);
    echo $output->header();
    // 20220716 HQ_882 Skip heading for Moodle 4.0 and higher as it seems to be automatic.
    if ($CFG->branch < 400) {
        echo $OUTPUT->heading($hotquestionname);
    }

    // Allow access at any time to manager, non-editing teacher and editing teacher but prevent access to students.
    // Check availability timeopen and timeclose. Added 10/2/16. Modified 20230120 to add viewaftertimeclose.
    // Modified 20230125 to create hqavailable class. This controls availability restrictions.
    if (!(has_capability('mod/hotquestion:manage', $context)
        || has_capability('mod/hotquestion:rate', $context))
        && !hqavailable::is_hotquestion_active($hq)
        ) {  // Availability restrictions.

        $inaccessible = '';
        if (hqavailable::is_hotquestion_ended($hq) && !$hotquestion->viewaftertimeclose) {
            $inaccessible = $output->hotquestion_inaccessible(get_string('hotquestionclosed',
                'hotquestion', userdate($hotquestion->timeclose)));
        }
        if (hqavailable::is_hotquestion_yet_to_start($hq)) {
            $inaccessible = $output->hotquestion_inaccessible(get_string('hotquestionopen',
                'hotquestion', userdate($hotquestion->timeopen)));
        }
        if ($inaccessible !== '') {
            echo $inaccessible;
            echo $OUTPUT->footer();
            exit();
        }
        // Password code can go here. e.g. // } else if {.
    }

    // 20220301 Added activity completion to the hotquestion description.
    $cminfo = cm_info::create($cm);
    $completiondetails = \core_completion\cm_completion_details::get_instance($cminfo, $USER->id);
    $activitydates = \core\activity_dates::get_dates_for_module($cminfo, $USER->id);

    // 20220706 HQ_882 Skip intro for Moodle 4.0 and higher as it seems to be automatic.
    if ($CFG->branch < 400) {
        echo $output->introduction($cminfo, $completiondetails, $activitydates);
    }

    // 20230123 Added open and close times, if set.
    if (($hotquestion->timeopen) && (($hotquestion->timeopen) > time())) {
        echo '<strong>'.get_string('hotquestionopen', 'hotquestion', date("l, d M Y, G:i A", $hotquestion->timeopen)).
             '</strong><br>';
    } else if ($hotquestion->timeopen) {
        echo '<strong>'.get_string('hotquestionopentime', 'hotquestion').
             ':</strong> '.date("l, d M Y, G:i A", $hotquestion->timeopen).'<br>';
    }
    if (($hotquestion->timeclose) && (($hotquestion->timeclose) < time())) {
        echo '<strong>'.get_string('hotquestionclosed', 'hotquestion', date("l, d M Y, G:i A", $hotquestion->timeclose)).
             '</strong><br>';
    } else if ($hotquestion->timeclose) {
        echo '<strong>'.get_string('hotquestionclosetime', 'hotquestion').
             ':</strong> '.date("l, d M Y, G:i A", $hotquestion->timeclose).'<br>';
    }

    // 20230522 Added a single row table to make both group and viewunapproved preference drop down menus work.
    echo '<table style="width:100%"><tr><td style="width:25%>';
    // Print group information (A drop down box will be displayed if the user
    // is a member of more than one group, or has access to all groups).
    echo groups_print_activity_menu($cm, $CFG->wwwroot.'/mod/hotquestion/view.php?id='.$cm->id);
    echo '</td>';

    // 20240209 I approval is not required, do not show the visibility preference slector.
    if ($hotquestion->approval == 1) {
        // 20230519 Added for preference selector.
        echo '<td style="width:50%"><form method="post">';
        // Add a selector for unapproved question visibility preference.
        $listoptions = [
            get_string('unapprovedquestionnotset', 'hotquestion'),
            get_string('unapprovedquestionsee', 'hotquestion'),
            get_string('unapprovedquestionhide', 'hotquestion'),
        ];
        $htmlout = '';
        $htmlout .= '&nbsp; &nbsp; &nbsp;'.get_string('unapprovedquestionvisibility', 'hotquestion')
                     .' <select onchange="this.form.submit()" id="pref_visibility" class="custom-select" name="vispreference">';
        // Get the ID and name of each preference in the DB.
        foreach ($listoptions as $akey => $aval) {
            // The first if is executed ONLY when the drop down menu is clicked to change the preference.
            if ($akey == $vispreference) {
                // This part of the if is reached when going to setup with an
                // preference already selected and it is the one already in use.
                $htmlout .= '<option value="'.$akey.'" selected="true">'.$aval.'</option>';
            } else {
                // This part of the if is reached the most and its when a preference option
                // is not the one currently selected in the dropdown list.
                $htmlout .= '<option value="'.$akey.'">'.$aval.'</option>';
            }
        }
        set_user_preference('hotquestion_seeunapproved'.$hotquestion->id, $vispreference);
        // 20240209 Added visibility preference check here. Might want to try it at line 306 and save some work.
        echo $htmlout;
        // 20230522 Limit the form to this one row/cell of the table.
        echo '</form></td>';
    } else {
        echo '<td>&nbsp; &nbsp; &nbsp;</td>';
    }
    // 20230519 This creates the URL link button for all HotQuestions in this course.
    $url2 = '<a href="'.$CFG->wwwroot.'/mod/hotquestion/index.php?id='.$course->id
        .'"class="btn btn-link">'
        .get_string('viewallhotquestions', 'hotquestion', $hotquestion->name)
        .'</a>';
    echo '<td style="width:25%; text-align:right">'.$url2.'</td>';
    echo '</tr></table>';
    // Print the textarea box for typing submissions in.
    if ((has_capability('mod/hotquestion:manage', $context)
        || has_capability('mod/hotquestion:rate', $context))
        || (has_capability('mod/hotquestion:ask', $context)
           && hqavailable::is_hotquestion_active($hq))
        ) {
        $mform->display();
    }
}

echo $output->container_start(null, 'questions_list');
// Print toolbar.
echo $output->container_start("toolbar");
// Start contrib by ecastro ULPGC to list the users grade just before the, View grades, button.
echo $output->current_user_rating(has_capability('mod/hotquestion:ask', $context));

// 20220515 Enabled the view grade button for both managers and students. Student ONLY see their grade.
// 20220629 The raw rating and button are visible only if grading is setup.
if (($entriesmanager || $canrate || $canask) && ($hotquestion->grade <> 0)) {
    echo ' ';
    $url = new moodle_url('grades.php', ['id' => $cm->id, 'group' => $group]);
    echo $output->single_button($url, get_string('viewgrades', 'hotquestion'));
}
// End contrib by ecastro ULPGC.
echo $output->toolbar(has_capability('mod/hotquestion:rate', $context));
echo $output->container_end();

// Print questions list from the current round, function questions is in renderer.php file.
echo $output->questions(has_capability('mod/hotquestion:vote', $context));
echo $output->container_end();



// Finish the page.
if (!$ajax) {
    echo $output->footer();
}
