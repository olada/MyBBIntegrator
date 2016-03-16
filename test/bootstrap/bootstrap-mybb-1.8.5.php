<?php

// Let's bootstrap the MyBB stuff according to our distributed manual
// TODO @todo

ini_set('display_errors', 1);
error_reporting(E_ALL);
define('IN_MYBB', NULL);
define('ROOT_PATH', '');
define('PATH_BOOTSTRAP', __DIR__);
define('PATH_ROOT', PATH_BOOTSTRAP . '/../../');
require_once PATH_ROOT . 'test\vendor\mybb1.8.5\global.php';
require_once PATH_ROOT . '../../../../sssrc/class.MyBBIntegrator.php';
$MyBBI = new MyBBIntegrator($mybb, $db, $cache, $plugins, $lang, $config);