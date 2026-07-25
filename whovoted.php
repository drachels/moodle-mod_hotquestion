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
 * Display voters per question for the selected round.
 *
 * @package   mod_hotquestion
 * @copyright 2026 AL Rachels
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
require_once('locallib.php');

$id = required_param('id', PARAM_INT);
$roundid = optional_param('round', -1, PARAM_INT);

if (!$cm = get_coursemodule_from_id('hotquestion', $id)) {
    throw new moodle_exception(get_string('incorrectmodule', 'hotquestion'));
}

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$hq = new mod_hotquestion($id, $roundid);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/hotquestion:rate', $context);

$baseurl = new moodle_url('/mod/hotquestion/whovoted.php', ['id' => $cm->id]);
if ($roundid > 0) {
    $baseurl->param('round', $roundid);
}

$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($hq->instance->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_cm($cm);

$prevround = $hq->get_prevround();
$nextround = $hq->get_nextround();
$roundlabel = $hq->get_currentroundx() . get_string('xofn', 'hotquestion') . $hq->get_roundcount();

$questions = $hq->get_questions();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('whovotedheading', 'hotquestion'));
echo $OUTPUT->notification(get_string('round', 'hotquestion', $roundlabel), 'info');

if (!empty($prevround) || !empty($nextround)) {
    $nav = '';
    if (!empty($prevround)) {
        $prevurl = new moodle_url('/mod/hotquestion/whovoted.php', [
            'id' => $cm->id,
            'round' => (int)$prevround->id,
        ]);
        $nav .= html_writer::link(
            $prevurl,
            get_string('previousround', 'hotquestion'),
            ['class' => 'btn btn-outline-secondary me-2']
        );
    }
    if (!empty($nextround)) {
        $nexturl = new moodle_url('/mod/hotquestion/whovoted.php', [
            'id' => $cm->id,
            'round' => (int)$nextround->id,
        ]);
        $nav .= html_writer::link(
            $nexturl,
            get_string('nextround', 'hotquestion'),
            ['class' => 'btn btn-outline-secondary']
        );
    }
    if ($nav !== '') {
        echo html_writer::div($nav, 'mb-2');
    }
}

$returnurl = new moodle_url('/mod/hotquestion/view.php', ['id' => $cm->id]);
if (!empty($hq->get_currentround()->id)) {
    $returnurl->param('round', (int)$hq->get_currentround()->id);
}
echo $OUTPUT->single_button($returnurl, get_string('returnto', 'hotquestion', format_string($hq->instance->name)));

if (empty($questions)) {
    echo $OUTPUT->notification(get_string('noquestions', 'hotquestion'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('question', 'hotquestion') . ' ID',
    format_text($hq->instance->questionlabel, FORMAT_MOODLE),
    get_string('heat', 'hotquestion'),
    get_string('whovotedvoters', 'hotquestion'),
];
$table->align = ['left', 'left', 'left', 'left'];
$table->attributes['class'] = 'generaltable';

foreach ($questions as $question) {
    $namefields = \core_user\fields::get_name_fields(true, 'u');
    $selectnamefields = implode(', ', $namefields);
    $voters = $DB->get_records_sql(
        "SELECT u.id, $selectnamefields
           FROM {hotquestion_votes} hv
           JOIN {user} u ON u.id = hv.voter
          WHERE hv.question = :questionid
         ORDER BY u.lastname, u.firstname",
        ['questionid' => $question->id]
    );

    $voternames = [];
    if ($voters) {
        foreach ($voters as $voter) {
            $voternames[] = fullname($voter);
        }
    }

    $cleanedcontent = format_string($question->content, true);
    $table->data[] = [
        $question->id,
        $cleanedcontent,
        (int)$question->votecount,
        !empty($voternames) ? implode(', ', $voternames) : '-',
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
