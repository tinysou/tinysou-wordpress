<?php
/*
Plugin Name: Tinysou
Plugin URI: https://github.com/tinysou/tinysou-wordpress
Description: 微搜索插件
Author: Tairy <tairyguo@gmail.com>
Version: 1.0.0
Author URI: http://tairy.me
*/

define('TINYSOU_VERSION','1.0.0');

require_once 'class-tinysou-client.php';
require_once 'class-tinysou-error.php';
require_once 'class-tinysou-plugin.php';

$tinysou_plugin = new TinysouPlugin();