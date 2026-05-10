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
 * The mod_hotquestion remove question event.
 *
 * @package     mod_hotquestion
 * @copyright   2016 AL Rachels (drachels@drachels.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hotquestion\event;
defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine

/**
 * The mod_hotquestion remove question class.
 *
 * @package    mod_hotquestion
 * @since      Moodle 2.7
 * @copyright  2016 drachels@drachels.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_question extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'hotquestion_questions';
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventremovequestion', 'mod_hotquestion');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $hotquestionid = $this->other['hotquestionid'] ?? 0;
        $roundid = $this->other['roundid'] ?? 0;
        return "The user with id '$this->userid' removed question id '$this->objectid' and its votes " .
            "from hotquestion id '$hotquestionid', round id '$roundid', " .
            "course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns relevant URL.
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/hotquestion/view.php', ['id' => $this->contextinstanceid]);
    }
}
