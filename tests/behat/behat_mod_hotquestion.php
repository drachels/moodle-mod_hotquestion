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

use Behat\Behat\Context\Step\Given as Given, Behat\Behat\Context\Step\When as When, Behat\Gherkin\Node\TableNode as TableNode;

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

    /**
     * Convert page names to URLs for steps like 'When I am on the "[page name]" page'.
     *
     * Recognised page names are:
     * | None so far!      |                                                              |
     *
     * @param string $page name of the page, with the component name removed e.g. 'Admin notification'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            default:
                throw new Exception('Unrecognised hotquestion page type "' . $page . '."');
        }
    }

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | pagetype          | name meaning                              | description                                         |
     * | view              | Hotquestion name                          | The hotquestion main page (view.php)                |
     * | edit              | Hotquestion name                          | The hotquestion edit page (modedit.php)                |
     *
     * @param string $type identifies which type of page this is, e.g. 'preview'.
     * @param string $identifier identifies the particular page, e.g. 'Test hotquestion > preview > Attempt 1'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'view':
                return new moodle_url('/mod/hotquestion/view.php',
                    ['id' => $this->get_cm_by_hotquestion_name($identifier)->id]);
            case 'edit':
                return new moodle_url('/course/modedit.php',
                    ['update' => $this->get_cm_by_hotquestion_name($identifier)->id]);

            default:
                throw new Exception('Unrecognised hotquestion page type "' . $type . '."');
        }
    }

    /**
     * Get a hotquestion by name.
     *
     * @param string $name hotquestion name.
     * @return stdClass the corresponding DB row.
     */
    protected function get_hotquestion_by_name(string $name): stdClass {
        global $DB;
        return $DB->get_record('hotquestion', ['name' => $name], '*', MUST_EXIST);
    }

    /**
     * Get a hotquestion cmid from the quiz name.
     *
     * @param string $name hotquestion name.
     * @return stdClass cm from get_coursemodule_from_instance.
     */
    protected function get_cm_by_hotquestion_name(string $name): stdClass {
        $hotquestion = $this->get_hotquestion_by_name($name);
        return get_coursemodule_from_instance('hotquestion', $hotquestion->id, $hotquestion->course);
    }

}
