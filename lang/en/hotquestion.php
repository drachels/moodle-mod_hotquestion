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
 * English strings for hotquestion.
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['ago'] = '{$a} ago';
$string['allowanonymouspost'] = 'Allow post question as anonymous';
$string['allowanonymouspost_descr'] = 'If enabled, questions can be posted anonomously, and if approved for viewing heat votes can be made by everyone.';
$string['allowanonymouspost_help'] = 'If enabled, questions can be posted anonomously, and if approved for viewing heat votes can be made by everyone.';
$string['allowauthorinfohide'] = 'Allow authors name to be hidden';
$string['allowauthorinfohide_descr'] = 'If enabled, the name of the questions author can be visible to teachers but hidden from students.';
$string['allowauthorinfohide_help'] = 'If enabled, the name of the questions author can be visible to teachers but hidden from students.';
$string['allowcomments'] = 'Allow comments on entries';
$string['allowcomments_help'] = 'If enabled, all participants with permission to create comments will be able to add comments to hot question entries.';
$string['alwaysshowdescription'] = 'Always show description';
$string['alwaysshowdescription_help'] = 'If disabled, the Hot Question Description will not be visible to students.';
$string['anonymous'] = 'Anonymous';
$string['approvallabel'] = 'Approval required column label';
$string['approvallabel_descr'] = 'Enter a default label for the Approved column.';
$string['approvedno'] = 'Not approved';
$string['approvedyes'] = 'Approved';
$string['authorinfo'] = 'Posted by {$a->user} at {$a->time}';
$string['authorinfohide'] = 'Posted {$a->time}';
$string['calendarend'] = '{$a} closes';
$string['calendarstart'] = '{$a} opens';
$string['cnfallowcomments'] = 'Define if a hot question will accept comments on entries by default';
$string['completiondetail:pass'] = 'Passing grade: {$a}';
$string['completiondetail:post'] = 'Post questions: {$a}';
$string['completiondetail:vote'] = 'Give heat: {$a}';
$string['completionpass'] = 'Student must pass:';
$string['completionpassdesc'] = 'Student must receive passing grade of {$a}';
$string['completionpassgroup'] = 'Require pass:';
$string['completionpost'] = 'Student must add questions:';
$string['completionpostdesc'] = 'Student must create at least {$a} questions(s)';
$string['completionpostgroup'] = 'Require questions';
$string['completionvote'] = 'Student must add heat:';
$string['completionvotedesc'] = 'Student must vote/give heat to {$a} question(s) or post(s)';
$string['completionvotegroup'] = 'Require heat:';
$string['connectionerror'] = 'Connection error';
$string['content'] = 'Content';
$string['crontask'] = 'Background processing for HotQuestion module';
$string['csvexport'] = 'Export to .csv';
$string['deleteentryconfirm'] = 'Confirm you want to delete entry ';
$string['deleteroundconfirm'] = 'Confirm you want to delete round ';
$string['description'] = 'Description';
$string['displayasanonymous'] = 'Display as anonymous';
$string['entries'] = 'Entries';
$string['eventaddquestion'] = 'Added a question';
$string['eventaddround'] = 'Opened a new round';
$string['eventdownloadquestions'] = 'Download questions';
$string['evententriesviewed'] = 'Viewed questions';
$string['evententryupdated'] = 'Updated question';
$string['eventremovequestion'] = 'Remove question';
$string['eventremoveround'] = 'Remove round';
$string['eventremovevote'] = 'Removed vote';
$string['eventupdatevote'] = 'Updated vote';
$string['exportfilename'] = 'questions.csv';
$string['exportfilenamep1'] = 'All_Site_';
$string['exportfilenamep2'] = 'HQ_Questions_Exported_On_';
$string['factorheat'] = 'Heat received factor';
$string['factorheat_help'] = 'A factor to apply to questions count for grading based on question heat.
The hotter the question, the greater weight towards grading.
A question by a student counts as 1 plus addition credit

    credit = (votes) · factor/100.

Normally a heat factor of 5% is adequate, meaning if 20 people votes a question, it\'s value is doubled.
You may need to adjust depending on number of participants and their engagement. ';
$string['factorpriority'] = 'Teacher priority factor';
$string['factorpriority_help'] = 'A factor to count questions without teacher priority set.
Each question counts in the sum of questions asked by a student as their priority value (1, 2 ...).
For those questions without priority (0) the specified value (as a %) is counted. <br />
A 100% factor means that a non-priority question counts just as a question with priority 1. ';
$string['factorvote'] = 'Heat given factor';
$string['factorvote_help'] = 'A factor to apply to the voting count of the students when calculating grades.
In addition to writing new questions, a student can receive participation credit by voting on questions by other students.
This credit is the number of emitted votes times this factor/100.

    credit = votes · factor/100

The factor need to be adjusted depending on the total votes a student could emit
and the weight desired for this type of participation.
For instance, if a student can emit 5 heat votes, a factor of 20% means voting for other 5 questions counts as
one question written by this student. A factor of 100% means voting for other questions counts the same as writing a new question. ';
$string['finalgrade'] = 'Final grade ';
$string['for'] = ' for site: ';
$string['grading'] = 'Raw grade';
$string['heat'] = 'Heat';
$string['heaterror'] = 'Too many votes';
$string['heatgiven'] = 'Heat given';
$string['heatlabel'] = 'Heat column label';
$string['heatlabel_descr'] = 'Enter a default label for the Heat column.';
$string['heatlimit'] = 'Heat default limit';
$string['heatlimit_descr'] = 'Enter a default for the number of times users can apply heat/vote, per round. Zero hides the heat column.';
$string['heatlimit_help'] = 'Enter a default for the number of times you can apply heat/vote, per round. Zero hides the heat column.';
$string['heatreceived'] = 'Heat received';
$string['heatvisibility'] = 'Heat column visibility';
$string['heatvisibility_descr'] = 'If enabled, the Heat column is visible, otherwise it is hidden.';
$string['heatvisibility_help'] = 'If enabled, the Heat column is visible.';
$string['hotquestion'] = 'Hotquestion';
$string['hotquestion:addinstance'] = 'Can add new Hot Question';
$string['hotquestion:ask'] = 'Ask questions';
$string['hotquestion:comment'] = 'Write comments';
$string['hotquestion:manage'] = 'Manage questions';
$string['hotquestion:manageentries'] = 'View list of activities';
$string['hotquestion:rate'] = 'Rate questions';
$string['hotquestion:view'] = 'View questions';
$string['hotquestion:vote'] = 'Vote on questions';
$string['hotquestionclosed'] = 'This activity closed on {$a}.';
$string['hotquestionclosetime'] = 'Close time';
$string['hotquestionintro'] = 'Topic';
$string['hotquestionname'] = 'Activity Name';
$string['hotquestionopen'] = 'This activity will be open on {$a}.';
$string['hotquestionopentime'] = 'Open time';
$string['id'] = 'ID';
$string['improperuseviewgradesclass'] = 'Improper use of the viewgrades class. Cannot load the grade item.';
$string['incorrectmodule'] = 'Course Module ID was incorrect';
$string['inputapprovallabel'] = 'Approved';
$string['inputapprovallabel_descr'] = 'Change the Approved column name to what you want it to be.';
$string['inputapprovallabel_help'] = 'Change the Approved column name to better fit the use of this activity.';
$string['inputheatlabel'] = 'Heat';
$string['inputheatlabel_descr'] = 'Change the Heat column name to what you want it to be.';
$string['inputheatlabel_help'] = 'Change the Heat column name to better fit the use of this activity.';
$string['inputquestion'] = 'Submit your question here:';
$string['inputquestion_descr'] = 'Change submit directions to what you want them to be.';
$string['inputquestion_help'] = 'Change the submit directions to what you want them to be.';
$string['inputquestionlabel'] = 'Questions';
$string['inputquestionlabel_descr'] = 'Change the Questions column name to what you want it to be.';
$string['inputquestionlabel_help'] = 'Change the Questions column name to better fit the use of this activity.';
$string['inputremovelabel'] = 'Remove';
$string['inputremovelabel_descr'] = 'Change the Remove column name to what you want it to be.';
$string['inputremovelabel_help'] = 'Change the remove column name to better fit the use of this activity.';
$string['inputteacherprioritylabel'] = 'Priority';
$string['inputteacherprioritylabel_descr'] = 'Change the Priority column name to what you want it to be.';
$string['inputteacherprioritylabel_help'] = 'Change the Priority column name to better fit the use of this activity.';
$string['invalidquestion'] = 'Empty questions are ignored.';
$string['modulename'] = 'Hot Question';
$string['modulename_help'] = 'A Hot Question activity enables students to post and vote on posts, in response to questions asked by course teachers.';
$string['modulename_link'] = 'mod/hotquestion/view';
$string['modulenameplural'] = 'Hot Questions';
$string['newround'] = 'Open a new round';
$string['newroundconfirm'] = 'Confirm you want to start a new round? (Existing questions and votes will be archived and can not be added to!)';
$string['newroundsuccess'] = 'You have successfully opened a new round.';
$string['nextround'] = 'Next round';
$string['noquestions'] = 'No entries yet.';
$string['notapproved'] = '<b>This entry is not currently approved for viewing.<br></b>';
$string['notavailable'] = '<b>Not currently available!<br></b>';
$string['pluginadministration'] = 'Hot question administration';
$string['pluginname'] = 'Hot Question';
$string['postbutton'] = 'Click to post';
$string['postmaxgrade'] = 'Questions for max grading';
$string['postmaxgrade_help'] = 'The number of questions required to obtain the maximum score.

This is nominally a count of questions, but the value attained by a user may be improved by heat factor (questions with greater heat count more)
and by voting on other questions (a user may improve the grade by participating in voting for questions by other students).';
$string['previousround'] = 'Previous round';
$string['privacy:metadata:hotquestion_grades'] = 'Grading data for hotquestion instances';
$string['privacy:metadata:hotquestion_grades:userid'] = 'The ID of the user that posted this grade.';
$string['privacy:metadata:hotquestion_grades:rawrating'] = 'Raw rating';
$string['privacy:metadata:hotquestion_questions'] = "Information about the user's entries for a given Hot Question activity. ";
$string['privacy:metadata:hotquestion_questions:anonymous'] = 'Is the entry posted as anonymous?';
$string['privacy:metadata:hotquestion_questions:approved'] = 'Is the question approved for general viewing?';
$string['privacy:metadata:hotquestion_questions:content'] = 'The content of the question.';
$string['privacy:metadata:hotquestion_questions:hotquestion'] = 'The ID of the Hot Question activity in which the content was posted.';
$string['privacy:metadata:hotquestion_questions:id'] = 'ID of the entry.';
$string['privacy:metadata:hotquestion_questions:time'] = 'Time the question was posted.';
$string['privacy:metadata:hotquestion_questions:tpriority'] = 'Has the teacher given a priority for this entry?';
$string['privacy:metadata:hotquestion_questions:userid'] = 'The ID of the user that posted this entry.';
$string['privacy:metadata:hotquestion_votes'] = 'Information about votes on questions.';
$string['privacy:metadata:hotquestion_votes:id'] = 'ID of the entry.';
$string['privacy:metadata:hotquestion_votes:question'] = 'The ID of the entry for this vote';
$string['privacy:metadata:hotquestion_votes:voter'] = 'User ID who voted.';
$string['question'] = 'Question';
$string['questionlabel'] = 'Questions column label';
$string['questionlabel_descr'] = 'Enter a default label for the Questions column.';
$string['questionremove'] = 'Remove';
$string['questionremovesuccess'] = 'You have successfully removed that question.';
$string['questions'] = 'Questions';
$string['questionsubmitted'] = 'Your post has been submitted successfully.';
$string['rawgrade'] = 'Raw grade {$a->rawgrade} / {$a->max}';
$string['removedround'] = 'You have successfully removed this round.';
$string['removelabel'] = 'Remove column label';
$string['removelabel_descr'] = 'Enter a default label for the Remove column.';
$string['removeround'] = 'Remove this round';
$string['removevote'] = 'Remove my vote';
$string['requireapproval'] = 'Approval required';
$string['requireapproval_descr'] = 'If enabled, questions require approval by a teacher before they are viewable by everyone.';
$string['requireapproval_help'] = 'If enabled, questions require approval by a teacher before they are viewable by everyone.';
$string['resethotquestion'] = 'Delete all questions and votes';
$string['returnto'] = 'Return to {$a}';
$string['round'] = 'Round {$a}';
$string['showrecentactivity'] = 'Show recent activity';
$string['showrecentactivityconfig'] = 'Everyone can see notifications in recent activity reports.';
$string['teacherpriority'] = 'Priority';
$string['teacherprioritylabel'] = 'Priority column label';
$string['teacherprioritylabel_descr'] = 'Enter a default label for the Priority column.';
$string['teacherpriorityvisibility'] = 'Teacher priority column visibility';
$string['teacherpriorityvisibility_descr'] = 'If enabled, the Teacher priority column is visible, otherwise it is hidden.';
$string['teacherpriorityvisibility_help'] = 'If enabled, the Teacher priority column is visible.';
$string['time'] = 'Time';
$string['totalcomments'] = 'Total comments';
$string['unapprovedquestionhide'] = 'Hide unapproved questions';
$string['unapprovedquestionnotset'] = 'Preference not set';
$string['unapprovedquestionsee'] = 'See unapproved questions';
$string['unapprovedquestionvisibility'] = 'Unapproved question visibility';
$string['userid'] = 'Userid';
$string['valueinterror'] = 'The factor must be a positive integer number';
$string['viewaftertimeclose'] = 'View after close time';
$string['viewaftertimeclose_descr'] = 'If enabled, students will be able to view, but not alter, questions and heat after close time, otherwise they will be hidden.';
$string['viewaftertimeclose_help'] = 'If enabled, students will be able to view, but not alter, questions and heat after close time, otherwise they will be hidden.';
$string['viewaftertimeclosevalidation'] = 'If no closing date is set, then View after close time must be, No.';
$string['viewallentries'] = '{$a->ucount} user(s) posted {$a->qcount} question(s).';
$string['viewallhotquestions'] = 'View all course Hot Questions';
$string['viewentries'] = 'Participation in current round';
$string['viewgrades'] = 'View grades';
$string['vote'] = 'Vote';
$string['xofn'] = ' of ';
