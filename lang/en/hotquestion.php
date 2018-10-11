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
$string['allowanonymouspost_help'] = 'If enabled, questions can be posted anonomously, and if approved for viewing heat votes can be made by everyone.';
$string['allowanonymouspost_descr'] = 'If enabled, questions can be posted anonomously, and if approved for viewing heat votes can be made by everyone.';
$string['alwaysshowdescription'] = 'Always show description';
$string['alwaysshowdescription_help'] = 'If disabled, the Hot Question Description will not be visible to students.';
$string['anonymous'] = 'Anonymous';
$string['approvedyes'] = 'Approved';
$string['approvedno'] = 'Not approved';
$string['authorinfo'] = 'Posted by {$a->user} at {$a->time}';
$string['connectionerror'] = 'Connection error';
$string['content'] = 'Content';
$string['csvexport'] = 'Export to .csv';
$string['description'] = 'Description';
$string['displayasanonymous'] = 'Display as anonymous';
$string['entries'] = 'Entries';
$string['eventaddquestion'] = 'Added a question';
$string['eventaddround'] = 'Opened a new round';
$string['eventdownloadquestions'] = 'Download questions';
$string['eventremovequestion'] = 'Remove question';
$string['eventremoveround'] = 'Remove round';
$string['eventupdatevote'] = 'Updated vote';
$string['exportfilename'] = 'questions.csv';
$string['exportfilenamep1'] = 'All_Site';
$string['exportfilenamep2'] = '_HQ_Questions_Exported_On_';
$string['heat'] = 'Heat';
$string['hotquestion'] = 'Hotquestion';
$string['hotquestionclosed'] = 'This activity closed on {$a}.';
$string['hotquestionclosetime'] = 'Close time';
$string['hotquestionintro'] = 'Topic';
$string['hotquestionname'] = 'Activity Name';
$string['hotquestionopentime'] = 'Open time';
$string['hotquestionopen'] = 'This activity will be open on {$a}.';
$string['hotquestion:addinstance'] = 'Can add new Hot Question';
$string['hotquestion:ask'] = 'Ask questions';
$string['hotquestion:manage'] = 'Manage questions';
$string['hotquestion:manageentries'] = 'View list of activities';
$string['hotquestion:view'] = 'View questions';
$string['hotquestion:vote'] = 'Vote on questions';
$string['id'] = 'ID';
$string['inputquestion'] = 'Submit your question here:';
$string['inputquestion_descr'] = 'Change submit directions to what you want them to be.';
$string['inputquestion_help'] = 'Change the submit directions to what you want them to be.';
$string['invalidquestion'] = 'Empty questions are ignored.';
$string['modulename'] = 'Hot Question';
$string['modulename_help'] = 'A Hot Question activity enables students to post and vote on posts, in response to questions asked by course teachers.';
$string['modulenameplural'] = 'Hot Questions';
$string['newround'] = 'Open a new round';
$string['newroundsuccess'] = 'You have successfully opened a new round.';
$string['newroundconfirm'] = 'Are you sure? (Existing questions and votes will be archived)';
$string['nextround'] = 'Next round';
$string['noquestions'] = 'No entries yet.';
$string['notavailable'] = '<b>Not currently available!<br></b>';
$string['notapproved'] = '<b>This entry is not currently approved for viewing.<br></b>';
$string['pluginadministration'] = 'Hot question administration';
$string['pluginname'] = 'Hot Question';
$string['post'] = 'Post';
$string['previousround'] = 'Previous round';
$string['privacy:metadata:hotquestion_questions'] = "Information about the user's entries for a given Hot Question activity. ";
$string['privacy:metadata:hotquestion_questions:userid'] = 'The ID of the user that posted this entry.';
$string['privacy:metadata:hotquestion_questions:hotquestion'] = 'The ID of the Hot Question activity in which the content was posted.';
$string['privacy:metadata:hotquestion_questions:content'] = 'The content of the question.';
$string['privacy:metadata:hotquestion_questions:time'] = 'Time the question was posted.';
$string['privacy:metadata:hotquestion_questions:id'] = 'ID of the entry.';
$string['privacy:metadata:hotquestion_questions:anonymous'] = 'Is the entry posted as anonymous?';
$string['privacy:metadata:hotquestion_questions:approved'] = 'Is the question approved for general viewing?';
$string['privacy:metadata:hotquestion_questions:tpriority'] = 'Has the teacher given a priority for this entry?';
$string['privacy:metadata:hotquestion_votes'] = 'Information about votes on questions.';
$string['privacy:metadata:hotquestion_votes:id'] = 'ID of the entry.';
$string['privacy:metadata:hotquestion_votes:question'] = 'The ID of the entry for this vote';
$string['privacy:metadata:hotquestion_votes:voter'] = 'User ID who voted.';

$string['question'] = 'Questions';
$string['questionsubmitted'] = 'Your post has been submitted successfully.';
$string['questionremove'] = 'Remove';
$string['questionremovesuccess'] = 'You have successfully removed that question.';
$string['removeround'] = 'Remove this round';
$string['removedround'] = 'You have successfully removed this round.';
$string['requireapproval'] = 'Approval required';
$string['requireapproval_help'] = 'If enabled, questions require approval by a teacher before they are viewable by everyone.';
$string['requireapproval_descr'] = 'If enabled, questions require approval by a teacher before they are viewable by everyone.';
$string['resethotquestion'] = 'Delete all questions and votes';
$string['returnto'] = 'Return to {$a}';
$string['round'] = 'Round {$a}';
$string['showrecentactivity'] = 'Show recent activity';
$string['showrecentactivityconfig'] = 'Everyone can see notifications in recent activity reports.';
$string['teacherpriority'] = 'Priority';
$string['time'] = 'Time';
$string['userid'] = 'Userid';
$string['vote'] = 'Vote';
$string['viewallentries'] = '{$a->ucount} user(s) posted {$a->qcount} question(s).';
$string['viewentries'] = 'Participation in current round';
