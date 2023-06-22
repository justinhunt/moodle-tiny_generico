<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tiny Generico for TinyMCE plugin for Moodle.
 *
 * @package     tiny_generico
 * @copyright   2023 Justin Hunt <justin@poodll.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tiny_generico;

use context;
use editor_tiny\plugin;
use editor_tiny\plugin_with_buttons;
use editor_tiny\plugin_with_menuitems;
use editor_tiny\plugin_with_configuration;

class plugininfo extends plugin implements plugin_with_configuration, plugin_with_buttons, plugin_with_menuitems {

    public static function get_available_buttons(): array {
        return [
            'tiny_generico/plugin',
        ];
    }

    public static function get_available_menuitems(): array {
        return [
            'tiny_generico/plugin',
        ];
    }

    public static function get_plugin_configuration_for_context(
        context $context,
        array $options,
        array $fpoptions,
        ?\editor_tiny\editor $editor = null
    ): array {

        $config = get_config('atto_generico');


        //generico specific
        $templates = get_object_vars(get_config('filter_generico'));

        $instructions = array();
        $keys = array();
        $names = array();
        $variables = array();
        $defaults = array();
        $ends = array();

        //get the no. of templates
        if (!array_key_exists('templatecount', $templates)) {
            $templatecount = 21;
        } else {
            $templatecount = $templates['templatecount'] + 1;
        }
        //put our template into a form thats easy to process in JS
        for ($tempindex = 1; $tempindex < $templatecount; $tempindex++) {
            if (empty($templates['template_' . $tempindex]) &&
                empty($templates['templatescript_' . $tempindex]) &&
                empty($templates['templatestyle_' . $tempindex])
            ) {
                continue;
            }

            //stash the key for this tempalte
            $keys[] = $templates['templatekey_' . $tempindex];

            //stash the name for this template
            $usename = trim($templates['templatename_' . $tempindex]);
            if($usename==''){
                $names[] = $templates['templatekey_' . $tempindex];
            }else{
                $names[]=$usename;
            }

            //instructions
            //stash the instructions for this template
            $instructions[] = rawurlencode($templates['templateinstructions_' . $tempindex]);

            //NB each of the $allvariables contains an array of variables (not a string)
            //there might be duplicates where the variable is used multiple times in a template
            //se we uniqu'ify it. That makes it look complicated. But we are just removing doubles
            $allvariables = self::fetch_variables($templates['template_' . $tempindex] . $templates['templatescript_' . $tempindex] . $templates['datasetvars_' . $tempindex]);
            $uniquevariables = array_unique($allvariables);
            $usevariables = array();

            //we need to reallocate array keys if the array size was changed in unique'ifying it
            //we also take the opportunity to remove user variables, since they aren't needed here.
            //NB DATASET can be referred to without the :
            while (count($uniquevariables) > 0) {
                $tempvar = array_shift($uniquevariables);
                if (strpos($tempvar, 'COURSE:') === false
                    && strpos($tempvar, 'USER:') === false
                    && strpos($tempvar, 'DATASET:') === false
                    && strpos($tempvar, 'URLPARAM:') === false
                    && $tempvar != 'MOODLEPAGEID'
                    && $tempvar != 'WWWROOT'
                    && $tempvar != 'AUTOID'
                    && $tempvar != 'CLOUDPOODLLTOKEN') {
                    $usevariables[] = $tempvar;
                }
            }
            $variables[] = $usevariables;

            //stash the defaults for this template
            //$defaults[] = $templates['templatedefaults_' . $tempindex];
            $defaults[] = self::fetch_filter_properties($templates['templatedefaults_' . $tempindex]);

            $ends[] = $templates['templateend_' . $tempindex];
        }
        if ($config->editoricon) {
            $customicon = atto_generico_custom_icon_url();
        }else{
            $customicon =false;
        }


        //config our array of data
        $params = array();
        $params['customicon']=rawurlencode($customicon);
        $params['keys'] = $keys;
        $params['names'] = $names;
        $params['instructions'] = $instructions;
        $params['variables'] = $variables;
        $params['defaults'] = $defaults;
        $params['ends'] = $ends;

        //If they don't have permission don't show it
        $disabled = false;
        if(!has_capability('tiny/generico:visible', $context) ){
            $disabled=true;
        }

        //add our disabled param
        $params['disabled'] = $disabled;


        return $params;











        return [
            // Your values go here.
            // These will be mapped to a namespaced EditorOption in Tiny.
            'editoricon' => 'TODO Calculate your values here',
        ];
    }



    /**
     * Return an array of variable names
     * @param string template containing @@variable@@ variables
     * @return array of variable names parsed from template string
     */
    private static function fetch_variables($template){
        $matches = array();
        $t = preg_match_all('/@@(.*?)@@/s', $template, $matches);
        if(count($matches)>1){
            return($matches[1]);
        }else{
            return array();
        }
    }

    private static function fetch_filter_properties($propstring){
        //Now we just have our properties string
        //Lets run our regular expression over them
        //string should be property=value,property=value
        //got this regexp from http://stackoverflow.com/questions/168171/regular-expression-for-parsing-name-value-pairs
        $regexpression='/([^=,]*)=("[^"]*"|[^,"]*)/';
        $matches=array();

        //here we match the filter string and split into name array (matches[1]) and value array (matches[2])
        //we then add those to a name value array.
        $itemprops = array();
        if (preg_match_all($regexpression, $propstring,$matches,PREG_PATTERN_ORDER)){
            $propscount = count($matches[1]);
            for ($cnt =0; $cnt < $propscount; $cnt++){
                // echo $matches[1][$cnt] . "=" . $matches[2][$cnt] . " ";
                $newvalue = $matches[2][$cnt];
                //this could be done better, I am sure. WE are removing the quotes from start and end
                //this wil however remove multiple quotes id they exist at start and end. NG really
                $newvalue = trim($newvalue,'"');
                $itemprops[trim($matches[1][$cnt])]=$newvalue;
            }
        }
        return $itemprops;
    }

    /**
     * Returns URL to the stored file via pluginfile.php.
     *
     * @param string $setting
     * @param string $filearea
     * @return string protocol relative URL or null if not present
     */
    private static function get_custom_icon_url() {
        global $CFG;
        $config = get_config(constants::M_COMPONENT);

        $component = constants::M_COMPONENT;
        $itemid = 0;
        $syscontext = \context_system::instance();
        $filearea = constants::M_ICON_FILEAREA;
        $filepath = $config->editoricon;

        $url = \moodle_url::make_file_url("$CFG->wwwroot/pluginfile.php", "/$syscontext->id/$component/$filearea/$itemid".$filepath);
        return $url;
    }
}
