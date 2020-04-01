<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-04-01
 * Time: 14:54
 */
$config = require(WEB_CONFIG_PATH.'/config/app.php');
$domain = require (WEB_CONFIG_PATH.'/config/domain.php');
$redis = require (WEB_CONFIG_PATH.'/config/redis.php');
$filter = require (WEB_CONFIG_PATH.'/config/filter.php');
$message = require (WEB_CONFIG_PATH.'/config/message.php');
return array_merge($config,$domain,$redis,$filter,$message);