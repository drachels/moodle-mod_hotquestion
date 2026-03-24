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
 * Upgrade code for install
 *
 * @package   mod_hotquestion
 * @copyright 2012 Zhang Anzhen
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine

/**
 * Upgrade this hotquestion instance - this function could be skipped but it will be needed later.
 * @param int $oldversion The old version of the hotquestion module
 * @return bool
 */
function xmldb_hotquestion_upgrade($oldversion = 0) {
    global $CFG, $DB;
    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.
    $result = true;

    // 1.9.0 Upgrade line.
    if ($result && $oldversion < 2007040100) {
        // Define field course to be added to hotquestion.
        $table = new XMLDBTable('hotquestion');
        $field = new XMLDBField('course');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'id');
        // Launch add field course.
        $result = $result && $table->add_field($field);

        // Define field intro to be added to hotquestion.
        $field = new xmldb_field('intro');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null, null, 'name');

        // Launch add field intro.
        $result = $result && $table->add_field($field);

        // Define field introformat to be added to hotquestion.
        $field = new xmldb_field('introformat');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'intro');
        // Launch add field introformat.
        $result = $result && $table->add_field($field);
    }

    if ($result && $oldversion < 2007040101) {
        // Define field timecreated to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('timecreated');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'introformat');

        // Launch add field timecreated.
        $result = $result && $table->add_field($field);

        $field = new xmldb_field('timemodified');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'timecreated');
        // Launch add field timemodified.
        $result = $result && $table->add_field($table, $field);

        // Define index course (not unique) to be added to hotquestion.
        $result = $result && $table->add_index('course', XMLDB_INDEX_NOTUNIQUE, ['course']);
    }

    if ($result && $oldversion < 2007040200) {
        // Add some actions to get them properly displayed in the logs.
        $rec = new stdClass();
        $rec->module = 'hotquestion';
        $rec->action = 'add';
        $rec->mtable = 'hotquestion';
        $rec->filed = 'name';
        // Insert the add action in log_display.
        $result = $DB->insert_record('log_display', $rec);
        // Now the update action.
        $rec->action = 'update';
        $result = $DB->insert_record('log_display', $rec);
        // Now the view action.
        $rec->action = 'view';
        $result = $DB->insert_record('log_display', $rec);
    }

    // 3.1.0 Upgrade start here.
    if ($oldversion < 2016100300) {
        // Define field submitdirections to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field(
            'submitdirections',
            XMLDB_TYPE_CHAR,
            '255',
            null,
            XMLDB_NOTNULL,
            null,
            'Submit your question here',
            'introformat'
        );

        // Conditionally launch add field submitdirections.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timeopen to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('timeopen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field timeopen.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timeclose to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('timeclose', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeopen');

        // Conditionally launch add field timeclose.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2016100300, 'hotquestion');
    }

    // 3.3.2 Upgrade start here.
    if ($oldversion < 2017122500) {
        // Define field approval to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('approval', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'anonymouspost');

        // Conditionally launch add field approval.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field approved to be added to hotquestion_questions.
        $table = new xmldb_table('hotquestion_questions');
        $field = new xmldb_field('approved', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'anonymous');

        // Conditionally launch add field approval.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2017122500, 'hotquestion');
    }

    // 3.4.0 Upgrade start here.
    if ($oldversion < 2018010100) {
        // Define field teacherpriority to be added to hotquestion_questions.
        $table = new xmldb_table('hotquestion_questions');
        $field = new xmldb_field('tpriority', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'approved');

        // Conditionally launch add field teacherpriority.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2018010100, 'hotquestion');
    }

    if ($oldversion < 2019112100) {
        // Define field teacherpriorityvisibility to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('teacherpriorityvisibility', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1', 'timeclose');

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field heatvisibility to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field(
            'heatvisibility',
            XMLDB_TYPE_INTEGER,
            '4',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'teacherpriorityvisibility'
        );

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2019112100, 'hotquestion');
    }

    if ($oldversion < 2020051000) {
        // Define field questionlabel to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('questionlabel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'Questions', 'timeclose');

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field teacherprioritylabel to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field(
            'teacherprioritylabel',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            'Priority',
            'teacherpriorityvisibility'
        );

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field heatlabel to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('heatlabel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'Heat', 'heatvisibility');

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field approvallabel to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('approvallabel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'Approved', 'approval');

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field removelabel to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('removelabel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'Remove', 'approvallabel');

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2020051000, 'hotquestion');
    }

    if ($oldversion < 2020052800) {
        // Define field heatlimit to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('heatlimit', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'heatlabel');

        // Conditionally launch add field heatlimit.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2020052800, 'hotquestion');
    }

    if ($oldversion < 2020121700) {
        // Define field authorhide to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('authorhide', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'anonymouspost');

        // Conditionally launch add field authorhide.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2020121700, 'hotquestion');
    }

    if ($oldversion < 2022041000) {
        // Define field scale to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('scale', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'removelabel');

        // Conditionally launch add field scale.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field assessed to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('assessed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'scale');

        // Conditionally launch add field assessed.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field assessedtimestart to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('assessedtimestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'assessed');

        // Conditionally launch add field assessedtimestart.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field assessedtimefinish to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field(
            'assessedtimefinish',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'assessedtimestart'
        );

        // Conditionally launch add field assessedtimefinish.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field comments to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('comments', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'assessedtimefinish');

        // Conditionally launch add field comments.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field format to be added to hotquestion_questions.
        $table = new xmldb_table('hotquestion_questions');
        $field = new xmldb_field('format', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'content');

        // Conditionally launch add field format.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2022041000, 'hotquestion');
    }

    // 4.1.0  Upgrade starts here.
    if ($oldversion < 2022070701) {
        // Define field assesstimestart to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('assesstimestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'assessed');

        // Conditionally launch add field assesstimestart.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field assesstimefinish to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field(
            'assesstimefinish',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'assessedtimestart'
        );

        // Conditionally launch add field assesstimefinish.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field grade to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'comments');

        // Conditionally launch add field grade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field postmaxgrade to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('postmaxgrade', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'grade');

        // Conditionally launch add field postmaxgrade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field factorheat to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('factorheat', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'postmaxgrade');

        // Conditionally launch add field factorheat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field factorpriority to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('factorpriority', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'factorheat');

        // Conditionally launch add field factorpriority.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field factorvote to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('factorvote', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'factorpriority');

        // Conditionally launch add field factorvote.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field completionpost to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('completionpost', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'factorvote');

        // Conditionally launch add field completionpost.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field completionvote to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('completionvote', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'completionpost');

        // Conditionally launch add field completionvote.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field completionpass to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('completionpass', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'completionvote');

        // Conditionally launch add field completionpass.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table hotquestion_grades to be created.
        $table = new xmldb_table('hotquestion_grades');

        // Adding fields to table hotquestion_grades.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('hotquestion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('rawrating', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table hotquestion_grades.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('hotquestion', XMLDB_KEY_FOREIGN, ['hotquestion'], 'hotquestion', ['id']);

        // Adding indexes to table hotquestion_grades.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('usergrade', XMLDB_INDEX_UNIQUE, ['hotquestion', 'userid']);

        // Conditionally launch create table for hotquestion_grades.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Redefine field teacherpriorityvisibility to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field(
            'teacherpriorityvisibility',
            XMLDB_TYPE_INTEGER,
            '2',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'questionlabel'
        );

        // Launch change of defaults for field teacherpriorityvisibility.
        $dbman->change_field_precision($table, $field);
        $dbman->change_field_notnull($table, $field);
        $dbman->change_field_default($table, $field);

        // Redefine field heatvisibility to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('heatvisibility', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'teacherprioritylabel');

        // Launch change of defaults for field heatvisibility.
        $dbman->change_field_precision($table, $field);
        $dbman->change_field_notnull($table, $field);
        $dbman->change_field_default($table, $field);

        // Redefine field heatlimit to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('heatlimit', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'heatlabel');

        // Launch change of defaults for field heatlimit.
        $dbman->change_field_precision($table, $field);
        $dbman->change_field_notnull($table, $field);
        $dbman->change_field_default($table, $field);

        // Redefine field anonymouspost to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('anonymouspost', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'heatlimit');

        // Launch change of defaults for field anonymouspost.
        $dbman->change_field_precision($table, $field);
        $dbman->change_field_notnull($table, $field);
        $dbman->change_field_default($table, $field);

        // Redefine field authorhide to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('authorhide', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'anonymouspost');

        // Launch change of defaults for field authorhide.
        $dbman->change_field_precision($table, $field);
        $dbman->change_field_notnull($table, $field);
        $dbman->change_field_default($table, $field);

        // Redefine field approval to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('approval', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'authorhide');

        // Launch change of defaults for field approval.
        $dbman->change_field_precision($table, $field);
        $dbman->change_field_notnull($table, $field);
        $dbman->change_field_default($table, $field);

        // Redefine field comments to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('comments', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'assesstimefinish');

        // Launch change of defaults for field comments.
        $dbman->change_field_precision($table, $field);
        $dbman->change_field_notnull($table, $field);
        $dbman->change_field_default($table, $field);

        // Redefine field anonymous to be added to hotquestion_questions.
        $table = new xmldb_table('hotquestion_questions');
        $field = new xmldb_field('anonymous', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'time');

        // Launch change of defaults for field anonymous.
        $dbman->change_field_precision($table, $field);
        $dbman->change_field_notnull($table, $field);
        $dbman->change_field_default($table, $field);

        // Redefine field approved to be added to hotquestion_questions.
        $table = new xmldb_table('hotquestion_questions');
        $field = new xmldb_field('approved', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'anonymous');

        // Launch change of defaults for field approved.
        $dbman->change_field_precision($table, $field);
        $dbman->change_field_notnull($table, $field);
        $dbman->change_field_default($table, $field);

        // The tpriority should always be 0 or higher. We want to find all records where this is not the case.
        // Get all the hotquestion_question records.
        $sql = "SELECT hqq.*
                  FROM {hotquestion_questions} hqq
                 WHERE hqq.id > 0";
        $tprioritystofix = $DB->get_records_sql($sql, null);
        // Find all the records with null for each hotquestion teacher priority.
        foreach ($tprioritystofix as $tprioritytofix) {
            if (!isset($tprioritytofix->tpriority)) {
                // Replace the null with 0 so the redefine will not fail.
                $tprioritytofix->tpriority = 0;
                $DB->update_record('hotquestion_questions', $tprioritytofix);
            };
        };

        // Redefine field tpriority to be added to hotquestion_questions.
        $table = new xmldb_table('hotquestion_questions');
        $field = new xmldb_field('tpriority', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'approved');

        // Launch change of defaults for field tpriority.
        $dbman->change_field_precision($table, $field);
        $dbman->change_field_notnull($table, $field);
        $dbman->change_field_default($table, $field);

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2022070701, 'hotquestion');
    }

    // 4.1.5 20230126 Upgrade starts here.
    if ($oldversion < 2023012600) {
        // If they exist, need code to drop two fields, assessedtimefinish and assessedtimestart.
        // Define field assessedtimefinish to be dropped from hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('assessedtimefinish');

        // Conditionally launch drop field assessedtimefinish.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field assessedtimestart to be dropped from hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('assessedtimestart');

        // Conditionally launch drop field assessedtimestart.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field viewaftertimeclose to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('viewaftertimeclose', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'timeclose');

        // Conditionally launch add field viewaftertimeclose.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2023012600, 'hotquestion');
    }

    if ($oldversion < 2026010200) {

        // Define field notifications to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('notifications', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'completionpass');

        // Conditionally launch add field notifications.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field mailed to be added to hotquestion_questions.
        $table = new xmldb_table('hotquestion_questions');
        $field = new xmldb_field('mailed', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'tpriority');

        // Conditionally launch add field mailed.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2026010200, 'hotquestion');
    }

    if ($oldversion < 2026032400) {
        // Define field minquestionsview to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('minquestionsview', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'submitdirections');

        // Conditionally launch add field minquestionsview.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field maxquestionsperuser to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field(
            'maxquestionsperuser',
            XMLDB_TYPE_INTEGER,
            '4',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'minquestionsview'
        );

        // Conditionally launch add field maxquestionsperuser.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2026032400, 'hotquestion');
    }

    return true;
}
