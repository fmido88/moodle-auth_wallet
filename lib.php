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
 * Auth wallet lib.
 *
 * @package     auth_wallet
 * @copyright   2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/authlib.php');

/**
 * Fire up each time require_login() called and redirect non-confirmed users to confirm page.
 * @return void
 */
function auth_wallet_after_require_login() {
    global $USER, $CFG;
    require_once($CFG->dirroot . '/enrol/wallet/locallib.php');
    if (isguestuser() || empty($USER->id)) {
        return;
    }
    $all = get_config('auth_wallet', 'all');
    // Disable redirection in case of another auth plugin.
    if (empty($all) && $USER->auth !== 'wallet') {
        return;
    }

    // Check if first required payment already done.
    $payconfirm = get_user_preferences('auth_wallet_balanceconfirm', false, $USER);
    if ($payconfirm) {
        return;
    }
    $itemid      = optional_param('itemid', '', PARAM_INT);
    $paymentarea = optional_param('paymentarea', '', PARAM_TEXT);
    $component   = optional_param('component', '', PARAM_RAW);
    $value       = optional_param('value', '', PARAM_FLOAT);
    $coupon      = optional_param('coupon', '', PARAM_TEXT);
    $order       = optional_param('order', '', PARAM_RAW);
    $obj         = optional_param('obj', '', PARAM_RAW);
    $s           = optional_param('s', '', PARAM_TEXT);
    $l           = optional_param('logout', '', PARAM_TEXT);
    $returnto    = optional_param('returnto', '', PARAM_TEXT);

    // Disable redirection in case of payment process or confirm page.
    if (!empty($itemid)
        || !empty($paymentarea)
        || !empty($component)
        || !empty($value)
        || !empty($coupon)
        || !empty($order)
        || !empty($s)
        || !empty($l)
        || !empty($returnto)
        ) {

        return;
    }

    $params = [
        's' => $USER->username,
    ];
    if (empty(get_config('auth_wallet', 'emailconfirm'))) {
        $params['p'] = $USER->secret;
    }

    $confirmationurl = new \moodle_url('/auth/wallet/confirm.php', $params);

    redirect($confirmationurl);
}

/**
 * Fire up  each time $CFG load.
 * @return void
 */
function auth_wallet_after_config() {
    global $USER;

    if (isloggedin() && !isguestuser() && !empty($USER->id)) {
        // Check if first required payment already done.
        $payconfirm = get_user_preferences('auth_wallet_balanceconfirm', false, $USER);
        if (!empty($payconfirm) && !empty($USER->confirmed)) {
            return;
        }
        auth_wallet_after_require_login();
    }
}

/**
 * Callback function after updating extra fee value or required balance for validation,
 * if the value is greater than the required balance it display error after set the value to the max allowed value.
 * @return void
 */
function auth_wallet_check_extrafee_validation() {
    $config = get_config('auth_wallet');
    if ($config->criteria === 'balance' && isset($config->extra_fee) && $config->extra_fee > $config->required_balance) {
        set_config('extra_fee', $config->required_balance, 'auth_wallet');
        $error = get_string('extra_fee_error', 'auth_wallet');
        redirect(new moodle_url('/admin/settings.php', ['section' => 'authsettingwallet']), $error, null, 'error');
    }
}
