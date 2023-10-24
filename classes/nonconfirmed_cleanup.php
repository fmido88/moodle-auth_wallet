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
 * Clean up non-confirmed users..
 *
 * @package    auth_wallet
 * @copyright  2023 Mo Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_wallet\task;

/**
 * Clean up non-confirmed users.
 */
class nonconfirmed_cleanup extends \core\task\scheduled_task {

    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('cleanup_nonconfirmed', 'auth_wallet');
    }

    /**
     * Run task for cleaning up users.
     */
    public function execute() {
        global $DB, $CFG;
        require_once("$CFG->dirroot/user/lib.php");
        require_once("$CFG->dirroot/auth/wallet/lib.php");

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $trace = new \text_progress_trace();
        $trace->output('Task started...');

        $select = 'confirmed != 1 AND timecreated < :timetosearch';
        $params = ['timetosearch' => time() - 4 * DAYSECS];
        $users = $DB->get_records_select('auth_wallet_confirm', $select, $params);

        $trace->output(count($users) . ' users found to delete.');
        foreach ($users as $user) {
            if (auth_wallet_is_confirmed($user)) {
                $trace->output('User with id ' . $user->id . ' already confirmed and skipped...');
                continue;
            }
            $user = get_complete_user_data('id', $user->id);
            user_delete_user($user);
            $DB->delete_records('auth_wallet_confirm', ['id' => $user->id]);
            $trace->output('user with id ' . $user->id . ' has been deleted.');
        }

        $trace->output('Finished.');
        $trace->finished();
    }

}
