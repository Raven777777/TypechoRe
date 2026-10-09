<?php
/**
 * 后台模板作用域变量: 由 admin/common.php (以及 header.php / menu.php) 通过 include 注入。
 * PHPStan 无法跨 include 传播局部变量, 这里按实际作用域显式声明。
 *
 * @var Widget\Options $options
 * @var Typecho\Widget\Request $request
 */

include 'common.php';

$panel = $request->get('panel');
$panelTable = $options->panelTable;

if (!isset($panelTable['file']) || !in_array(urlencode($panel), $panelTable['file'])) {
    throw new \Typecho\Plugin\Exception(_t('页面不存在'), 404);
}

[$pluginName, $file] = explode('/', trim($panel, '/'), 2);

require_once $options->pluginDir($pluginName) . '/' . $file;
