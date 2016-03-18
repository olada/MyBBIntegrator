<?php

// Let's bootstrap the MyBB stuff according to our distributed manual
// TODO @todo

function __($msg, $withPre = false, $die = true) {
	if ($withPre) echo "<pre>";
	print_r($msg);
	if ($withPre) echo "</pre>";
}

ini_set('display_errors', 1);
error_reporting(E_ALL);
//define('IN_MYBB', NULL);
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'index.php');

define('PHPDAVE_PATH_BOOTSTRAP', __DIR__);
define('PHPDAVE_PATH_ROOT', PHPDAVE_PATH_BOOTSTRAP . '/../../');

// Register all globals, so PHPUnit does not get rid of them
// These are so many, we might as well just do one line per first character...
// This is the place where you add globals in case single tests fail
global $admin_session, $announcements, $attachtypes, $archive_url, 
	   $base_url, 
	   $cache, $cached_forum_permissions_permissions, $cached_forum_permissions, $canview, $canpostthreads, $canpostreplies, 
	   	$canpostpolls, $canpostattachments, $change_dir, $config, $cp_style, 
	   $db, $debug, $displaygroupfields, $done_shutdown,
	   $error_handler, 
	   $fpermcache, $fpermfields, $fselectcache, $footer, $form, $forum_cache, $forumarraycache, $forumpermissions, 
	   $globaltime, $groupscache, $grouppermignore, $groupzerogreater, 
	   $header, $headerinclude, $htmldoctype,
	   $icon, $img_width, $img_height, $inherit, 
	   $lang, $list, 
	   $maintimer, $max_angle, $max_size, $mergethread, $min_angle, $min_size, $moderation, $modules_dir, $mybb, $mybbgroups,
	   $navbits,
	   $page, $pagestarttime, $parser, $parsetime, $permissioncache, $plugins, $pforumcache, 
	   $run_module, 
	   $selectoptions, $session, $shutdown_queries, $shutdown_functions, $smiliecache, $smiliecount, $sub_forums, 
	   $table, $task, $templatecache, $templatelist, $templates, $theme_cache, $thread, $time, $ttf_fonts, 
	   $user_cache, $user_view_fields, $use_ttf;

require_once PHPDAVE_PATH_ROOT . 'test\vendor\mybb1.8.5\global.php';
require_once PHPDAVE_PATH_ROOT . 'src\class.MyBBIntegrator.php';
$MyBBI = new MyBBIntegrator($mybb, $db, $cache, $plugins, $lang, $config);

__($MyBBI->getMembers());