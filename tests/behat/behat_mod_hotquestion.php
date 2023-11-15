<?php
// This file is part of the mod_coursecertificate plugin for Moodle - http://moodle.org/
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
 * mod_hotquestion steps definitions.
 *
 * @package     mod_hotquestion
 * @category    test
 * @copyright   2023 Giorgio Riva
 * @copyright   AL Rachels (drachels@drachels.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for mod_hotquestion.
 *
 * @package     mod_hotquestion
 * @category    test
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_hotquestion extends behat_base {

    /**
     * Step to open current course or activity settings page (language string changed between 3.11 and 4.0)
     *
     * @When /^I open course or activity (hotquestion) settings page$/
     * @return void
     */
    public function i_open_course_or_activity_settings_page(): void {
        global $CFG;
        if ($CFG->version < 2022012100) {
            $this->execute("behat_navigation::i_navigate_to_in_current_page_administration", ['Edit settings']);
        } else {
            $this->execute("behat_navigation::i_navigate_to_in_current_page_administration", ['Settings']);
        }
    }
}
