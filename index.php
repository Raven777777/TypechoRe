<?php
/**
 * Typecho Blog Platform
 *
 * @copyright  Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license    GNU General Public License 2.0
 * @version    $Id: index.php 1153 2009-07-02 10:53:22Z magike.net $
 */

/** 载入配置支持 */
if (!defined('__TYPECHO_ROOT_DIR__') && !@include_once 'config.inc.php') {
    file_exists('./install.php') ? header('Location: install.php') : print('Missing Config File');
    exit;
}

/** 初始化组件 */
\Widget\Init::alloc();

/** 注册一个初始化插件 */
\Typecho\Plugin::factory('index.php')->call('begin');

$response = \Typecho\Response::getInstance();
$cacheableRequest = \Typecho\PageCache::eligible();
$cacheGeneration = $cacheableRequest ? \Typecho\PageCache::generation() : '';
$options = \Widget\Options::alloc();
$cacheKey = $cacheableRequest
    ? \Typecho\PageCache::key($cacheGeneration, $options->theme, $options->lang)
    : '';
$cachedBody = $cacheableRequest ? \Typecho\PageCache::get($cacheKey) : null;

if ($cachedBody !== null && \Typecho\PageCache::generation() === $cacheGeneration) {
    $response->setHeader('X-Typecho-Page-Cache', 'HIT');
    echo $cachedBody;
} else {
    $responseState = $response->cacheState();
    $headersBefore = headers_list();
    ob_start();
    \Typecho\Router::dispatch();
    $body = ob_get_clean();
    $cacheStored = false;
    if (
        $cacheableRequest
        && \Typecho\PageCache::generation() === $cacheGeneration
        && $response->cacheState() === $responseState
        && headers_list() === $headersBefore
        && ($responseState[0] ?? null) === 200
        && str_starts_with(strtolower($responseState[3] ?? ''), 'text/html')
    ) {
        $cacheStored = \Typecho\PageCache::put($cacheKey, $body);
    }
    $response->setHeader('X-Typecho-Page-Cache', $cacheStored ? 'MISS' : 'BYPASS');
    echo $body;
}

/** 注册一个结束插件 */
\Typecho\Plugin::factory('index.php')->call('end');
