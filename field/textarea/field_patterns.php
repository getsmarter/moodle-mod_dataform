<?php
// This file is part of Moodle - http://moodle.org/.
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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * @package mod-dataform
 * @package field-textarea
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/dataform/field/field_patterns.php");

/**
 *
 */
class mod_dataform_field_textarea_patterns extends mod_dataform_field_patterns {

    /**
     *
     */
    public function get_replacements($tags = null, $entry = null, array $options = null) {
        $field = $this->_field;
        $fieldname = $field->name();
        $edit = !empty($options['edit']) ? $options['edit'] : false;

        $replacements = array();
        // rules support
        $tags = $this->add_clean_pattern_keys($tags);

        foreach ($tags as $cleantag => $tag) {
            $params = null;
            $required = $this->is_required($tag);
            switch ($cleantag) {

                case "[[$fieldname]]":
                    if ($edit) {
                        $replacements[$tag] = array('', array(array($this,'display_edit'), array($entry, $required)));
                    } else {
                        $replacements[$tag] = array('html', $this->display_browse($entry));
                    }
                    break;

                // plain text, no links
                case "[[$fieldname:text]]":
                    if ($edit) {
                        $replacements[$tag] = array('', array(array($this,'display_edit'), array($entry, $required)));
                    } else {
                        $replacements[$tag] = array('html', $this->display_browse($entry, array('text' => true)));
                    }
                    break;

                // plain text, with links
                case "[[$fieldname:textlinks]]":
                    if ($edit) {
                        $replacements[$tag] = array('', array(array($this,'display_edit'), array($entry, $required)));
                    } else {
                        $replacements[$tag] = array('html', $this->display_browse($entry, array('text' => true, 'links' => true)));
                    }
                    break;

                case "[[{$fieldname}:wordcount]]":
                    if ($edit) {
                        $patterns[$tag] = '';
                    } else {
                        $patterns[$tag] = array('html', $this->word_count($entry));
                    }
                    break;
            }
        }


        return $replacements;
    }

    /**
     *
     */
    public function validate_data($entryid, $tags, $data) {
        $field = $this->_field;
        $fieldid = $field->id();
        $fieldname = $field->name();

        $tags = $this->add_clean_pattern_keys($tags);
        $editabletags = array(
            "[[$fieldname]]",
            "[[$fieldname:text]]",
            "[[$fieldname:textlinks]]"
        );

        if (!$field->is_editor() or !can_use_html_editor()) {
            $formfieldname = "field_{$fieldid}_{$entryid}";
            $cleanformat = PARAM_NOTAGS;
        } else {
            $formfieldname = "field_{$fieldid}_{$entryid}[text]";
            $cleanformat = PARAM_CLEANHTML;
        }

        foreach ($editabletags as $cleantag) {
           if (array_key_exists($cleantag, $tags)) {
                if ($this->is_required($tags[$cleantag])) {
                    if (!isset($data->$formfieldname)
                            or !$content = clean_param($data->$formfieldname, $cleanformat)) {
                        return array($formfieldname, get_string('fieldrequired', 'dataform'));
                    }
                }
            }
        }
    }

    /**
     *
     */
    public function display_edit(&$mform, $entry, $required = false) {
        global $PAGE, $CFG;

        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        // word count
        if ($field->get('param9')) {
            $mform->addElement('html', '<link type="text/css" rel="stylesheet" href="'. "$CFG->libdir/yui/2.8.2/build/progressbar/assets/skins/sam/progressbar.css". '">');

            $pbcontainer = '<div id="'. "id_{$fieldname}_wordcount_pb". '"></div>';
            $minvaluecontainer = '<div id="'. "id_{$fieldname}_wordcount_minvalue". '" class="yui-pb-range" style="float:left;">0</div>';
            $maxvaluecontainer = '<div id="'. "id_{$fieldname}_wordcount_maxvalue". '" class="yui-pb-range" style="float:right;">'.$this->field->param8.'</div>';
            $valuecontainer = '<div class="yui-pb-caption"><span id="'. "id_{$fieldname}_wordcount_value". '"></span></div>';
            $captionscontainer = '<div id="'. "id_{$fieldname}_wordcount_captions". '">'.
                                    $minvaluecontainer. $maxvaluecontainer. $valuecontainer.
                                    '</div>';
            $mform->addElement('html', '<table style="margin-left:16%;"><tr><td>'.
                                        $pbcontainer.
                                        $captionscontainer.
                                        '</td></tr></table>');

            $options = new object;
            $options->minValue = 0;
            $options->maxValue = $field->get('param8');
            $options->value = 0;
            $options->minRequired = $field->get('param7');
            $options->identifier = $fieldname;

            $module = array(
                'name' => 'M.dataform_wordcount_bar',
                'fullpath' => '/mod/dataform/dataform.js',
                'requires' => array('yui2-yahoo-dom-event', 'yui2-element', 'yui2-animation', 'yui2-progressbar'));

            $PAGE->requires->js_init_call('M.dataform_wordcount_bar.init', array($options), true, $module);
        }

        // editor
        $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;

        $attr = array();
        $attr['cols'] = !$field->get('param2') ? 40 : $field->get('param2');
        $attr['rows'] = !$field->get('param3') ? 20 : $field->get('param3');

        $data = new object;
        $data->$fieldname = isset($entry->{"c{$fieldid}_content"}) ? $entry->{"c{$fieldid}_content"} : '';

        if (!$field->is_editor() or !can_use_html_editor()) {
            $mform->addElement('textarea', $fieldname, null, $attr);
            $mform->setDefault($fieldname, $data->$fieldname);
            if ($required) {
                $mform->addRule($fieldname, null, 'required', null, 'client');
            }
        } else {
            // format
            $data->{"{$fieldname}format"} = isset($entry->{"c{$fieldid}_content1"}) ? $entry->{"c{$fieldid}_content1"} : FORMAT_HTML;

            $data = file_prepare_standard_editor($data, $fieldname, $field->editor_options(), $field->df()->context, 'mod_dataform', 'content', $contentid);

            $mform->addElement('editor', "{$fieldname}_editor", null, $attr , $field->editor_options());
            $mform->setDefault("{$fieldname}_editor", $data->{"{$fieldname}_editor"});
            $mform->setDefault("{$fieldname}[text]", $data->$fieldname);
            $mform->setDefault("{$fieldname}[format]", $data->{"{$fieldname}format"});
            if ($required) {
                $mform->addRule("{$fieldname}[text]", null, 'required', null, 'client');
            }
        }
    }

    /**
     *
     */
    public function word_count($entry) {

        $fieldid = $this->_field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $text = $entry->{"c{$fieldid}_content"};

            return '';
            //$options = new object();
            //$options->para = false;
            //$str = format_text($text, FORMAT_PLAIN, $options);
        } else {
            return '';
        }
    }

    /**
     * Print the content for browsing the entry
     */
    protected function display_browse($entry, $params = null) {

        $field = $this->_field;
        $fieldid = $field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $contentid = $entry->{"c{$fieldid}_id"};
            $text = $entry->{"c{$fieldid}_content"};
            $format = isset($entry->{"c{$fieldid}_content1"}) ? $entry->{"c{$fieldid}_content1"} : FORMAT_PLAIN;

//            if ($field->is_editor()) {
                $text = file_rewrite_pluginfile_urls($text, 'pluginfile.php', $field->df()->context->id, 'mod_dataform', 'content', $contentid);
//            }

            $options = new object();
            $options->para = false;
            $options->overflowdiv = true;
            $str = format_text($text, $format, $options);
            return $str;
        } else {
            return '';
        }
    }

    /**
     *
     */
    public function pluginfile_patterns() {
        return array("[[{$this->_field->name()}]]");
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = array();
        $patterns["[[$fieldname]]"] = array(true);
        $patterns["[[$fieldname:text]]"] = array(false);
        $patterns["[[$fieldname:wordcount]]"] = array(false);

        return $patterns;
    }

    /**
     * Array of patterns this field supports
     */
    protected function supports_rules() {
        return array(
            self::RULE_REQUIRED
        );
    }
}