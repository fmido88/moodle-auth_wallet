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
 * Plugin strings are defined here.
 *
 * @package     auth_wallet
 * @category    string
 * @copyright   2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['auth_walletdescription'] = '<p>self-registration based on user balance enables a user to create their own account via a \'Create new account\' button on the login page. The user then receives an optional email containing a secure link or redirected to a page where they can confirm their account. Future logins just check the username and password against the stored values in the Moodle database.</p><p>Note: In addition to enabling the plugin, Signup with Wallet Balance Confirm must also be selected from the self registration drop-down menu on the \'Manage authentication\' page.</p>';
$string['auth_walletnoemail'] = 'Tried to send you an email but failed!';
$string['auth_walletrecaptcha'] = 'Adds a visual/audio confirmation form element to the sign-up page for email self-registering users. This protects your site against spammers and contributes to a worthwhile cause. See https://www.google.com/recaptcha for more details.';
$string['auth_walletrecaptcha_key'] = 'Enable reCAPTCHA element';
$string['auth_walletsettings'] = 'Settings';
$string['applytoall'] = 'Apply to all';
$string['applytoall_desc'] = 'Apply balance or fee confirmation to all auth plugins.';
$string['balance_required'] = 'Balance Required';
$string['confirmcriteria'] = 'Confirmation Requirement';
$string['confirmcriteria_desc'] = 'Choose if the confirmation required a payment fee or some amount in the wallet.';
$string['emailconfirm'] = 'Send confirmation email';
$string['emailconfirm_desc'] = 'Sending a confirmation email first then redirect the user to the topping up page to charge the wallet with the minimum required amount.';
$string['extra_fee'] = 'Extra fee';
$string['extra_fee_desc'] = 'Deduct extra fee from the balance after confirmation.<br>NOTE: Make sure that Extra fees is less than the required balance, else confirmation error thrown.';
$string['extra_fee_error'] = 'Cannot set extra fee more than the required balance.';
$string['debitextrafee_desc'] = 'Extra fee for signup confirmation.';
$string['debitfee_desc'] = 'Fee for signup confirmation';
$string['extrafeerequired'] = '<p>Note: There is an extra fee {$a} will be deducted after confirmation.</p>';
$string['feerequired'] = 'Fee Required';
$string['payment_required'] = '<h6>Welcome {$a->name}</h5>
<p>In order to complete your signup you must have a balance of {$a->required} {$a->currency} in your wallet.</p>
<p><strong>Your current balance is {$a->balance} {$a->currency}</strong></p>
<p>You need to recharge you wallet by {$a->rest} {$a->currency}</p>
{$a->extrafee}';
$string['fee_required'] = '<h6>Welcome {$a->name}</h5>
<p>In order to complete your signup you must pay confirmation fee {$a->required} {$a->currency} by your wallet.</p>
<p><strong>Your current balance is {$a->balance} {$a->currency}</strong></p>
<p>You need to recharge you wallet by {$a->rest} {$a->currency} to complete payment</p>';
$string['manual_confirm'] = 'Manual wallet balance confirmation for users';
$string['pluginname'] = 'Signup with Wallet Balance Confirm';
$string['privacy:metadata'] = 'The Signup with Wallet Balance Confirm authentication plugin does not store any personal data.';
$string['required_balance'] = 'Min required balance.';
$string['required_balance_desc'] = 'The minimum required balance the user need to charge wallet to confirm registration';
$string['required_fee'] = 'Required fee';
$string['required_fee_desc'] = 'Minimum required payment fee to confirm the account.';
$string['settingerror'] = 'Configuration error, please contact support.';
$string['usersconfirmed'] = '{$a} user(s) has been confirmed.';
