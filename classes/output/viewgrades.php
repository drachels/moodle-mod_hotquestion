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
 * Contains class mod_hotquestion_responses_table.
 *
 * @package   mod_hotquestion
 * @copyright 2022 Enrique Castro
 * @copyright AL Rachels (drachels@drachels.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hotquestion\output;

use table_sql;
use context_module;
use moodle_url;
use html_writer;
use user_picture;
use grade_item;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Class mod_hotquestion_responses_table.
 *
 * @package   mod_hotquestion
 * @copyright 2022 Enrique Castro
 * @copyright AL Rachels (drachels@drachels.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class viewgrades extends table_sql {
    /**
     * Maximum number of hotquestion questions to display in the "Show responses" table.
     */
    const PREVIEWCOLUMNSLIMIT = 10;

    /**
     * Maximum number of hotquestion questions answers to retrieve in one SQL query.
     * Mysql has a limit of 60, we leave 1 for joining with users table.
     */
    const TABLEJOINLIMIT = 59;

    /**
     * When additional queries are needed to retrieve more than TABLEJOINLIMIT questions answers, do it in chunks every x rows.
     * Value too small will mean too many DB queries, value too big may cause memory overflow.
     */
    const ROWCHUNKSIZE = 100;

    /** @var mod_hotquestion_structure */
    protected $hotquestion;

    /** @var int */
    protected $grandtotal = null;

    /** @var bool */
    protected $showall = false;

    /** @var string */
    protected $showallparamname = 'showall';

    /** @var string */
    protected $downloadparamname = 'download';

    /** @var int number of columns that were not retrieved in the main SQL query
     * (no more than TABLEJOINLIMIT tables with values can be joined). */
    protected $hasmorecolumns = 0;

    /** @var bool whether we are building this table for a external function */
    protected $buildforexternal = false;

    /** @var array the data structure containing the table data for the external function */
    protected $dataforexternal = [];

    /** @var grade_item the grade_item record for this assign instance's primary grade item. */
    private $gradeitem;

    /** @var array cache for things like the coursemodule name or the scale menu -
     *             only lives for a single request.
     */
    private $cache;

    /**
     * Constructor.
     *
     * @param mod_hotquestion $hotquestion
     * @param int $group Retrieve only users from this group (optional).
     * @param int $userid
     */
    public function __construct(\mod_hotquestion $hotquestion, $group = 0, $userid = 0) {
        $this->hotquestion = $hotquestion;

        parent::__construct('hotquestion-showranking-list-' . $hotquestion->cm->instance);

        $this->showall = optional_param($this->showallparamname, 0, PARAM_BOOL);
        $this->define_baseurl(new moodle_url(
            '/mod/hotquestion/grades.php',
            ['id' => $this->hotquestion->cm->id]
        ));

        // 20220520 Added to fix groups on grades.php page.
        $currentgroup = groups_get_activity_group($this->hotquestion->cm, true);
        // 20230407 Get the current group id and groupname for later use.
        $currentgroupid = groups_get_activity_group($this->hotquestion->cm);
        if ($currentgroup) {
            $group = $currentgroup;
            $groupname = groups_get_group_name($currentgroupid);
        } else {
            $group = '';
            $groupname = 'All participants';
        }

        if ($group) {
            $this->baseurl->param('group', $group);
        }

        if ($this->showall) {
            $this->baseurl->param($this->showallparamname, $this->showall);
        }

        // 20230407 Adding course name to filename.
        $coursename = format_string($hotquestion->course->fullname . ' - ');
        $name = format_string($hotquestion->instance->name);

        $this->is_downloadable(true);
        $this->is_downloading(
            optional_param($this->downloadparamname, 0, PARAM_ALPHA),
            $coursename . $name . ' - ' . $groupname,
            get_string('viewgrades', 'hotquestion')
        );
        $this->useridfield = 'userid';

        $this->init($group, $userid);
    }

    /**
     * Initialises table.
     * @param int $group retrieve only users from this group (optional)
     * @param int $userid
     */
    protected function init($group = 0, $userid = 0) {
        global $CFG, $DB;
        // 20220503 Changed votes to heatgiven. Added teacher priority and heatreceived.
        // 20220504 Added teacherpriority.
        $tablecolumns = [
            'userpic',
            'fullname',
            'questions',
            'teacherpriority',
            'heatgiven',
            'heatreceived',
            'rawrating',
            'finalgrade',
        ];

        // 20220716 Get the grade point setting for this Hot Question.
        $finalgrade = $this->hotquestion->instance->grade;
        // If HotQuestion is set for None or Points, then skip ahead.
        // If set for Scale, then figure out the scale index and entry for the maximum score.
        if ($finalgrade < 0) {
            if ($scale = $DB->get_record('scale', ['id' => -($this->hotquestion->instance->grade)])) {
                $this->cache['scale'] = make_menu_from_list($scale->scale);
            }

            $finalgrade = count($this->cache['scale']);
            $finalgrade .= '=' . ($this->cache['scale'][$finalgrade]);
        }

        $tableheaders = [
            get_string('userpic'),
            get_string('fullnameuser'),
            format_string($this->hotquestion->instance->questionlabel) . ' (' . ($this->hotquestion->instance->postmaxgrade) . ')',
            get_string('teacherpriority', 'hotquestion') . ' (' . ($this->hotquestion->instance->factorpriority) . '%)',
            get_string('heatgiven', 'hotquestion') . ' (' . ($this->hotquestion->instance->factorheat) . '%)',
            get_string('heatreceived', 'hotquestion') . ' (' . ($this->hotquestion->instance->factorvote) . '%)',
            get_string('grading', 'hotquestion'),
            get_string('finalgrade', 'hotquestion') . ' (' . ($finalgrade) . ')',
        ];

        $context = $this->get_context();

        // 20220428 Added Moodle branch check.
        if ($CFG->branch < 311) {
            $namefields = user_picture::fields('u', null, 'userid');
        } else {
            $userfieldsapi = \core_user\fields::for_userpic();
            $namefields = $userfieldsapi->get_sql('u', false, '', 'userid', false)->selects;
        }

        $fields = 'u.id, u.username, u.idnumber, ' . $namefields;
        $fields .= ', gg.rawgrade, gg.finalgrade, g.rawrating  ';
        $fields .= ', (SELECT COUNT(qq.id)
                         FROM {hotquestion_questions} qq
                        WHERE qq.userid = u.id AND qq.hotquestion = h.id ) AS questions';

        $fields .= ', (SELECT SUM(qq.tpriority)
                         FROM {hotquestion_questions} qq
                        WHERE qq.userid = u.id AND qq.hotquestion = h.id
                                               AND (qq.tpriority <> 0 OR qq.tpriority = 0)) AS teacherpriority';

        $fields .= ', (SELECT COUNT(v.id)
                         FROM {hotquestion_votes} v
                         JOIN {hotquestion_questions} qv ON v.question = qv.id
                        WHERE v.voter = u.id AND qv.hotquestion = h.id
                            ) AS heatgiven ';

        $fields .= ', (SELECT COUNT(qq.id)
                         FROM {hotquestion_questions} qq
                         JOIN {hotquestion_votes} v ON v.question = qq.id
                        WHERE qq.userid = u.id AND qq.hotquestion = h.id AND v.voter <> u.id ) AS heatreceived';

        [$esql, $params] = get_enrolled_sql($context, 'mod/hotquestion:view', $group, true);
        $from = " {user} u
                 JOIN {hotquestion} h ON h.id = :instance
            LEFT JOIN {hotquestion_grades} g ON g.hotquestion = h.id AND g.userid = u.id
                 JOIN ($esql) je ON je.id = u.id
                 JOIN {grade_items} gi ON gi.courseid = h.course AND gi.iteminstance = h.id AND gi.itemmodule = 'hotquestion'
            LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
                 ";

        $where = ' 1 = 1 ';
        if ($userid) {
            $where .= ' AND u.id = :userid ';
            $params['userid'] = $userid;
        }

        if ($this->is_downloading()) {
            // When downloading data:
            // Remove 'userpic' from downloaded data.
            array_shift($tablecolumns);
            array_shift($tableheaders);
            // 20220503 Removed unneeded code re:$extrafields from here.
        }

        $this->define_columns($tablecolumns);
        $this->define_headers($tableheaders);

        $this->sortable(true, 'lastname', SORT_ASC);
        $this->collapsible(true);
        $this->set_attribute('id', 'showentrytable');

        $params['instance'] = $this->hotquestion->instance->id;

        $this->set_sql($fields, $from, $where, $params);
        $this->set_count_sql("SELECT COUNT(u.id) FROM $from WHERE $where ", $params);
    }

    /**
     * Current context.
     * @return context_module
     */
    public function get_context(): \context {
        return context_module::instance($this->hotquestion->cm->id);
    }

    /**
     * Allows to set the display column value for all columns without "col_xxxxx" method.
     * @param string $column column name
     * @param stdClass $row current record result of SQL query
     */
    public function other_cols($column, $row) {
        if (preg_match('/^val(\d+)$/', $column, $matches)) {
            $items = $this->hotquestion->get_items();
            $itemobj = hotquestion_get_item_class($items[$matches[1]]->typ);
            $printval = $itemobj->get_printval($items[$matches[1]], (object) ['value' => $row->$column]);
            if ($this->is_downloading()) {
                $printval = s($printval);
            }

            return trim($printval);
        }

        return parent::other_cols($column, $row);
    }

    /**
     * Prepares column userpic for display.
     * @param stdClass $row
     * @return string
     */
    public function col_userpic($row) {
        global $OUTPUT;
        $user = user_picture::unalias($row, [], $this->useridfield);
        return $OUTPUT->user_picture($user, ['courseid' => $this->hotquestion->cm->course]);
    }

    /**
     * Prepares column questions for display.
     * @param stdClass $row
     * @return string
     */
    public function col_questions($row) {
        global $OUTPUT;

        return $row->questions;
    }

    /**
     * Prepares column tpriority for display.
     * @param stdClass $row
     * @return string
     */
    public function col_teacherpriority($row) {
        global $OUTPUT;

        if (isset($row->teacherpriority)) {
            return $row->teacherpriority;
        } else {
            $priority = '-';
        }

        return $priority;
    }

    /**
     * Prepares column heatgiven for display.
     * @param stdClass $row
     * @return string
     */
    public function col_heatgiven($row) {
        global $OUTPUT;

        return $row->heatgiven;
    }

    /**
     * Prepares column heatreceived for display.
     * @param stdClass $row
     * @return string
     */
    public function col_heatreceived($row) {
        global $OUTPUT;

        return $row->heatreceived;
    }

    /**
     * Prepares column rawrating for display.
     * @param stdClass $row
     * @return string
     */
    public function col_rawrating($row) {
        $item = $this->get_grade_item();
        $rating = format_float($row->rawrating, $item->get_decimals());

        if ($this->is_downloading()) {
            return $rating;
        }

        if ($rating) {
            $rating .= '&nbsp;/&nbsp;' . $this->hotquestion->instance->postmaxgrade;
        } else {
            $rating = '-';
        }

        return $rating;
    }

    /**
     * Prepares column finalgrade for display.
     * @param stdClass $row
     * @return string
     */
    public function col_finalgrade($row) {
        $item = $this->get_grade_item();

        if ($this->is_downloading()) {
            return format_float($row->finalgrade, $item->get_decimals());
        }

        return $this->display_grade($row->finalgrade);
    }

    /**
     * Query the db. Store results in the table object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar. Bar
     * will only be used if there is a fullname column defined for the table.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;
        $this->totalrows = $grandtotal = $this->get_total_users_count();
        if (!$this->is_downloading()) {
            $this->initialbars($useinitialsbar);

            [$wsql, $wparams] = $this->get_sql_where();
            if ($wsql) {
                $this->countsql .= ' AND ' . $wsql;
                $this->countparams = array_merge($this->countparams, $wparams);

                $this->sql->where .= ' AND ' . $wsql;
                $this->sql->params = array_merge($this->sql->params, $wparams);

                $this->totalrows = $DB->count_records_sql($this->countsql, $this->countparams);
            }

            if ($this->totalrows > $pagesize) {
                $this->pagesize($pagesize, $this->totalrows);
            }
        }

        if ($sort = $this->get_sql_sort()) {
            $sort = "ORDER BY $sort";
        }

        $sql = "SELECT
                {$this->sql->fields}
                FROM {$this->sql->from}
                WHERE {$this->sql->where}
                {$sort}";

        if (!$this->is_downloading()) {
            $this->rawdata = $DB->get_recordset_sql($sql, $this->sql->params, $this->get_page_start(), $this->get_page_size());
        } else {
            $this->rawdata = $DB->get_recordset_sql($sql, $this->sql->params);
        }
    }

    /**
     * Returns total number of reponses (without any filters applied).
     * @return int
     */
    public function get_total_users_count() {
        global $DB;

        if ($this->grandtotal === null) {
            $this->grandtotal = $DB->count_records_sql($this->countsql, $this->countparams);
        }

        return $this->grandtotal;
    }

    /**
     * Defines columns.
     * @param array $columns an array of identifying names for columns. If
     * columns are sorted then column names must correspond to a field in sql.
     */
    public function define_columns($columns) {
        parent::define_columns($columns);
        foreach ($this->columns as $column => $column) {
            // Automatically assign classes to columns.
            $this->column_class[$column] = ' ' . $column;
        }
    }

    /**
     * Displays the table.
     */
    public function display() {
        global $OUTPUT;
        groups_print_activity_menu($this->hotquestion->cm, $this->baseurl->out());

        $group = groups_get_activity_group($this->hotquestion->cm, true);
        $returnurl = new moodle_url('/mod/hotquestion/view.php', ['id' => $this->hotquestion->cm->id]);
        if ($group > 0) {
            $returnurl->param('group', $group);
        }
        $hotquestionname = format_string($this->hotquestion->instance->name);
        echo $OUTPUT->single_button($returnurl, get_string('returnto', 'hotquestion', $hotquestionname));

        $grandtotal = $this->get_total_users_count();
        if (!$grandtotal) {
            echo $OUTPUT->box(get_string('nothingtodisplay'), 'generalbox nothingtodisplay');
            return;
        }

        echo $OUTPUT->heading(get_string('viewgrades', 'hotquestion'), 4);
        $this->out(
            $this->showall ? $grandtotal : HOTQUESTION_DEFAULT_PAGE_COUNT,
            $grandtotal > HOTQUESTION_DEFAULT_PAGE_COUNT
        );

        // Toggle 'Show all' link.
        if ($this->totalrows > HOTQUESTION_DEFAULT_PAGE_COUNT) {
            if (!$this->use_pages) {
                echo html_writer::div(html_writer::link(
                    new moodle_url($this->baseurl, [$this->showallparamname => 0]),
                    get_string('showperpage', '', HOTQUESTION_DEFAULT_PAGE_COUNT)
                ), 'showall');
            } else {
                echo html_writer::div(html_writer::link(
                    new moodle_url($this->baseurl, [$this->showallparamname => 1]),
                    get_string('showall', '', $this->totalrows)
                ), 'showall');
            }
        }
    }

    /**
     * Download the data.
     */
    public function download() {
        \core\session\manager::write_close();
        $this->out($this->get_total_users_count(), false);
        exit;
    }

    /**
     * Take the data returned from the db_query and go through all the rows
     * processing each col using either col_{columnname} method or other_cols
     * method or if other_cols returns NULL then put the data straight into the
     * table.
     *
     * This overwrites the parent method because full SQL query may fail on Mysql
     * because of the limit in the number of tables in the join. Therefore we only
     * join 59 tables in the main query and add the rest here.
     *
     * @return void
     */
    public function build_table() {
        if ($this->rawdata instanceof \Traversable && !$this->rawdata->valid()) {
            return;
        }

        if (!$this->rawdata) {
            return;
        }

        $columnsgroups = [];
        if ($this->hasmorecolumns) {
            $items = $this->hotquestion->get_items(true);
            $notretrieveditems = array_slice($items, self::TABLEJOINLIMIT, $this->hasmorecolumns, true);
            $columnsgroups = array_chunk($notretrieveditems, self::TABLEJOINLIMIT, true);
        }

        $chunk = [];
        foreach ($this->rawdata as $row) {
            if ($this->hasmorecolumns) {
                $chunk[$row->id] = $row;
                if (count($chunk) >= self::ROWCHUNKSIZE) {
                    $this->build_table_chunk($chunk, $columnsgroups);
                    $chunk = [];
                }
            } else {
                if ($this->buildforexternal) {
                    $this->add_data_for_external($row);
                } else {
                    $this->add_data_keyed($this->format_row($row), $this->get_row_class($row));
                }
            }
        }

        $this->build_table_chunk($chunk, $columnsgroups);
    }

    /**
     * Retrieve additional columns. Database engine may have a limit on number of joins.
     *
     * @param array $rows Array of rows with already retrieved data, new values will be added to this array
     * @param array $columnsgroups array of arrays of columns. Each element has up to self::TABLEJOINLIMIT items. This
     *     is easy to calculate but because we can call this method many times we calculate it once and pass by
     *     reference for performance reasons
     */
    protected function build_table_chunk(&$rows, &$columnsgroups) {
        global $DB;
        if (!$rows) {
            return;
        }

        foreach ($columnsgroups as $columnsgroup) {
            $fields = 'c.id';
            $from = '{hotquestion_completed} c';
            $params = [];
            foreach ($columnsgroup as $nr => $item) {
                $fields .= ", v{$nr}.value AS val{$nr}";
                $from .= " LEFT OUTER JOIN {hotquestion_value} v{$nr} " .
                    "ON v{$nr}.completed = c.id AND v{$nr}.item = :itemid{$nr}";
                $params["itemid{$nr}"] = $item->id;
            }

            [$idsql, $idparams] = $DB->get_in_or_equal(array_keys($rows), SQL_PARAMS_NAMED);
            $sql = "SELECT $fields FROM $from WHERE c.id " . $idsql;
            $results = $DB->get_records_sql($sql, $params + $idparams);
            foreach ($results as $result) {
                foreach ($result as $key => $value) {
                    $rows[$result->id]->{$key} = $value;
                }
            }
        }

        foreach ($rows as $row) {
            if ($this->buildforexternal) {
                $this->add_data_for_external($row);
            } else {
                $this->add_data_keyed($this->format_row($row), $this->get_row_class($row));
            }
        }
    }

    /**
     * Returns html code for displaying "Download" button if applicable.
     */
    public function download_buttons() {
        global $OUTPUT;

        if ($this->is_downloadable() && !$this->is_downloading()) {
            return $OUTPUT->download_dataformat_selector(
                get_string('downloadas', 'table'),
                $this->baseurl->out_omit_querystring(),
                $this->downloadparamname,
                $this->baseurl->params()
            );
                    // Might see about adding a return button before or at the end of this return line of code.
        } else {
            return '';
        }
    }

    /**
     * Returns the grade item.
     */
    public function get_grade_item() {
        if ($this->gradeitem) {
            return $this->gradeitem;
        }

        $params = [
            'itemtype' => 'mod',
            'itemmodule' => 'hotquestion',
            'iteminstance' => $this->hotquestion->instance->id,
            'courseid' => $this->hotquestion->instance->course,
            'itemnumber' => 0,
        ];
        $this->gradeitem = grade_item::fetch($params);
        if (!$this->gradeitem) {
            throw new coding_exception(get_string('improperuseviewgradesclass', 'hotquestion'));
        }

        return $this->gradeitem;
    }

    /**
     * Return a grade in user-friendly form, whether it's a scale or not.
     *
     * @param mixed $grade float|null
     * @return string User-friendly representation of grade
     */
    public function display_grade($grade) {
        global $DB;

        static $scalegrades = [];

        $o = '';

        // If using points then we go here.
        if ($this->hotquestion->instance->grade >= 0) {
            // Normal number.
            if ($grade == -1 || $grade === null) {
                $o .= '-';
            } else {
                $item = $this->get_grade_item();
                $o .= grade_format_gradevalue($grade, $item);
                if ($item->get_displaytype() == GRADE_DISPLAY_TYPE_REAL) {
                    // If displaying the raw grade, also display the total value.
                    $o .= '&nbsp;/&nbsp;' . format_float($this->hotquestion->instance->grade, $item->get_decimals());
                }
            }

            return $o;
        } else {
            // If using scale and the Scale is missing go here.
            if (empty($this->cache['scale'])) {
                if ($scale = $DB->get_record('scale', ['id' => -($this->hotquestion->instance->grade)])) {
                    $this->cache['scale'] = make_menu_from_list($scale->scale);
                } else {
                    $o .= '-';
                    return $o;
                }
            }

            // Create a scaleid based on users current grade.
            $scaleid = (int)$grade;
            // If it is there, pick the users grade from the scale using the scaleid.
            if (isset($this->cache['scale'][$scaleid])) {
                $o .= $this->cache['scale'][$scaleid];
                return $o;
            }

            $o .= '-';
            return $o;
        }
    }
}
