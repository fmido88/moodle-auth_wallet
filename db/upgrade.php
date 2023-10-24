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
 * This file keeps track of upgrades to the wallet authentication plugin
 *
 * @package    auth_wallet
 * @copyright  2023 Mo Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrading database.
 *
 * @param int $oldversion the old version of this plugin.
 * @return bool
 */
function xmldb_auth_wallet_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2023081508) {

        // Define table enrol_wallet_awards and its fields.
        $table = new xmldb_table('auth_wallet_confirm');
        $table->add_field('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, true);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, false);
        $table->add_field('confirmed', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, false, 0);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, false);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, 10, null, null, false);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Conditionally launch create table responsive.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2023081508, 'auth', 'wallet');
    }

    return true;
}
