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
 * A class for representing question categories.
 *
 * @package    core_question
 * @subpackage questionbank
 * @copyright   {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

// Number of categories to display on page.
define('QUESTION_PAGE_LENGTH', 25);

require_once($CFG->libdir . '/listlib.php');


/**
 * Class representing a list of question categories for exporting
 *
 * @copyright   {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_category_list_for_export extends moodle_list {
    /**
     * @var string $table contains name of table.
     */
    public $table = "question_categories";
    /**
     * @var string $listitemclassname contains name of extended class list_item.
     */
    public $listitemclassname = 'question_category_list_item';
    /**
     * @var question_category_list_for_export $nextlist reference to list displayed below this one.
     */
    public $nextlist = null;
    /**
     * @var question_category_list_for_export $lastlist reference to list displayed above this one.
     */
    public $lastlist = null;
    /**
     * @var object $context context.
     */
    public $context = null;
    /**
     * @var string $sortby contains parameters of sort category.
     */
    public $sortby = 'parent, sortorder, name';

    /**
     * Constructor.
     *
     * @param string $type
     * @param string $attributes
     * @param boolean $editable
     * @param moodle_url $pageurl url for this page
     * @param integer $page if 0 no pagination. (These three params only used in top level list.)
     * @param string $pageparamname name of url param that is used for passing page no
     * @param integer $itemsperpage no of top level items.
     * @param object $context 
     */
    public function __construct($type='ul', $attributes='', $editable = false, $pageurl=null, $page = 0,
                $pageparamname = 'page', $itemsperpage = 20, $context = null) {
        parent::__construct('ul', '', $editable, $pageurl, $page, 'cpage', $itemsperpage);
        $this->context = $context;
    }

    /**
     * Get an array of records of list items.
     */
    public function get_records() {
        $this->records = get_categories_for_contexts($this->context->id, $this->sortby);
    }
}


/**
 * An item in a list of question categories.
 *
 * @copyright   {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_category_list_item extends list_item {

    /**
     * Set icon html
     * @param boolean $first
     * @param boolean $last
     * @param object $lastitem
     */
    public function set_icon_html($first, $last, $lastitem) {

    }

    /**
     * Output the html just for this item. Called by to_html which adds html for children.
     * @param array $extraargs any extra data that is needed to print the list item may be used by sub class.
     * @return the html just for this item
     */
    public function item_html($extraargs = array()) {
        $category = $this->item;

        // Each section adds html to be displayed as part of this list item.
        $item = '';
        $item .= html_writer::checkbox('cat' . $category->id, 1, false, '',
                array('id' => 'checkcat' . $category->id)) . ' ';
        $item .= html_writer::tag('b', format_string($category->name, true,
                array('context' => $this->parentlist->context))) . ' ';
        $item .= format_string('(' . $category->questioncount . ')', true) . ' ';

        return $item;
    }
}


/**
 * Class representing question category
 *
 * @copyright  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_category_object {

    /**
     * @var array common language strings.
     */
    public $str;

    /**
     * @var array $editlists nested lists to display categories.
     */
    public $editlists = array();
    /**
     * @var string $tab margin category.
     */
    public $tab;
    /**
     * @var int $tabsize size of margin.
     */
    public $tabsize = 3;

    /**
     * @var moodle_url Object representing url for this page
     */
    public $pageurl;

    /**
     * @var question_category_edit_form Object representing form for adding / editing categories.
     */
    public $catform;

    /**
     * Constructor
     *
     * Gets necessary strings and sets relevant path information
     * @param int $page
     * @param moodle_url $pageurl
     * @param array $contexts
     */
    public function __construct($page, $pageurl, $contexts) {
        global $CFG, $COURSE, $OUTPUT;

        $this->tab = str_repeat('&nbsp;', $this->tabsize);

        $this->pageurl = $pageurl;

        $this->initialize($page, $contexts);
    }

    /**
     * Initializes this classes general category-related variables
     * @param int $page
     * @param array $contexts
     */
    public function initialize($page, $contexts) {
        $lastlist = null;
        foreach ($contexts as $context) {
            $this->editlists[$context->id] = new question_category_list_for_export('ul', '',
                    true, $this->pageurl, $page, 'cpage', QUESTION_PAGE_LENGTH, $context);
            $this->editlists[$context->id]->lastlist =& $lastlist;
            if ($lastlist !== null) {
                $lastlist->nextlist =& $this->editlists[$context->id];
            }
            $lastlist =& $this->editlists[$context->id];
        }

        $count = 1;
        $paged = false;
        foreach ($this->editlists as $key => $list) {
            list($paged, $count) = $this->editlists[$key]->list_from_records($paged, $count);
        }
    }

    /**
     * Outputs a list to allow editing/rearranging of existing categories
     *
     * $this->initialize() must have already been called
     * @return the html all of categories
     */
    public function output_edit_lists() {
        global $OUTPUT;

        $result = '';

        foreach ($this->editlists as $context => $list) {
            $listhtml = $list->to_html(0, array('str' => $this->str));
            if ($listhtml) {
                $result .= $OUTPUT->box_start('boxwidthwide boxaligncenter generalbox questioncategories contextlevel'
                        . $list->context->contextlevel);
                $fullcontext = context::instance_by_id($context);
                $result .= $OUTPUT->heading(get_string('questioncatsfor', 'question', $fullcontext->get_context_name()), 3);
                $result .= $listhtml;
                $result .= $OUTPUT->box_end();
            }
        }
        $result .= $list->display_page_numbers();

        return $result;
    }
}
