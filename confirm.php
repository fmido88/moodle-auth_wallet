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
 * Confirm self registered user.
 *
 * @package     auth_wallet
 * @copyright   2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/wallet/locallib.php');
require_once($CFG->dirroot . '/login/lib.php');
require_once($CFG->libdir . '/authlib.php');

$p = optional_param('p', '', PARAM_ALPHANUM);   // Parameter: secret.
$s = optional_param('s', '', PARAM_RAW);        // Parameter: username.
$redirect = core_login_get_return_url();
$logout = optional_param('logout', false, PARAM_BOOL);
if ($logout) {
    require_logout();
    redirect($CFG->wwwroot.'/');
    exit;
}
$emailconfirm = get_config('auth_wallet', 'emailconfirm');

$PAGE->set_url('/auth/wallet/confirm.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');

if (!$auth = signup_get_user_confirmation_authplugin() || $auth->authtype !== 'wallet') {
    throw new moodle_exception('confirmationnotenabled');
}

$authplugin = new auth_plugin_wallet();

if ((!empty($p) && !empty($s))) {
    $usersecret = $p;
    $username   = $s;

    $confirmed = $authplugin->user_confirm($username, $usersecret);
    if ($confirmed == AUTH_CONFIRM_ALREADY) {
        $user = get_complete_user_data('username', $username);
        $PAGE->navbar->add(get_string("alreadyconfirmed"));
        $PAGE->set_title(get_string("alreadyconfirmed"));
        $PAGE->set_heading($COURSE->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->box_start('generalbox centerpara boxwidthnormal boxaligncenter');
        echo "<p>".get_string("alreadyconfirmed")."</p>\n";
        echo $OUTPUT->single_button(core_login_get_return_url(), get_string('courses'));
        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
        exit;

    } else if ($confirmed == AUTH_CONFIRM_OK) {

        // The user has confirmed successfully, let's log them in.
        if (!$user = get_complete_user_data('username', $username)) {
            throw new \moodle_exception('cannotfinduser', '', '', s($username));
        }

        if (!$user->suspended) {
            complete_user_login($user);

            \core\session\manager::apply_concurrent_login_limit($user->id, session_id());

            // Check where to go, $redirect has a higher preference.
            if (!empty($redirect)) {
                if (!empty($SESSION->wantsurl)) {
                    unset($SESSION->wantsurl);
                }
                redirect($redirect);
            }
        }

        $PAGE->navbar->add(get_string("confirmed"));
        $PAGE->set_title(get_string("confirmed"));
        $PAGE->set_heading($COURSE->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->box_start('generalbox centerpara boxwidthnormal boxaligncenter');
        echo "<h3>".get_string("thanks").", ". fullname($USER) . "</h3>\n";
        echo "<p>".get_string("confirmed")."</p>\n";
        echo $OUTPUT->single_button(core_login_get_return_url(), get_string('continue'));
        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
        exit;
    } else if ($confirmed == AUTH_CONFIRM_ERROR) {
        throw new \moodle_exception('invalidconfirmdata');
    }
}

if (!empty($s)) {
    $user = get_complete_user_data('username', $s);
} else {
    global $USER;
    $user = $USER;
}

// Reaching this part of the code means either the user confirmed by email already and wait payment confirmation,
// or confirmation by email is disabled.
if (!empty($user) && is_object($user)) {
    if (empty($user->suspended) && (!empty($user->confirmed) || empty($emailconfirm))) {

        // Prepare redirection url.
        $params = [
            's' => (empty($s)) ? $user->username : $s,
            'p' => $user->secret,
        ];
        $url = new \moodle_url('/auth/wallet/confirm.php', $params);

        // Login the user to enable payment.
        if (!isloggedin() || empty($user->id)) {
            complete_user_login($user);
            if (empty($user->id)) {
                global $USER;
                $user->id = $USER->id;
            }
            \core\session\manager::apply_concurrent_login_limit($user->id, session_id());
        }

        require_login();

        $transactions = new enrol_wallet\transactions;

        $balance       = $transactions->get_user_balance($user->id);
        $confirmmethod = get_config('auth_wallet', 'criteria');
        $required      = get_config('auth_wallet', 'required_balance');
        $fee           = get_config('auth_wallet', 'required_fee');

        if ($confirmmethod === 'balance' && $balance >= $required) {
            set_user_preference('auth_wallet_balanceconfirm', true, $user);
            redirect($url);
        } else if ($confirmmethod === 'fee' && $balance >= $fee) {
            $transactions->debit($user->id, $fee, 'New user fee');
            set_user_preference('auth_wallet_balanceconfirm', true, $user);
            redirect($url);
        } else {
            $PAGE->set_title($COURSE->fullname);
            $PAGE->set_heading($COURSE->fullname);
            echo $OUTPUT->header();
            echo $OUTPUT->box_start('generalbox centerpara boxwidthnormal boxaligncenter');
            $a = [
                'balance'  => $balance,
                'required' => $required,
                'rest'     => $required - $balance,
                'currency' => get_config('enrol_wallet', 'currency'),
                'name'     => fullname($user),
            ];
            echo get_string('payment_required', 'auth_wallet', $a);
            echo enrol_wallet_display_topup_options();
            $url = new \moodle_url('/auth/wallet/confirm.php', ['logout' => 1]);
            echo $OUTPUT->single_button($url, get_string('logout'));
            echo $OUTPUT->box_end();
            echo $OUTPUT->footer();
            exit;
        }
    }
} else {
    throw new \moodle_exception("errorwhenconfirming");
}
