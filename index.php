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
 * This page lists all the instances of hot question in a particular course.
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//require_once(__DIR__ . "/../../config.php");
require_once("../../config.php");
require_once("lib.php");

$id = required_param('id', PARAM_INT);   // course

if (! $course = $DB->get_record("course", array("id" => $id))) {
    print_error("Course ID is incorrect");
}

require_course_login($course);

// Header.
$strhotquestions = get_string("modulenameplural", "hotquestion");
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/mod/hotquestion/index.php', array('id' => $id));
$PAGE->navbar->add($strhotquestions);
$PAGE->set_title($strhotquestions);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($strhotquestions);

if (! $hotquestions = get_all_instances_in_course("hotquestion", $course)) {
    notice(get_string('thereareno', 'moodle', get_string("modulenameplural", "hotquestion")), "../../course/view.php?id=$course->id");
    die;
}

// Sections.
$usesections = course_format_uses_sections($course->format);
if ($usesections) {
	$modinfo = get_fast_modinfo($course);
	$sections = $modinfo->get_section_info_all();
}

$timenow = time();

// Table data.
$table = new html_table();

$table->head = array();
$table->align = array();

if ($usesections) {
    $table->head[] = get_string('sectionname', 'format_'.$course->format);
    $table->align[] = 'center';
}

$table->head[] = get_string('name');
$table->align[] = 'left';
$table->head[] = get_string('description');
$table->align[] = 'left';

$currentsection = '';
$i = 0;
foreach ($hotquestions as $hotquestion) {

    $context = context_module::instance($hotquestion->coursemodule);
    $entriesmanager = has_capability('mod/hotquestion:manageentries', $context);

    // Section.
    $printsection = '';
    if ($hotquestion->section !== $currentsection) {
        if ($hotquestion->section) {
            $printsection = get_section_name($course, $sections[$hotquestion->section]);
        }
        if ($currentsection !== '') {
            $table->data[$i] = 'hr';
            $i++;
        }
        $currentsection = $hotquestion->section;
    }
    if ($usesections) {
        $table->data[$i][] = $printsection;
    }

    // Link to Hot Question activities.
    if (!$hotquestion->visible) {
        //Show dimmed if the mod is hidden
        $table->data[$i][] = "<a class=\"dimmed\" href=\"view.php?id=$hotquestion->coursemodule\">".format_string($hotquestion->name,true)."</a>";
    } else {
        //Show normal if the mod is visible
        $table->data[$i][] = "<a href=\"view.php?id=$hotquestion->coursemodule\">".format_string($hotquestion->name,true)."</a>";
    }

    // Description of the Hot Question activity.
    $table->data[$i][] = format_text($hotquestion->intro,  $hotquestion->introformat);

    // Questions info
//    if ($entriesmanager) {
//
        // Display the report.php col only if is a entries manager in some CONTEXT_MODULE
//        if (empty($managersomewhere)) {
//            $table->head[] = get_string('viewentries', 'hotquestion');
//            $table->align[] = 'left';
//            $managersomewhere = true;
//
//            // Fill the previous col cells
//            $manageentriescell = count($table->head) - 1;
//            for ($j = 0; $j < $i; $j++) {
//                if (is_array($table->data[$j])) {
//                    $table->data[$j][$manageentriescell] = '';
//                }
//            }
//        }
//		
//        //$entrycount = hotquestion_count_entries($hotquestion, get_current_group($course->id));
//		//$entrycount = hotquestion_count_entries($hotquestion, groups_get_all_groups($course->id, $USER->id));
//        $table->data[$i][] = "<a href=\"report.php?id=$hotquestion->coursemodule\">".get_string("viewallentries","hotquestion", $entrycount)."</a>";
//    } else if (!empty($managersomewhere)) {
//
//        $table->data[$i][] = "";
//    }
	
    $i++;
}

echo "<br />";

echo html_writer::table($table);

// Trigger course module instance list event.
$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_hotquestion\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

echo $OUTPUT->footer();