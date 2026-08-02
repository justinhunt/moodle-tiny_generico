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

        global $COURSE, $USER;

        $params = [];

        if (!$context) {
            $context = \context_course::instance($COURSE->id);
        }

        $disabled = false;
        // If they don't have permission don't show it.
        if (!has_capability('tiny/generico:visible', $context)) {
            $disabled = true;
        }
        $params['disabled'] = $disabled;

        // Only offer templates whose filter is actually active in this context,
        // so an inserted tag is never dead text.
        $activefilters = filter_get_active_in_context($context);

        // Classic Generico templates (config-based).
        $widgets = [];
        if (array_key_exists('generico', $activefilters)) {
            $genericoconfig = get_config('filter_generico');
            if (!empty($genericoconfig->version)) {
                $widgets = self::get_widgets_for_js();
            }
        }

        // Generico Two templates (DB-based).
        if (array_key_exists('genericotwo', $activefilters)
                && !empty(get_config('filter_genericotwo', 'version'))) {
            $g2widgets = self::get_genericotwo_widgets_for_js($context);
            // When both filters contribute, flag the first G2 widget so the
            // selector template renders an <hr/> between the two groups.
            if (!empty($widgets) && !empty($g2widgets)) {
                $g2widgets[0]->showseparator = true;
            }
            $widgets = array_merge($widgets, $g2widgets);
        }

        $params['widgets'] = $widgets;

        return ['generico' => $params];
    }

    /**
     * Return the js params required for this module.
     *
     * @return array of additional params to pass to javascript init function for this module.
     */
    private static function get_widgets_for_js() {
        // Init our return value.
        $widgets = [];
        // Generico specific.
        $templates = get_object_vars(get_config('filter_generico'));

        // Get the no. of templates.
        if (!array_key_exists('templatecount', $templates)) {
            $templatecount = \filter_generico\generico_utils::FILTER_GENERICO_TEMPLATE_COUNT + 1;
        } else {
            $templatecount = $templates['templatecount'] + 1;
        }
        // Put our template into a form thats easy to process in JS.
        for ($tempindex = 1; $tempindex < $templatecount; $tempindex++) {
            if (!empty($templates['templatehidden_' . $tempindex])) {
                continue;
            }
            if (
                empty($templates['template_' . $tempindex]) &&
                empty($templates['templatescript_' . $tempindex]) &&
                empty($templates['templatestyle_' . $tempindex])
            ) {
                continue;
            }

            $widget = new \stdClass();

            // Stash the key and name for this template.
            $widget->key = $templates['templatekey_' . $tempindex];
            $usename = trim($templates['templatename_' . $tempindex]);
            if ($usename == '') {
                $widget->name = $templates['templatekey_' . $tempindex];
            } else {
                $widget->name = $usename;
            }

            // Instructions.
            $widget->instructions = $templates['templateinstructions_' . $tempindex];

            // Stash the defaults for this template.
            $widgetdefaults = self::fetch_widget_properties($templates['templatedefaults_' . $tempindex]);

            // NB each of the $allvariables contains an array of variables (not a string)
            // there might be duplicates where the variable is used multiple times in a template
            // so we uniqu'ify it. That makes it look complicated. But we are just removing doubles.
            $allvariables = self::fetch_widget_variables($templates['template_' . $tempindex] .
                $templates['templatescript_' . $tempindex] . $templates['datasetvars_' . $tempindex]);
            $uniquevariables = array_unique($allvariables);
            $usevariables = [];

            // We need to reallocate array keys if the array size was changed in unique'ifying it.
            // We also take the opportunity to remove user variables, since they aren't needed here.
            // NB DATASET can be referred to without the :
            while (count($uniquevariables) > 0) {
                $tempvar = array_shift($uniquevariables);
                if (
                    strpos($tempvar, 'COURSE:') === false
                    && strpos($tempvar, 'USER:') === false
                    && strpos($tempvar, 'DATASET:') === false
                    && strpos($tempvar, 'URLPARAM:') === false
                    && strpos($tempvar, 'STRING:') === false
                    && $tempvar != 'MOODLEPAGEID'
                    && $tempvar != 'WWWROOT'
                    && $tempvar != 'AUTOID'
                    && $tempvar != 'CLOUDPOODLLTOKEN'
                ) {
                    $usevariables[] = $tempvar;
                }
            }
            $widget->variables = self::build_variables($usevariables, $widgetdefaults);

            // Set the template index so we can find it easily later.
            $widget->templateindex = $tempindex;

            $widget->end = !empty($templates['templateend_' . $tempindex]) ? $templates['templateend_' . $tempindex] : false;
            $widget->filtertag = 'GENERICO';

            $widgets[] = $widget;
        }

        return $widgets;
    }

    private static function get_genericotwo_widgets_for_js(\context $context): array {
        global $DB;

        $widgets = [];
        $templates = $DB->get_records('filter_genericotwo_templates', null, 'name ASC');

        foreach ($templates as $template) {
            // A templatewith no content and JS renders nothing - don't offer it.
            if (empty($template->content) && empty($template->jscontent)) {
                continue;
            }
            // Respect the template's allowedcontexts / allowedcontextids restrictions.
            if (!\filter_genericotwo\utils::is_context_allowed($context, $template)) {
                continue;
            }

            $widget = new \stdClass();
            $widget->key = $template->templatekey;
            $usename = trim((string) $template->name);
            $widget->name = $usename !== '' ? $usename : $template->templatekey;
            $widget->instructions = $template->instructions;

            // Defaults use the same property=value,property=value format as classic Generico.
            $widgetdefaults = self::fetch_widget_properties((string) $template->variabledefaults);

            $allvariables = self::fetch_genericotwo_variables(
                $template->content . ' ' . $template->jscontent . ' ' . $template->datasetvars);
            $widget->variables = self::build_variables($allvariables, $widgetdefaults);

            // Prefix keeps these from colliding with classic Generico's numeric indexes.
            $widget->templateindex = 'g2_' . $template->id;
            $widget->end = !empty($template->templateend);
            $widget->filtertag = 'G2';
            $widgets[] = $widget;
        }

        return $widgets;
    }

    private static function fetch_genericotwo_variables(string $template): array {
        $matches = [];
        // Collect all {{...}} tags - this over-matches (sections, system vars), the loop below filters those out.
        preg_match_all('/\{\{(.*?)\}\}/s', $template, $matches);

        $variables = [];
        foreach (array_unique($matches[1]) as $tempvar) {
            $tempvar = trim($tempvar);
            if ($tempvar === '') {
                continue;
            }
            // Skip mustache section/close/inverted/partial/comment/unescaped tokens
            // and the implicit iterator.
            if (in_array($tempvar[0], ['#', '/', '^', '>', '!', '{', '&', '.'])) {
                continue;
            }
            // Skip variables the filter resolves itself.
            if (strpos($tempvar, 'COURSE:') === 0
                || strpos($tempvar, 'USER:') === 0
                || strpos($tempvar, 'DATASET') === 0
                || strpos($tempvar, 'URLPARAM:') === 0
                || strpos($tempvar, 'STRING:') === 0
                || in_array($tempvar, ['MOODLEPAGEID', 'WWWROOT', 'AUTOID',
                    'CLOUDPOODLLTOKEN', 'CSSLINK', 'CSSCUSTOM', 'uniqid'])) {
                continue;
            }
            $variables[] = $tempvar;
        }
        return array_values($variables);
    }

    /**
     * Return an array of variable names
     *
     * @param string template containing @@variable@@ variables
     * @return array of variable names parsed from template string
     */
    private static function fetch_widget_variables($template) {
        $matches = [];
        $t = preg_match_all('/@@(.*?)@@/s', $template, $matches);
        if (count($matches) > 1) {
            return ($matches[1]);
        } else {
            return [];
        }
    }

    private static function fetch_widget_properties($propstring) {
        // Now we just have our properties string.
        // Lets run our regular expression over them.
        // String should be property=value,property=value.
        // Got this regexp from http://stackoverflow.com/questions/168171/regular-expression-for-parsing-name-value-pairs.
        $regexpression = '/([^=,]*)=("[^"]*"|[^,"]*)/';
        $matches = [];

        // Here we match the filter string and split into name array (matches[1]) and value array (matches[2]).
        // We then add those to a name value array.
        $itemprops = [];
        if (preg_match_all($regexpression, $propstring, $matches, PREG_PATTERN_ORDER)) {
            $propscount = count($matches[1]);
            for ($cnt = 0; $cnt < $propscount; $cnt++) {
                // echo $matches[1][$cnt] . "=" . $matches[2][$cnt] . " ";
                $newvalue = $matches[2][$cnt];
                // this could be done better, I am sure. WE are removing the quotes from start and end
                // this wil however remove multiple quotes id they exist at start and end. NG really
                $newvalue = trim($newvalue, '"');
                $itemprops[trim($matches[1][$cnt])] = $newvalue;
            }
        }
        return $itemprops;
    }

    private static function build_variables(array $usevariables, array $widgetdefaults): array {
        $variables = [];
        foreach ($usevariables as $var) {
            $default = isset($widgetdefaults[$var]) ? $widgetdefaults[$var] : '';
            $isarray = false;
            if (strpos($default, '|')) {
                $default = explode('|', $default);
                $isarray = true;
            }
            $displayname = str_replace('_', ' ', $var);
            $displayname = str_replace('-', ' ', $displayname);
            $displayname = ucwords($displayname);
            $variables[] = ['key' => $var, 'default' => $default,
                'isarray' => $isarray, 'displayname' => $displayname];
        }
        return $variables;
    }
}
