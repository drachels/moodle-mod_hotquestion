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
 * This file is used to export submissions in csv format. Called from view.php. 
 *
 * @package    mod
 * @subpackage hotquestion
 * @copyright  2016 onwards AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function array_to_csv_download($array, $filename = "export.csv", $delimiter=";") {
    header('Content-Type: application/csv');
    header('Content-Disposition: attachement; filename="'.$filename.'";');
	header("Pragma: no-cache");
	header("Expires: 0");
    $f = fopen('php://output', 'w');
    $headings = array(get_string('id', 'hotquestion'),
                      get_string('hotquestion', 'hotquestion'),
                      get_string('content', 'hotquestion'),
                      get_string('userid', 'hotquestion'),
                      get_string('time', 'hotquestion'),
                      get_string('anonymous', 'hotquestion'));
    fputcsv($f, $headings, $delimiter);
    //foreach ($array as $hq) {
		$fields = array($hq_id->id, $hq_id->hotquestion, $hq_id->content, $hq_id->userid, date('d. M Y G:i', $hq_id->time), $hq_id->anonymous);
		fputcsv($f, $fields, $delimiter);		
    //}
    fclose($f);
} 

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

$hq_id = optional_param('hotquestion_questions', 0, PARAM_INT);
//$m_is_exam = optional_param('hotquestion', 0, PARAM_INT);
//if($m_is_exam)
//	$grds = get_typergradesfull($m_id, 2, 0);
//else
$db_questions = $DB->get_record('hotquestion_questions', array('id' => $hq_id));
	//$grds = get_questions($hq_id, 0, 0, 2, 0);

array_to_csv_download($db_questions, get_string('exportfilename', 'hotquestion'));
