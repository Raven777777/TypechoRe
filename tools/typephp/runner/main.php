<?php

/**
 * TypePHP 二进制入口: 把一次 HTTP 请求交给 TypechoRe 的正常引导流程处理。
 *
 * AOT 只编译这一个文件, TypechoRe 自身仍然由 Zend 运行时解释执行 (include 引入),
 * 因此这个入口的作用是「用环境变量伪造一个请求」并打印响应, 用来验证
 * `var/Typecho/*` 与主题模板在原生进程里能正常跑起来。
 *
 * 环境变量:
 *   TYPECHORE_ROOT  站点根目录 (必填, 含 index.php 与 config.inc.php)
 *   TYPECHORE_URI   请求路径, 默认为 /
 *   TYPECHORE_DUMP  设为 1 时把响应正文写到 stdout (重定向即可存成 .html),
 *                   状态行改走 stderr; 不设则只打印状态行
 */

function main(): void
{
    $root = getenv('TYPECHORE_ROOT');

    if ($root === false || $root === '' || !is_file($root . '/index.php')) {
        fwrite(STDERR, "TYPECHORE_ROOT 必须指向含 index.php 的站点目录\n");
        exit(2);
    }

    $uri = getenv('TYPECHORE_URI');
    $uri = ($uri === false || $uri === '') ? '/' : $uri;

    // TypechoRe 从 $_SERVER 里读请求, 这里补全一次 GET 请求需要的全部字段
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['QUERY_STRING'] = '';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['SERVER_PORT'] = '80';
    $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    $_SERVER['DOCUMENT_ROOT'] = $root;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'typephp-runner';
    $_SERVER['HTTP_ACCEPT'] = 'text/html';

    chdir($root);
    ob_start();
    include $root . '/index.php';
    $body = ob_get_clean();

    $title = preg_match('#<title>(.*?)</title>#s', $body, $match) === 1 ? trim($match[1]) : '(none)';
    $dump = getenv('TYPECHORE_DUMP') === '1';
    // 状态行与正文分开输出: 正文可以重定向到 .html 文件
    $status = $dump ? STDERR : STDOUT;

    fwrite($status, sprintf("uri=%s bytes=%d title=%s\n", $uri, strlen($body), $title));

    if ($dump) {
        echo $body;
    }
}
