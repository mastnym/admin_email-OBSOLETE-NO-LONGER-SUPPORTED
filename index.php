<?php

// Written at Louisiana State University
global $CFG, $USER, $SESSION, $PAGE, $SITE, $OUTPUT, $DB;
require_once '../../config.php';
require_once "$CFG->dirroot/course/lib.php";
require_once "$CFG->libdir/adminlib.php";
require_once "$CFG->dirroot/user/filters/lib.php";
require_once 'lib.php';
require_once 'email_form.php';

require_login();

require_capability('moodle/site:config', context_system::instance());

$page       = optional_param('page', 0, PARAM_INT);
$perpage    = optional_param('perpage', 20, PARAM_INT);
$sort       = optional_param('sort', '', PARAM_ACTION);
$direction  = optional_param('dir', 'ASC', PARAM_ACTION);

$blockname  = get_string('pluginname', 'block_admin_email');
$header     = get_string('send_email', 'block_admin_email');

$context    = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url('/blocks/admin_email/');
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($header);
$PAGE->set_heading($SITE->shortname.': '.$blockname);

// Get Our users
$fields = array(
    'realname'      => 1,
    'lastname'      => 1,
    'firstname'     => 1,
    'email'         => 1,
    'city'          => 1,
    'country'       => 1,
    'confirmed'     => 1,
    'suspended'     => 1,
    'profile'       => 1,
    'courserole'    => 0,
    'systemrole'    => 0,
    'username'      => 0,
    'cohort'        => 1,
    'firstaccess'   => 1,
    'lastaccess'    => 0,
    'neveraccessed' => 1,
    'timemodified'  => 1,
    'nevermodified' => 1,
    'auth'          => 1,
    'mnethostid'    => 1,
    );

$ufiltering         = new user_filtering($fields);
list($sql, $params) = $ufiltering->get_sql_filter();
$usersearchcount    = get_users(false, '', true, null, '', '', '', '', '',
                '*', $sql, $params);

if(empty($sort)) $sort = 'lastname';

$display_users  = empty($sql) ? array() :
    get_users_listing($sort, $direction, $page*$perpage,
    $perpage, '', '', '', $sql, $params);

$users          = empty($sql) ? array() :
    get_users_listing($sort, $direction, 0,
    0, '', '', '', $sql, $params);

$form = new email_form();

// Process data submission
if ($form->is_cancelled()) {
    unset($SESSION->user_filtering);
    redirect(new moodle_url('/blocks/admin_email/'));
} else if ($data = $form->get_data()) {

    $message = new Message($data, array_keys($users));
    $message->send();
    $message->sendAdminReceipt();
    // Finished processing
    // Empty errors mean that you can go back home
    if(empty($message->warnings))
        redirect(new moodle_url('/'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($header);

// Notify the admin.
if(!empty($message->warnings)) {
    foreach($message->warnings as $warning) {
        echo $OUTPUT->notification($warning);
    }
}

// Start work
$ufiltering->display_add();
$ufiltering->display_active();

$paging_bar = !$sql ? '' :
    $OUTPUT->paging_bar($usersearchcount, $page, $perpage,
        new moodle_url('/blocks/admin_email/index.php', array(
            'sort' => $sort,
            'dir' => $direction,
            'perpage' => $perpage
        )
    ));

if(!empty($sql)) {
    echo $OUTPUT->heading("Found $usersearchcount User(s)");
}

echo $paging_bar;

if(!empty($display_users)) {
    $columns = array('firstname', 'lastname', 'email', 'city', 'lastaccess');
    foreach($columns as $column) {
        $direction = ($sort == $column and $direction == "ASC") ? "DESC" : "ASC";
        $$column = html_writer::link('index.php?sort='.$column.'&dir='.
            $direction, get_string($column));
    }
    $table = new html_table();

    $table->head = array("$firstname / $lastname", $email, $city, $lastaccess);
    $table->data = array_map(function($user) {
        $fullname = fullname($user);
        $email = $user->email;
        $city = $user->city;
        $lastaccess_time = isset($user->lastaccess) ?
            format_time(time() - $user->lastaccess) : get_string('never');
        return array($fullname, $email, $city, $lastaccess_time);
    }, $display_users);
    echo html_writer::table($table);
    $form->set_data(array('noreply' => $CFG->noreplyaddress));
    echo $form->display();
}

echo $paging_bar;

echo $OUTPUT->footer();
