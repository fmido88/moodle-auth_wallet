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

use enrol_wallet\local\config;
use enrol_wallet\local\wallet\balance;
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

        if (empty($CFG->deleteunconfirmed)) {
            mtrace('Configuration deleteunconfirmed set to never ...');
            return;
        }

        $config = config::make();

        $gift      = $config->newusergift;
        $giftvalue = $config->newusergiftvalue;

        $intval = $CFG->deleteunconfirmed * 60 * 60;

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $trace = new \text_progress_trace();
        $trace->output('Task started...');

        $select = 'confirmed != :confirmed AND timecreated < :timetosearch';
        $params = ['confirmed' => 1, 'timetosearch' => time() - $intval];
        $records = $DB->get_records_select('auth_wallet_confirm', $select, $params);

        $trace->output(count($records) . ' users found to delete.');
        foreach ($records as $record) {
            // Double check that the user is confirmed before delete.
            if ($user = get_complete_user_data('id', $record->userid)) {
                if (auth_wallet_is_confirmed($user)) {
                    $trace->output('User with id ' . $user->id . ' already confirmed and skipped...');
                    continue;
                }
                $helper = new balance($user->id);
                $balance = $helper->get_total_balance();
                $free = $helper->get_total_free();
                if (!empty($balance) && $balance > $free) {
                    if (!$gift || ($gift + $balance > $giftvalue)) {
                        $trace->output('User with id '. $user->id . ' has a balance of '. $balance .', so not deleted.');
                        continue;
                    }
                }

                user_delete_user($user);
            }

            $DB->delete_records('auth_wallet_confirm', ['userid' => $record->userid]);
            $trace->output('user with id ' . $record->userid . ' has been deleted...');
        }

        $trace->output('Finished.');
        $trace->finished();
    }

}
