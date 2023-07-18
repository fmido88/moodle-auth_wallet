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
require_once($CFG->dirroot.'/user/editlib.php');
global $DB;
require_login();
require_capability('auth/wallet:manualconfirm', context_system::instance());

$baseurl = new moodle_url('/auth/wallet/manualconfirm.php');
$PAGE->set_url($baseurl);

$context = context_system::instance();
$PAGE->set_context($context);

$PAGE->set_title(get_string('manual_confirm', 'auth_wallet'));
$PAGE->set_heading(get_string('manual_confirm', 'auth_wallet'));
$PAGE->set_pagelayout('admin');

$confirm = optional_param('confirm', '', PARAM_BOOL);
$userids = optional_param_array('userids', '', PARAM_INT);
if (!empty($confirm) && !empty($userids) && confirm_sesskey()) {
    $i = 0;

    foreach ($userids as $userid) {
        $user = get_complete_user_data('id', $userid);
        if (!empty($user)) {
            set_user_preference('auth_wallet_balanceconfirm', true, $user);
            useredit_update_user_preference($user);
            $DB->set_field("user", "confirmed", 1, array("id" => $user->id));
            $i++;
        }
    }
    redirect($baseurl, get_string('usersconfirmed', 'auth_wallet', $i), null, 'success');
}

echo $OUTPUT->header();

$mform = new MoodleQuickForm('manual_wallet_confirm', 'get', $baseurl);

$mform->addElement('header', 'head', get_string('manual_confirm', 'auth_wallet'));

$context = context_system::instance();
$options = [
    'id'         => 'manual-confirm_users',
    'ajax'       => 'enrol_manual/form-potential-user-selector',
    'multiple'   => true,
    'courseid'   => SITEID,
    'enrolid'    => 0,
    'perpage'    => $CFG->maxusersperpage,
    'userfields' => implode(',', \core_user\fields::get_identity_fields($context, true))
];
$mform->addElement('autocomplete', 'userids', get_string('selectusers', 'enrol_manual'), [], $options);
$mform->addRule('userids', 'select user', 'required', null, 'client');
$mform->addElement('submit', 'confirm', get_string('confirm'));

$mform->addElement('hidden', 'sesskey');
$mform->setType('sesskey', PARAM_TEXT);
$mform->setDefault('sesskey', sesskey());

$mform->display();

echo $OUTPUT->footer();
