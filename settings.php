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
 * Admin settings and defaults.
 *
 * @package     auth_wallet
 * @copyright   2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Introductory explanation.
    $settings->add(new admin_setting_heading('auth_wallet/pluginname', '',
                                                get_string('auth_walletdescription', 'auth_wallet')));

    $settings->add(new admin_setting_configcheckbox('auth_wallet/emailconfirm',
                                                get_string('emailconfirm', 'auth_wallet'),
                                                get_string('emailconfirm_desc', 'auth_wallet'), 0));

    $options = [
        'balance' => get_string('balance_required', 'auth_wallet'),
        'fee' => get_string('feerequired', 'auth_wallet')
    ];
    $settings->add(new admin_setting_configselect('auth_wallet/criteria',
                                                get_string('confirmcriteria', 'auth_wallet'),
                                                get_string('confirmcriteria', 'auth_wallet'), 1, $options));

    $requiredbalance = new admin_setting_configtext('auth_wallet/required_balance',
                                                get_string('required_balance', 'auth_wallet'),
                                                get_string('required_balance_desc', 'auth_wallet'),
                                                0,
                                                PARAM_FLOAT,
                                                null);
    $settings->add($requiredbalance);

    $extrafee = new admin_setting_configtext('auth_wallet/extra_fee',
                                                get_string('extra_fee', 'auth_wallet'),
                                                get_string('extra_fee_desc', 'auth_wallet'),
                                                0,
                                                PARAM_FLOAT,
                                                null);
    $settings->add($extrafee);

    $requiredfee = new admin_setting_configtext('auth_wallet/required_fee',
                                                get_string('required_fee', 'auth_wallet'),
                                                get_string('required_fee_desc', 'auth_wallet'),
                                                0,
                                                PARAM_FLOAT,
                                                null);
    $settings->add($requiredfee);

    $settings->hide_if('auth_wallet/required_fee', 'auth_wallet/criteria', 'eq', 'balance');
    $settings->hide_if('auth_wallet/required_balance', 'auth_wallet/criteria', 'eq', 'fee');
    $settings->hide_if('auth_wallet/extra_fee', 'auth_wallet/criteria', 'eq', 'fee');

    $options = [
        0 => get_string('no'),
        1 => get_string('yes'),
    ];
    $settings->add(new admin_setting_configselect('auth_wallet/recaptcha',
                                                get_string('auth_walletrecaptcha_key', 'auth_wallet'),
                                                get_string('auth_walletrecaptcha', 'auth_wallet'), 0, $options));

    // Display locking / mapping of profile fields.
    $authplugin = get_auth_plugin('wallet');
    display_auth_lock_options($settings, $authplugin->authtype, $authplugin->userfields,
            get_string('auth_fieldlocks_help', 'auth'), false, false);
}
