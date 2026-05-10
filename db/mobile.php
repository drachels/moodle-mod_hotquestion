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
 * Mobile app definition.
 *
 * @package   mod_hotquestion
 * @copyright 2026 AL Rachels <drachels@drachels.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_hotquestion' => [
        'handlers' => [
            'mod_hotquestion' => [
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/hotquestion/pix/icon.svg',
                    'class' => '',
                ],
                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_course_view',
            ],
            'mod_hotquestion_submit' => [
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/hotquestion/pix/icon.svg',
                    'class' => '',
                ],
                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_submit_question',
                'init' => 'mobile_submit_question',
            ],
        ],
        'lang' => [
            ['modulename', 'mod_hotquestion'],
            ['noquestions', 'mod_hotquestion'],
            ['postbutton', 'mod_hotquestion'],
            ['anonymous', 'mod_hotquestion'],
            ['hotquestionmobileopenbrowser', 'mod_hotquestion'],
            ['hotquestionmobileaskquestion', 'mod_hotquestion'],
            ['hotquestionmobilesubmit', 'mod_hotquestion'],
            ['hotquestionmobilepostanon', 'mod_hotquestion'],
            ['hotquestionmobilesaved', 'mod_hotquestion'],
            ['hotquestionmobilenoask', 'mod_hotquestion'],
            ['hotquestionmobileapprovalrequired', 'mod_hotquestion'],
            ['hotquestionmobilewebmhint', 'mod_hotquestion'],
        ],
        'css' => $CFG->wwwroot . '/mod/hotquestion/styles.css',
    ],
];
