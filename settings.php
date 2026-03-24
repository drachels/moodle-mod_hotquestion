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
 * Administration settings definitions for the Hot Question module.
 *
 * @package    mod_hotquestion
 * @copyright  2016 onwards AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */


defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot . '/mod/hotquestion/lib.php');
    require_once($CFG->dirroot . '/mod/hotquestion/locallib.php');

    // Recent activity setting.
    $name = new lang_string('showrecentactivity', 'mod_hotquestion');
    $description = new lang_string('showrecentactivityconfig', 'mod_hotquestion');
    $setting = new admin_setting_configcheckbox(
        'mod_hotquestion/showrecentactivity',
        $name,
        $description,
        0
    );
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    // Default submit instructions setting.
    $settings->add(new admin_setting_configtext(
        'mod_hotquestion/submitinstructions',
        new lang_string('inputquestion', 'hotquestion'),
        new lang_string('inputquestion_descr', 'hotquestion'),
        'Submit your question here:',
        PARAM_TEXT,
        25
    ));

    // Default minimum posts required before viewing other users posts.
    $settings->add(new admin_setting_configtext(
        'mod_hotquestion/minquestionsview',
        new lang_string('minquestionsview', 'hotquestion'),
        new lang_string('minquestionsview_descr', 'hotquestion'),
        0,
        PARAM_INT,
        4
    ));

    // Default maximum posts per user in the current round.
    $settings->add(new admin_setting_configtext(
        'mod_hotquestion/maxquestionsperuser',
        new lang_string('maxquestionsperuser', 'hotquestion'),
        new lang_string('maxquestionsperuser_descr', 'hotquestion'),
        0,
        PARAM_INT,
        4
    ));

    // Default heading questionlabel setting.
    $settings->add(new admin_setting_configtext(
        'mod_hotquestion/questionlabel',
        new lang_string('questionlabel', 'hotquestion'),
        new lang_string('questionlabel_descr', 'hotquestion'),
        'Questions',
        PARAM_TEXT,
        20
    ));

    // Default hide Priority column visibility setting.
    $settings->add(new admin_setting_configcheckbox_with_advanced(
        'mod_hotquestion/teacherpriorityvisibility',
        get_string('teacherpriorityvisibility', 'hotquestion'),
        get_string('teacherpriorityvisibility_descr', 'hotquestion'),
        ['value' => 1, 'adv' => false]
    ));

    // Default heading prioritylabel setting.
    $settings->add(new admin_setting_configtext(
        'mod_hotquestion/teacherprioritylabel',
        new lang_string('teacherprioritylabel', 'hotquestion'),
        new lang_string('teacherprioritylabel_descr', 'hotquestion'),
        'Priority',
        PARAM_TEXT,
        20
    ));

    // Default allow Heat column visibility setting.
    $settings->add(new admin_setting_configcheckbox_with_advanced(
        'mod_hotquestion/heatvisibility',
        get_string('heatvisibility', 'hotquestion'),
        get_string('heatvisibility_descr', 'hotquestion'),
        ['value' => 1, 'adv' => false]
    ));

    // Default heading heatlabel setting.
    $settings->add(new admin_setting_configtext(
        'mod_hotquestion/heatlabel',
        new lang_string('heatlabel', 'hotquestion'),
        new lang_string('heatlabel_descr', 'hotquestion'),
        'Heat',
        PARAM_TEXT,
        20
    ));

    // Default heading heatlimit setting.
    $tl = [];
    for ($i = 0; $i <= 10; $i++) {
        $tl[] = $i;
    }

    $settings->add(new admin_setting_configselect(
        'mod_hotquestion/heatlimit',
        get_string('heatlimit', 'hotquestion'),
        get_string('heatlimit_descr', 'hotquestion'),
        5,
        $tl
    ));

    // Default allow anonymous post setting.
    $settings->add(new admin_setting_configcheckbox_with_advanced(
        'mod_hotquestion/anonymouspost',
        get_string('allowanonymouspost', 'hotquestion'),
        get_string('allowanonymouspost_descr', 'hotquestion'),
        ['value' => 0, 'adv' => false]
    ));

    // Default allow hide author info from students.
    $settings->add(new admin_setting_configcheckbox_with_advanced(
        'mod_hotquestion/allowauthorinfohide',
        get_string('allowauthorinfohide', 'hotquestion'),
        get_string('allowauthorinfohide_descr', 'hotquestion'),
        ['value' => 0, 'adv' => false]
    ));

    // Default heading removelabel setting.
    $settings->add(new admin_setting_configtext(
        'mod_hotquestion/removelabel',
        new lang_string('removelabel', 'hotquestion'),
        new lang_string('removelabel_descr', 'hotquestion'),
        'Remove',
        PARAM_TEXT,
        20
    ));

    // Default approval required setting.
    $settings->add(new admin_setting_configcheckbox_with_advanced(
        'mod_hotquestion/requireapproval',
        get_string('requireapproval', 'hotquestion'),
        get_string('requireapproval_descr', 'hotquestion'),
        ['value' => 0, 'adv' => false]
    ));

    // Default heading approvallabel setting.
    $settings->add(new admin_setting_configtext(
        'mod_hotquestion/approvallabel',
        new lang_string('approvallabel', 'hotquestion'),
        new lang_string('approvallabel_descr', 'hotquestion'),
        'Approved',
        PARAM_TEXT,
        20
    ));

    // Default view after time close setting.
    $settings->add(new admin_setting_configcheckbox_with_advanced(
        'mod_hotquestion/viewaftertimeclose',
        get_string('viewaftertimeclose', 'hotquestion'),
        get_string('viewaftertimeclose_descr', 'hotquestion'),
        ['value' => 1, 'adv' => false]
    ));

    // Default allow comments setting.
    $settings->add(new admin_setting_configcheckbox(
        'mod_hotquestion/allowcomments',
        get_string('allowcomments', 'hotquestion'),
        get_string('cnfallowcomments', 'hotquestion'),
        0
    ));

    // 20260102 Default allow notification setting.
    $settings->add(new admin_setting_configcheckbox(
        'mod_hotquestion/allownotifications',
        get_string('allownotifications', 'hotquestion'),
        get_string('canallownotifications', 'hotquestion'),
        0
    ));
}
