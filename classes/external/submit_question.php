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

namespace mod_hotquestion\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/hotquestion/locallib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_hotquestion\local\results;

/**
 * External function to submit a new question from mobile.
 *
 * @package   mod_hotquestion
 * @copyright 2026 AL Rachels <drachels@drachels.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_question extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid'      => new external_value(PARAM_INT, 'Course module id'),
            'content'   => new external_value(PARAM_RAW, 'Question content (may contain @@PLUGINFILE@@ tokens)'),
            'format'    => new external_value(PARAM_INT, 'Text format', VALUE_DEFAULT, FORMAT_HTML),
            'itemid'    => new external_value(PARAM_INT, 'Draft file area item id (0 if no media)', VALUE_DEFAULT, 0),
            'anonymous' => new external_value(PARAM_BOOL, 'Post anonymously', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status'     => new external_value(PARAM_ALPHA, 'ok or error'),
            'questionid' => new external_value(PARAM_INT, 'New question id (0 on failure)'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int    $cmid
     * @param string $content
     * @param int    $format
     * @param int    $itemid
     * @param bool   $anonymous
     * @return array
     */
    public static function execute($cmid, $content, $format = FORMAT_HTML, $itemid = 0, $anonymous = false) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'      => $cmid,
            'content'   => $content,
            'format'    => $format,
            'itemid'    => $itemid,
            'anonymous' => $anonymous,
        ]);

        $cm = get_coursemodule_from_id('hotquestion', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $hotquestion = $DB->get_record('hotquestion', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_login($course, false, $cm);
        require_capability('mod/hotquestion:ask', $context);

        // Build the question record the same way view.php does.
        $hq = new \mod_hotquestion($params['cmid']);

        $newentry = new \stdClass();
        $newentry->hotquestion = $hotquestion->id;
        $newentry->content    = trim((string)$params['content']);
        $newentry->format     = (int)$params['format'];
        $newentry->userid     = $USER->id;
        $newentry->time       = time();
        $newentry->anonymous  = (int)$params['anonymous'];
        $newentry->approved   = (int)$hotquestion->approval;
        $newentry->tpriority  = 0;

        $questionid = results::add_new_question($newentry, $hq);

        if (!$questionid) {
            return ['status' => 'error', 'questionid' => 0];
        }

        // Move any media files from the draft file area into the permanent question file area.
        $draftitemid = (int)$params['itemid'];
        if ($draftitemid > 0) {
            $fileoptions = ['subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => -1];
            $processedcontent = file_save_draft_area_files(
                $draftitemid,
                $context->id,
                'mod_hotquestion',
                'question',
                $questionid,
                $fileoptions,
                $newentry->content
            );
            // Update the stored content to use @@PLUGINFILE@@ tokens.
            $DB->set_field('hotquestion_questions', 'content', $processedcontent, ['id' => $questionid]);
        }

        return ['status' => 'ok', 'questionid' => (int)$questionid];
    }
}
