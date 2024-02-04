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
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot.'/user/editlib.php');
global $DB;
require_login();
require_capability('auth/wallet:manualconfirm', context_system::instance());

$baseurl = new moodle_url('/auth/wallet/bulkconfirm.php');
$PAGE->set_url($baseurl);

$context = context_system::instance();
$PAGE->set_context($context);

$title = get_string('manual_confirm', 'auth_wallet');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

$confirm = optional_param('confirm', false, PARAM_BOOL);

if ($confirm && !empty($SESSION->bulk_users) && confirm_sesskey()) {
    $i = 0;

    foreach ($SESSION->bulk_users as $userid) {
        $user = core_user::get_user($userid);
        if (!empty($user)) {
            auth_wallet_set_confirmed($user);
            $DB->set_field("user", "confirmed", 1, ["id" => $user->id]);
            $i++;
        }
    }
    redirect(new moodle_url('/admin/user/user_bulk.php'), get_string('usersconfirmed', 'auth_wallet', $i), null, 'success');
}

if (empty($SESSION->bulk_users)) {
    redirect(new moodle_url('/admin/user/user_bulk.php'), get_string('noselectedusers', 'bulkusers'), null, 'warning');
}

echo $OUTPUT->header();

list($in, $params) = $DB->get_in_or_equal($SESSION->bulk_users);
$users = $DB->get_records_select('user', 'id '.$in, $params);

$list = html_writer::start_tag('ul');
foreach ($users as $user) {
    $list .= html_writer::tag('li', fullname($user));
}
$list .= html_writer::end_tag('ul');
$message = get_string('confirmcheckfull', '', $list);

$baseurl->params(['confirm' => true]);
$cancel = new single_button(new moodle_url('/admin/user/user_bulk.php'), get_string('no'));
$continue = new single_button($baseurl, get_string('yes'));

echo $OUTPUT->confirm($message, $continue, $cancel);

echo $OUTPUT->footer();
