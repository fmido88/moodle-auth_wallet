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
 * Authentication class for wallet is defined here.
 *
 * @package     auth_wallet
 * @copyright   2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/authlib.php');
use enrol_wallet\transactions;

// For further information about authentication plugins please read
// https://docs.moodle.org/dev/Authentication_plugins.
//
// The base class auth_plugin_base is located at /lib/authlib.php.
// Override functions as needed.

/**
 * Authentication class for wallet.
 */
class auth_plugin_wallet extends auth_plugin_base {

    /**
     * Set the properties of the instance.
     */
    public function __construct() {
        $this->authtype = 'wallet';
        $this->config = get_config('auth_wallet');
    }

    /**
     * Returns true if the username and password work and false if they are
     * wrong or don't exist.
     *
     * @param string $username The username.
     * @param string $password The password.
     * @return bool Authentication success or failure.
     */
    public function user_login($username, $password) {
        global $CFG, $DB;

        // Validate the login by using the Moodle user table.
        // Remove if a different authentication method is desired.
        $user = $DB->get_record('user', array('username' => $username, 'mnethostid' => $CFG->mnet_localhost_id));

        // User does not exist.
        if (!$user) {
            return false;
        }

        $validate = validate_internal_user_password($user, $password);
        if (!$validate) {
            return false;
        }

        return true;
    }

    /**
     * Sign up a new user ready for confirmation.
     * Password is passed in plaintext.
     * @param $user: new user object
     * @param $notify: print notice with link and terminate
     * @throws \moodle_exception
     * @return bool|void
     */
    public function user_signup($user, $notify = true) {
        global $CFG, $DB, $SESSION;
        require_once($CFG->dirroot.'/user/profile/lib.php');
        require_once($CFG->dirroot.'/user/lib.php');

        $params = [
            'p' => $user->secret,
            's' => $user->username,
        ];
        $confirmationurl = new \moodle_url('/auth/wallet/confirm.php', $params);

        $plainpassword = $user->password;
        $user->password = hash_internal_user_password($user->password);
        if (empty($user->calendartype)) {
            $user->calendartype = $CFG->calendartype;
        }

        $user->id = user_create_user($user, false, false);

        user_add_password_history($user->id, $plainpassword);

        // Save any custom profile field information.
        profile_save_data($user);

        // Save wantsurl against user's profile, so we can return them there upon confirmation.
        if (!empty($SESSION->wantsurl)) {
            set_user_preference('auth_wallet_wantsurl', $SESSION->wantsurl, $user);
        }

        // Trigger event.
        \core\event\user_created::create_from_userid($user->id)->trigger();

        // If email confirmation enabled, send the email with the confirmation link.
        if (!empty($this->config->emailconfirm)) {
            if (!send_confirmation_email($user, $confirmationurl)) {
                throw new \moodle_exception('auth_walletnoemail', 'auth_wallet');
            }

            if ($notify) {
                global $CFG, $PAGE, $OUTPUT;
                $emailconfirm = get_string('emailconfirm');
                $PAGE->navbar->add($emailconfirm);
                $PAGE->set_title($emailconfirm);
                $PAGE->set_heading($PAGE->course->fullname);
                echo $OUTPUT->header();
                notice(get_string('emailconfirmsent', '', $user->email), "$CFG->wwwroot/index.php");
            } else {
                return true;
            }

        } else { // Redirect to confirm.
            redirect($confirmationurl);
        }

        if ($notify && !empty($this->config->emailconfirm)) {
            global $CFG, $PAGE, $OUTPUT;
            $emailconfirm = get_string('emailconfirm');
            $PAGE->navbar->add($emailconfirm);
            $PAGE->set_title($emailconfirm);
            $PAGE->set_heading($PAGE->course->fullname);
            echo $OUTPUT->header();
            notice(get_string('emailconfirmsent', '', $user->email), "$CFG->wwwroot/index.php");
        } else {
            return true;
        }
    }

    /**
     * Confirm the new user as registered.
     *
     * @param string $username
     * @param string $confirmsecret
     * @return int
     */
    public function user_confirm($username, $confirmsecret) {
        global $DB, $SESSION;
        $user = get_complete_user_data('username', $username);

        if (!empty($user)) {
            $paycofirm = get_user_preferences('auth_wallet_balanceconfirm', false, $user);

            if ($user->auth != $this->authtype) {
                return AUTH_CONFIRM_ERROR;

            } else if ($user->secret === $confirmsecret && $user->confirmed && !empty($paycofirm)) {
                return AUTH_CONFIRM_ALREADY;

            } else if ($user->secret === $confirmsecret) {   // They have provided the secret key to get in.

                $DB->set_field("user", "confirmed", 1, array("id" => $user->id));

                $required = $this->config->required_balance;
                $balance = transactions::get_user_balance($user->id);
                $method = $this->config->criteria;
                $fee = $this->config->required_fee;

                // Check if the user balance is sufficient.
                if ($method == 'balance' && $balance < $required) {
                    set_user_preference('auth_wallet_balanceconfirm', false, $user);
                    return AUTH_CONFIRM_FAIL;
                }

                // Check if the method depend on paying a confirm fee and not confirmed yet.
                if ($method == 'fee' && empty($paycofirm)) {
                    // Check if there already enough balance for paying the fee.
                    if ($method == 'fee' && $balance >= $fee) {
                        transactions::debit($user->id, $fee);
                    } else {
                        set_user_preference('auth_wallet_balanceconfirm', false, $user);
                        return AUTH_CONFIRM_FAIL;
                    }
                }

                set_user_preference('auth_wallet_balanceconfirm', true, $user);

                if ($wantsurl = get_user_preferences('auth_wallet_wantsurl', false, $user)) {
                    // Ensure user gets returned to page they were trying to access before signing up.
                    $SESSION->wantsurl = $wantsurl;
                    unset_user_preference('auth_wallet_wantsurl', $user);
                }

                return AUTH_CONFIRM_OK;
            } else {
                return AUTH_CONFIRM_ERROR;
            }
        } else {
            return AUTH_CONFIRM_ERROR;
        }
    }

    /**
     * Post authentication hook.
     * This method is called from authenticate_user_login() for all enabled auth plugins.
     *
     * @param stdClass $user: user object, later used for $USER
     * @param string $username: (with system magic quotes)
     * @param string $password: plain text password (with system magic quotes)
     */
    public function user_authenticated_hook(&$user, $username, $password) {
        $payconfirm = get_user_preferences('auth_wallet_balanceconfirm', null, $user);

        if ((empty($user->confirmed) || empty($payconfirm)) && $user->auth === 'wallet') {
            if (empty($this->config->emailconfirm)) {
                $params = [
                    's' => $user->username,
                    'p' => $user->secret,
                ];
                $confirmationurl = new \moodle_url('/auth/wallet/confirm.php', $params);
                redirect($confirmationurl);
            }
        }
    }

    /**
     * Returns true if this authentication plugin can change the user's password.
     *
     * @return bool
     */
    public function can_change_password() {
        return true;
    }

    /**
     * Returns true if this authentication plugin can edit the users'profile.
     *
     * @return bool
     */
    public function can_edit_profile() {
        return true;
    }

    /**
     * Returns true if this authentication plugin is "internal".
     *
     * Internal plugins use password hashes from Moodle user table for authentication.
     *
     * @return bool
     */
    public function is_internal() {
        return true;
    }

    /**
     * Indicates if password hashes should be stored in local moodle database.
     *
     * @return bool True means password hash stored in user table, false means flag 'not_cached' stored there instead.
     */
    public function prevent_local_passwords() {
        return false;
    }

    /**
     * Indicates if moodle should automatically update internal user
     * records with data from external sources using the information
     * from get_userinfo() method.
     *
     * @return bool True means automatically copy data from ext to user table.
     */
    public function is_synchronised_with_external() {
        return false;
    }

    /**
     * Returns true if plugin allows resetting of internal password.
     *
     * @return bool.
     */
    public function can_reset_password() {
        return true;
    }

    /**
     * Returns true if plugin allows signup and user creation.
     *
     * @return bool
     */
    public function can_signup() {
        return true;
    }

    /**
     * Returns true if plugin allows confirming of new users.
     *
     * @return bool
     */
    public function can_confirm() {
        return true;
    }

    /**
     * Returns whether or not this authentication plugin can be manually set
     * for users, for example, when bulk uploading users.
     *
     * This should be overriden by authentication plugins where setting the
     * authentication method manually is allowed.
     *
     * @return bool
     */
    public function can_be_manually_set() {
        return true;
    }

    /**
     * Returns whether or not the captcha element is enabled.
     * @return bool
     */
    public function is_captcha_enabled() {
        return $this->config->recaptcha;
    }
}
