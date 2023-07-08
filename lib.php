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
    global $USER, $PAGE, $OUTPUT, $CFG, $wallet;
    require_once($CFG->dirroot . '/enrol/wallet/locallib.php');
    // Disable redirection in case of another auth plugin.
    if ($USER->auth !== 'wallet' || !empty($wallet)) {
        return;
    }

    $itemid = optional_param('itemid', '', PARAM_INT);
    $paymentarea = optional_param('paymentarea', '', PARAM_TEXT);
    $component = optional_param('component', '', PARAM_RAW);
    $value = optional_param('value', '', PARAM_FLOAT);
    $coupon = optional_param('coupon', '', PARAM_TEXT);
    // Disable redirection in case of payment process.
    if (!empty($itemid) || !empty($paymentarea) || !empty($component) || !empty($value) || !empty($coupon)) {
        return;
    }

    // Check if first required payment already done.
    $payconfirm = get_user_preferences('auth_wallet_balanceconfirm');

    if ($payconfirm && !empty($USER->confirmed)) {
        return;
    }

    if (!empty($PAGE->url) && (
        strpos('enrol/wallet/extra/topup.php', $PAGE->url) !== false
        || strpos('auth/wallet/confirm.php', $PAGE->url) !== false
        || strpos('payment/gateway', $PAGE->url) !== false
        )) {
        return;
    }

    $params = [
        's' => $USER->username,
    ];
    $confirmationurl = new \moodle_url('/auth/wallet/confirm.php', $params);
    redirect($confirmationurl);
}

/**
 * Fire up  each time $CFG load.
 * @return void
 */
function auth_wallet_after_config() {
    global $wallet;
    if (isloggedin() && !isguestuser() && empty($wallet)) {
        auth_wallet_after_require_login();
    }
}
