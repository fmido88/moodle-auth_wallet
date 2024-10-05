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

namespace auth_wallet;

use core_user\hook\extend_bulk_user_actions;
/**
 * Class bulk_confirm
 *
 * @package    auth_wallet
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callbacks {
    /**
     * Add bulk user action.
     * @param \core_user\hook\extend_bulk_user_actions $hook
     * @return void
     */
    public static function bulk_user_actions(extend_bulk_user_actions $hook) {

        $url = new \moodle_url('/auth/wallet/bulkconfirm.php');
        $label = get_string('bulk_user_confirm', 'auth_wallet');

        $hook->add_action('auth_wallet_bulk_confirm', new \action_link($url, $label));
    }
}
