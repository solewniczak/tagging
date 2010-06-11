<?php

// Copyright (c) 2006-2007 Alex King. All rights reserved.
// http://alexking.org/projects/php-tag-engine
//
// Released under the LGPL license
// http://www.opensource.org/licenses/lgpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************

/**
 * This file contains the PHP Tag Engine class definition.
 *
 * @package phptagengine
 */

require_once 'phptagengine/phptagengine.class.inc.php';

function throw_error($str, $int, $int2) {
        trigger_error($str);
}

/**
 * The phptagengine class, all the good stuff happens here.
 *
 * @package phptagengine
 */
class tagging_phptagengine extends phptagengine {
    public function __construct($db, $table_prefix, $section_title, $user, $lang) {
        // -- database info
            $this->db = $db; // where $db is your ADOdb instance
            $this->table_tags = $table_prefix.'tags';
            $this->table_tag_names = $table_prefix.'tag_names';
            $this->table_users = $table_prefix.'users';
            $this->table_users_name = 'name';

        $dict = NewDataDictionary($db);
        $tables = $dict->MetaTables();
        $is_installed = true;
        foreach(array($this->table_tags, $this->table_tag_names) as $table) {
            if (!in_array($table, $tables)) {
                $is_installed = false;
            }
        }
        if (!$is_installed) {
            $this->install();
        }

        // -- misc

        $this->base_url = DOKU_URL . 'lib/plugins/tagging/phptagengine/';
        $this->ajax_handler = DOKU_URL . 'lib/exe/ajax.php?call=tagging';
        $this->tag_browse_url = '?do=search&id=<tag>#' . str_replace(' ', '_', strtolower($section_title));

        // -- default values (optional)

        $this->default_type = '';
        $this->default_user = $user;

        // -- language file

        $langmap = array('en' => 'english', 'de' => 'german', 'fr' => 'french',
                         'nl' => 'nederlands', 'no' => 'norwegian',
                         'es' => 'spanish');

        if (isset($langmap[$lang])) {
            $lang = $langmap[$lang];
        }

        require_once 'phptagengine/languages/english.inc.php';
        include_once "phptagengine/languages/$lang.inc.php";
        $this->strings = $pte->strings;
        if ($lang === 'german') {
            $this->strings['action_save'] = 'Speichern';
            $this->strings['action_saving'] = 'Speichern…';
        }

        // -- buttons

        $this->edit_button_display = 'text';
        $this->edit_button_image_url = 'phptagengine/images/icon_edit_tag_small.gif';

        $this->show_remove_links = false;
        $this->delete_button_display = 'text';
        $this->delete_button_image_url = 'phptagengine/images/icon_delete_tag.gif';

        // -- Yahoo! Auto-Complete
        $this->yac = true;
    }

        // Add class 'button' to the buttons
        function html_item_tags($item, $type = null, $user = null, $use_cache = false, $read_only = false) {
                $user = $this->default_value('user', $user);
                $type = $this->default_value('type', $type);
                $tags = $this->get_tags($item, $type, $user, $use_cache);
                if (count($tags) > 0) {
                        $tags_class = ' pte_has_tags';
                }
                else {
                        $tags_class = '';
                }
                print('
                        <!-- PHP Tag Engine html_item_tags for '.$item.' - begin -->
                        <div id="pte_tag_form_'.$item.'" class="pte_tags_form'.$tags_class.'">
                                <form id="pte_tag_edit_form_'.$item.'" action="'.$this->ajax_handler.'" onsubmit="pte.save_tags(\''.$user.'\', \''.$item.'\', this.tags.value, this.type.value); return false;">
                                        <label for="pte_tags_'.$item.'">'.$this->strings['label_tags'].'</label>
                                        <ul id="pte_tags_list_'.$item.'" class="pte_tags_list">
                ');
                if (count($tags) > 0) {
                        foreach ($tags as $id => $tag) {
                                print('
                                                <li id="pte_tag_'.$item.'_'.$tag.'"><a href="'.hsc($this->tag_browse_url($tag, $type)).'">'.$this->html($tag).'</a>
                                ');
                                if ($this->show_remove_links && !$read_only) {
                                        print('
                                                        <a href="javascript:void(pte.remove_tag(\''.$item.'\', \''.$tag.'\', \''.$type.'\'));" title="'.$this->strings['action_delete'].'">'.$this->button_display('delete').'</a>
                                        ');
                                }
                                print('
                                                </li>
                                ');
                        }
                        $edit_value = implode(' ', $tags).' ';
                }
                else {
                        print('
                                                <li>'.$this->strings['data_none'].'</li>
                        ');
                        $edit_value = '';
                }
                if ($read_only) {
                        print('
                                        </ul>
                                </form>
                        ');
                }
                else {
                        print('
                                                <li class="pte_edit"><a href="javascript:void(pte.item_tag_view(\''.$item.'\', \'edit\'));">'.$this->button_display('edit').'</a></li>
                                        </ul>
                                        <fieldset id="pte_tags_edit_'.$item.'" class="pte_tags_edit">
                                                <div class="pte_edit_wrapper">
                                                        <input type="text" id="pte_tags_edit_field_'.$item.'" class="pte_tags_edit_field" name="tags" value="'.$edit_value.'" />
                        ');
                        if ($this->yac) {
                                print('
                                                        <div id="yac_container_'.$item.'" class="yac_list"></div>
                                ');
                        }
                        print('
                                                </div>
                                                <input class="button" type="submit" name="submit_button" value="'.$this->strings['action_save'].'" />
                                                <input class="button" type="button" name="cancel_button" value="'.$this->strings['action_cancel'].'" onclick="pte.item_tag_view(\''.$item.'\', \'view\')" />
                                                <input type="hidden" id="pte_tags_edit_type_'.$item.'" name="type" value="'.$type.'" />
                                        </fieldset>
                                        <span id="pte_tags_saving_'.$item.'" class="pte_tags_saving">'.$this->strings['action_saving'].'</span>
                                </form>
                        </div>
                        ');
                        if ($this->yac) {
                                print('
                                <script type="text/javascript"><!--
                                yac = new YAHOO.widget.AutoComplete("pte_tags_edit_field_'.$item.'","yac_container_'.$item.'", yac_tags);
                                yac.delimChar = " ";
                                yac.maxResultsDisplayed = 20;
                                yac.queryDelay = 0;
                                // --></script>
                                ');
                        }
                }
                print('<!-- PHP Tag Engine html_item_tags for '.$item.' - end -->'."\n");
        }



    /**
     * Set a tag to lowercase and remove spaces, could be extended in the future
     * @param mixed $value the value to be normalized
     * @param string $type the type of value, used for the switch statement
     * @return mixed
     */
    function normalize($value, $type = 'tag') {
        switch ($type) {
            case 'tag':
                $value = utf8_strtolower($value, 'UTF-8');
                $value = preg_replace('|[^\w\dßäüö_.\-@#$%*!&]|i', '', $value);
                break;
        }
        return $value;
    }

    /**
     * Search items with a specific tag
     */
    function browse_tag($name) {
        $id = $this->get_tag_id($name);
        if ($id === false) {
            return false;
        }
        $result = $this->db->Execute("
            SELECT item AS ITEM, COUNT(user) as N
            FROM $this->table_tags
            WHERE tag = $id
            GROUP BY item
        ") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
        return $result;
    }

    /**
     * Return data for a user-specific tagcloud
     */
    function user_tagcloud($user, $maxcount = 20) {
        $result = $this->db->Execute("
            SELECT COUNT(t.item) AS N, tn.name as NAME
            FROM $this->table_tag_names tn
            LEFT OUTER JOIN $this->table_tags t
            ON tn.id = t.tag
            WHERE t.user = '$user'
            GROUP BY t.tag
            ORDER BY N DESC
            LIMIT $maxcount
        ") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));

        $data_arr = array();
        $max = -1;
        $min = -1;

        while ($data = $result->FetchNextObject()) {
            $data_arr[$data->NAME] = $data->N;
            if ($max < $data->N) $max = $data->N;
            if ($min > $data->N || $min === -1) $min = $data->N;
        }

        ksort($data_arr);

        return array($min, $max, $data_arr);
    }

    /**
     * Return data for a page-specific tagcloud
     */
    function tagcloud($page, $maxcount = 20) {
        $result = $this->db->Execute("
            SELECT tn.name AS NAME, COUNT(t.user) AS N
            FROM $this->table_tag_names tn
            LEFT OUTER JOIN $this->table_tags t
            ON tn.id = t.tag
            WHERE t.item = '$page'
            GROUP BY t.tag
            ORDER BY N DESC
            LIMIT $maxcount
        ") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));

        $data_arr = array();
        $max = -1;
        $min = -1;

        while ($data = $result->FetchNextObject()) {
            $data_arr[$data->NAME] = $data->N;
            if ($max < $data->N) $max = $data->N;
            if ($min > $data->N || $min === -1) $min = $data->N;
        }

        ksort($data_arr);

        return array($min, $max, $data_arr);
    }

    /**
     * Print the CSS and JS needed in the HTML output
     */
    function html_pte() {
        require_once DOKU_INC . '/inc/JSON.php';
        $json = new JSON();
        $keys = array('ajax_handler', 'tag_browse_url', 'strings',
                      'show_remove_links', 'edit_button_display',
                      'edit_button_image_url', 'delete_button_display',
                      'delete_button_image_url');
        $data = array();
        foreach($keys as $key) {
            $data[$key] = $this->$key;
        }
        $data['req'] = false;
        echo 'var pte = ' . $json->encode($data) . ';' . DOKU_LF;
    }

    function html_head() {
        require_once DOKU_INC . '/inc/JSON.php';
        $json = new JSON();
        ?><script type="text/javascript" charset="utf-8"><!--//--><![CDATA[//><!--
        var tagging_tags = <?php echo $json->encode(array_values($this->get_all_tags())); ?>;
        //--><!]]></script>
        <style type="text/css">
            @import url('<?php echo $this->base_url; ?>phptagengine.css?version=<?php echo $this->version; ?>');
        </style><?php
    }
}
