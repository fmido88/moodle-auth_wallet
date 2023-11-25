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
require_once(__DIR__.'/auth.php');
require_once(__DIR__.'/lib.php');
require_once($CFG->dirroot . '/enrol/wallet/locallib.php');
require_once($CFG->dirroot . '/login/lib.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot.'/user/editlib.php');

global $SESSION;

$p        = optional_param('p', '', PARAM_ALPHANUM);   // Parameter: secret.
$s        = optional_param('s', '', PARAM_RAW);        // Parameter: username.
$data     = optional_param('data', '', PARAM_RAW);
$redirect = optional_param('redirect', '', PARAM_URL);
if (empty($redirect)) {
    if (isset($SESSION->wantsurl)) {
        $redirect = (new moodle_url($SESSION->wantsurl))->out(false);
    } else if ($base = get_user_preferences('auth_wallet_wantsurl', false)) {
        $redirect = (new moodle_url($base))->out(false);
    } else {
        $redirect = core_login_get_return_url();
    }
}

// Logout button pressed.
$logout = optional_param('logout', false, PARAM_BOOL);
if ($logout) {
    require_logout();
    redirect($CFG->wwwroot.'/');
    exit;
}

$emailconfirm = get_config('auth_wallet', 'emailconfirm');
$all = get_config('auth_wallet', 'all');

$PAGE->set_url('/auth/wallet/confirm.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');

if (empty($all)) {
    if (!$auth = signup_get_user_confirmation_authplugin() || $auth->authtype !== 'wallet') {
        throw new moodle_exception('confirmationnotenabled');
    }
}

$authplugin = new auth_plugin_wallet();

if ((!empty($p) && !empty($s)) || !empty($data)) {

    if (!empty($data)) {
        $dataelements = explode('/', $data, 2); // Stop after 1st slash. Rest is username.
        $usersecret = $dataelements[0];
        $username   = $dataelements[1];
    } else {
        $usersecret = $p;
        $username   = $s;
    }

    $user = get_complete_user_data('username', $username);
    if (!$user || isguestuser($user)) {
        throw new \moodle_exception('cannotfinduser', '', '', s($username));
    }
    $confirmed = $authplugin->user_confirm($username, $usersecret);
    if ($confirmed == AUTH_CONFIRM_ALREADY) {

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
        if (empty($user->suspended)) {
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
        debugging('Confirmation Error.', DEBUG_NONE);
        redirect(new moodle_url('/login/index.php'), get_string('invalidconfirmdata'), null, 'error');
    }
}

if (!empty($s)) {
    $user = get_complete_user_data('username', $s);
} else {
    global $USER;
    $user = fullclone($USER);
}

if (!empty($user) && is_object($user) && !isguestuser($user)) {

    $payconfirm = auth_wallet_is_confirmed($user);

    if (empty($user->suspended)) {

        if (!empty($user->confirmed)
            || empty($emailconfirm)
            || $user->auth != 'wallet') {
            // Reaching this part of the code means either the user confirmed by email already and wait payment confirmation,
            // or confirmation by email is disabled.

            // Prepare redirection url.
            if (!empty($user->confirmed)) {
                $url = $redirect;
            } else {
                $params = [
                    's' => (empty($s)) ? $user->username : $s,
                ];
                if (empty($user->secret)) {
                    $user->secret = random_string(15);
                    $DB->set_field('user', 'secret', $user->secret, ['id' => $user->id]);
                }
                $params['p'] = $user->secret;
                $params['redirect'] = $redirect;
                $url = new \moodle_url('/auth/wallet/confirm.php', $params);
            }

            // Login the user to enable payment.
            if (!isloggedin() || empty($user->id)) {
                $user = complete_user_login($user);

                \core\session\manager::apply_concurrent_login_limit($user->id, session_id());
            }

            require_login();

            if (!empty($payconfirm)) {
                redirect($url);
            } else {
                // Display the payment page.
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
                    'extrafee' => !empty($extrafee) ? get_string('extrafeerequired', 'auth_wallet', $extrafee) : '',
                ];

                $confirmmethod = get_config('auth_wallet', 'criteria');
                if ($confirmmethod === 'balance') {
                    echo get_string('payment_required', 'auth_wallet', $a);
                    echo enrol_wallet_display_topup_options();
                } else if ($confirmmethod === 'fee') {
                    echo get_string('fee_required', 'auth_wallet', $a);
                    echo enrol_wallet_display_topup_options();
                } else {
                    echo $OUTPUT->notification(get_string('settingerror', 'auth_wallet'), 'error');
                }

                $url = new \moodle_url('/auth/wallet/confirm.php', ['logout' => 1]);
                echo $OUTPUT->single_button($url, get_string('logout'));
                echo $OUTPUT->box_end();
                echo $OUTPUT->footer();
                exit;
            }
        }
    }
}

// Not recognized user, suspended user or not confirmed by email yet.
redirect(new moodle_url('/login/index.php'), get_string('errorwhenconfirming'), null, 'error');
