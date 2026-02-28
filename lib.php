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
 * Add user bulk action for confirming users.
 * @return array[action_link]
 */
function auth_wallet_bulk_user_actions() {
    $url = new moodle_url('/auth/wallet/bulkconfirm.php');
    $label = get_string('bulk_user_confirm', 'auth_wallet');
    return [
        'auth_wallet_bulk_confirm' => new action_link($url, $label),
    ];
}
/**
 * Fire up each time require_login() called and redirect non-confirmed users to confirm page.
 * @return void
 */
function auth_wallet_after_require_login() {
    global $USER, $CFG, $FULLME, $SESSION;
    require_once($CFG->dirroot . '/enrol/wallet/locallib.php');
    require_once($CFG->dirroot.'/login/lib.php');
    if (!isloggedin() || isguestuser() || is_siteadmin()) {
        return;
    }

    if (AJAX_SCRIPT || CLI_SCRIPT || WS_SERVER) {
        return;
    }

    if (!empty($SESSION->auth_wallet_confirmed)) {
        return;
    }

    if (file_exists($CFG->dirroot.'/auth/parent/lib.php')) {
        require_once($CFG->dirroot.'/auth/parent/lib.php');
        if (auth_parent_is_parent($USER)) {
            return;
        }
    }

    $all = get_config('auth_wallet', 'all');
    // Disable redirection in case of another auth plugin.
    if (empty($all) && $USER->auth !== 'wallet') {
        return;
    }

    // Check if first required payment already done.
    $payconfirm = auth_wallet_is_confirmed($USER);
    if (!empty($payconfirm)) {
        return;
    }

    $currentpage = new moodle_url(qualified_me());
    if (!auth_wallet_should_redirect($currentpage)) {
        return;
    }

    $params = [
        's' => $USER->username,
    ];
    if (empty(get_config('auth_wallet', 'emailconfirm')) || !empty($USER->confirmed)) {
        // If no need for email confirmation or the user is already confirmed this way.
        if (empty($USER->secret)) {
            $USER->secret = random_string(15);
            $DB->set_field('user', 'secret', $USER->secret, ['id' => $USER->id]);
        }
        $params['p'] = $USER->secret;
        $params['redirect'] = qualified_me();
        $params['postdata'] = base64_decode(json_encode($_POST));
    }

    $confirmationurl = new moodle_url('/auth/wallet/confirm.php', $params);
    redirect($confirmationurl);
}

/**
 * Should the passed url directed or not.
 * @param moodle_url $url
 * @return bool
 */
function auth_wallet_should_redirect(moodle_url $url) {
    $paths = [
        'user/view.php', // Any editing or viewing for profile pages.
        'user/profile.php',
        'user/edit.php',
        'user/editadvanced.php',
        'auth/wallet/confirm.php', // Confirmation page.
        'payment/gateway', // Any payment gateway page for payments.
        'login', // Logging out or logging in pages.
        'enrol/wallet', // Enrol wallet action pages.
        'blocks/vc', // Vodafone cash & instapay block.
    ];

    if (!$url->is_local_url()) {
        // Any non local pages.
        return false;
    }

    foreach ($paths as $path) {
        if (stripos($url->get_path(), $path) !== false
        || stripos($path, $url->get_path()) !== false) {
            return false;
        }
    }

    return true;
}

/**
 * Checks if the user is confirmed by auth_wallet plugin or not.
 * @param object $user
 * @return bool
 */
function auth_wallet_is_confirmed($user) {
    global $DB, $SESSION;
    if (!empty($SESSION->auth_wallet_confirmed)) {
        return true;
    }
    // Check if the user not signed up using this auth plugin.
    $all = get_config('auth_wallet', 'all');
    if (!$all && $user->auth != 'wallet') {
        $SESSION->auth_wallet_confirmed = true;
        return true;
    }

    $payconfirm = get_user_preferences('auth_wallet_balanceconfirm', false, $user);
    if ($payconfirm) {
        auth_wallet_set_confirmed($user);
        $SESSION->auth_wallet_confirmed = true;
        return true;
    }

    $confirmrecord = $DB->record_exists('auth_wallet_confirm', ['userid' => $user->id, 'confirmed' => 1]);

    if (!$confirmrecord) {
        if (auth_wallet_check_conditions($user)) {
            auth_wallet_set_confirmed($user);
            $SESSION->auth_wallet_confirmed = true;
            return true;
        }
    } else {
        $SESSION->auth_wallet_confirmed = true;
    }

    return $confirmrecord;
}

/**
 * Check the conditions for confirming the user.
 * @param object $user
 * @return bool
 */
function auth_wallet_check_conditions($user) {

    $config = get_config('auth_wallet');
    $all = $config->all;

    if (empty($all) && $user->auth !== 'wallet') {
        return true;
    }

    if (empty($user->confirmed)) {
        return false;
    }

    $required = $config->required_balance;
    $op = new enrol_wallet\local\wallet\balance_op($user->id);
    $balance  = $op->get_total_balance();
    $method   = $config->criteria;
    $fee      = $config->required_fee;
    $extrafee = $config->extra_fee;

    // Check if the user balance is sufficient.
    if ($method == 'balance' && $balance >= $required && empty($extrafee)) {
        return true;
    } else if ($method == 'balance' && $balance >= $required) {
        $op->debit($extrafee, $op::D_AUTH_EXTRA_FEE);
        return true;
    } else if ($method === 'fee' && $balance >= $fee) {
        $op->debit($fee, $op::D_AUTH_FEE);
        return true;
    }

    return false;
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

/**
 * Set the user as payment confirmed.
 * @param object $user
 * @return void
 */
function auth_wallet_set_confirmed($user) {
    global $DB, $CFG, $SESSION;
    require_once($CFG->dirroot.'/user/editlib.php');
    $DB->set_field("user", "confirmed", 1, ["id" => $user->id]);
    set_user_preference('auth_wallet_balanceconfirm', true, $user);
    useredit_update_user_preference($user);
    $now = time();
    $recorddata = [
        'userid' => $user->id,
        'confirmed' => 1,
        'timemodified' => $now,
    ];
    if ($confirmrecord = $DB->get_record('auth_wallet_confirm', ['userid' => $user->id])) {
        $recorddata['id'] = $confirmrecord->id;
        $DB->update_record('auth_wallet_confirm', (object)$recorddata);
    } else {
        $recorddata['timecreated'] = $now;
        $DB->insert_record('auth_wallet_confirm', (object)$recorddata);
    }
    $SESSION->auth_wallet_confirmed = true;
}
