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
$options = \Widget\Options::alloc();
$cacheableRequest = false;
$cacheTtl = 60;
$cacheGeneration = '';
$cacheKey = '';
$cachedBody = null;
try {
    $cacheEnabled = (string) ($options->pageCacheEnabled ?? '1') === '1';
    $cacheTtl = max(1, min(86400, (int) ($options->pageCacheTtl ?? 60)));
    $cacheableRequest = $cacheEnabled && \Typecho\PageCache::eligible();
    if ($cacheableRequest) {
        $cacheGeneration = \Typecho\PageCache::generation();
        $cacheKey = \Typecho\PageCache::key($cacheGeneration, $options->rootUrl, $options->theme, $options->lang);
        $cachedBody = \Typecho\PageCache::get($cacheKey);
        if ($cachedBody !== null && \Typecho\PageCache::generation() !== $cacheGeneration) {
            $cachedBody = null;
        }
    }
} catch (\Throwable $error) {
    // Page-cache errors must fail open; public rendering still needs to run.
    error_log('Typecho page cache preflight failed: ' . $error->getMessage());
    $cacheableRequest = false;
    $cachedBody = null;
}

if ($cachedBody !== null) {
    $response->setHeader('X-Typecho-Page-Cache', 'HIT');
    echo $cachedBody;
} else {
    ob_start();
    \Typecho\Router::dispatch();
    $body = ob_get_clean();
    $cacheStored = false;
    try {
        $state = $response->cacheState();
        $unsafeHeader = false;

        foreach (array_keys($state[1]) as $headerName) {
            if (in_array(strtolower((string) $headerName), ['set-cookie', 'vary', 'cache-control'], true)) {
                $unsafeHeader = true;
            }
        }

        /**
         * 只拦“这个响应不能共用”的信号: 设 cookie、Vary、Cache-Control、响应器。
         * 以前比较全部 header, 结果 X-Pingback 这种固定 header 就让单篇/页面全部缓存不上。
         */
        if (
            $cacheableRequest
            && \Typecho\PageCache::generation() === $cacheGeneration
            && !$unsafeHeader
            && empty($state[2])
            && empty($state[4])
            && ($state[0] ?? null) === 200
            && str_starts_with(strtolower($state[3] ?? ''), 'text/html')
        ) {
            $cacheStored = \Typecho\PageCache::put($cacheKey, $body, $cacheTtl);
        }
    } catch (\Throwable $error) {
        error_log('Typecho page cache store failed: ' . $error->getMessage());
        $cacheStored = false;
    }
    $response->setHeader('X-Typecho-Page-Cache', $cacheStored ? 'MISS' : 'BYPASS');
    echo $body;
}

/** 注册一个结束插件 */
\Typecho\Plugin::factory('index.php')->call('end');
