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
 * Plugin administration pages are defined here.
 *
 * @package     tiny_generico
 * @category    admin
 * @copyright   2023 Justin Hunt <justin@poodll.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tiny_generico\constants;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('tiny_generico_settings', new lang_string('pluginname', 'tiny_generico'));

    if ($ADMIN->fulltree) {
        //A customizable editor icon for Generico
        $name = constants::M_COMPONENT . '/editoricon';
        $title =get_string('editoricon', constants::M_COMPONENT );
        $description = get_string('editoricon_desc', constants::M_COMPONENT );
        $settings->add(new admin_setting_configstoredfile($name, $title, $description, constants::M_ICON_FILEAREA));
    }
}