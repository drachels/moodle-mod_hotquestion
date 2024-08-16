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

namespace mod_hotquestion\task;
defined('MOODLE_INTERNAL') || die(); // phpcs:ignore
use context_module;
use stdClass;

/**
 * A schedule task for hotquestion cron.
 *
 * @package   mod_hotquestion
 * @copyright 2024 AL Rachels <drachels@drachels.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_task extends \core\task\scheduled_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'mod_hotquestion');
    }

    /**
     * Run hotquestion cron.
     */
    public function execute() {
        global $CFG;

        $this->log_start("Processing HotQuestion information.");

        require_once($CFG->dirroot . '/mod/hotquestion/locallib.php');
        // phpcs:ignore
        // ...\hotquestion::cron();.
        // ...\hotquestion::update_completion_state();.

        // 20240704 Added to update completion state after a user adds heat or teacher adds to a students priority/grade.
        // ...$ci = new completion_info($course);.
        // ...if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {.
        // ...    $ci->update_state($cm, COMPLETION_UNKNOWN, null);.
        // ///}.
        return true;
    }
}
