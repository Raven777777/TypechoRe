<?php

define('__TYPECHO_ROOT_DIR__', dirname(__DIR__));
define('__TYPECHO_PLUGIN_DIR__', '/usr/plugins');
require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';

// 运行时由 Widget\Init / admin/common.php 定义的常量, 这里补声明给静态分析用
defined('__TYPECHO_BACKUP_DIR__') || define('__TYPECHO_BACKUP_DIR__', __TYPECHO_ROOT_DIR__ . '/usr/backups');
