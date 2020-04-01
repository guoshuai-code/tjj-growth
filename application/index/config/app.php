<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-04-01
 * Time: 14:54
 */
$config = require(WEB_CONFIG_PATH.'/config/app.php');
$domain = require (WEB_CONFIG_PATH.'/config/proxy.php');
return array_merge($config,$domain);