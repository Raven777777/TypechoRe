<?php
/**
 * Minimal PHP 8.5 regression smoke tests.
 * Run from the project root: php tests/smoke.php
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/..';
define('__TYPECHO_ROOT_DIR__', ROOT);
define('__TYPECHO_PAGE_CACHE_DIR__', sys_get_temp_dir() . '/typechore-page-cache-smoke-' . getmypid());
register_shutdown_function(static function (): void {
    foreach (glob(__TYPECHO_PAGE_CACHE_DIR__ . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir(__TYPECHO_PAGE_CACHE_DIR__);
});

spl_autoload_register(static function (string $class): void {
    foreach (['Typecho\\' => ROOT . '/var/Typecho/', 'Widget\\' => ROOT . '/var/Widget/', 'Utils\\' => ROOT . '/var/Utils/', 'IXR\\' => ROOT . '/var/IXR/'] as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }
    }
});

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require_once ROOT . '/var/Typecho/Common.php';
$general = (new ReflectionClass(\Widget\Options\General::class))->newInstanceWithoutConstructor();
check(
    $general->checkPageCacheEnabled('0') && $general->checkPageCacheEnabled('1'),
    'page cache toggle validation rejected a valid value'
);
check(!$general->checkPageCacheEnabled('2'), 'page cache toggle validation accepted an invalid value');
check(
    $general->checkPageCacheTtl('1') && $general->checkPageCacheTtl('86400'),
    'page cache TTL validation rejected a boundary value'
);
check(
    !$general->checkPageCacheTtl('0') && !$general->checkPageCacheTtl('86401') && !$general->checkPageCacheTtl('1.5'),
    'page cache TTL validation accepted an invalid value'
);
$cacheKey = hash('sha256', 'smoke-page-cache');
$cacheFile = __TYPECHO_PAGE_CACHE_DIR__ . '/' . $cacheKey;
@unlink($cacheFile);
check(\Typecho\PageCache::get($cacheKey) === null, 'page cache miss returned a body');
$cacheStart = time();
check(\Typecho\PageCache::put($cacheKey, '<html>cached</html>', 30), 'page cache could not store a body');
$cacheEntry = unserialize((string) file_get_contents($cacheFile), ['allowed_classes' => false]);
check(
    is_array($cacheEntry) && $cacheEntry['expires'] >= $cacheStart + 30 && $cacheEntry['expires'] <= time() + 30,
    'page cache did not apply its configured TTL'
);
check(\Typecho\PageCache::get($cacheKey) === '<html>cached</html>', 'page cache did not return its stored body');
$nullOptionKey = \Typecho\PageCache::key('generation', 'https://example.test/', null, null);
$expectedNullOptionKey = hash('sha256', implode("\n", [
    $_SERVER['HTTP_HOST'] ?? '', $_SERVER['REQUEST_URI'] ?? '/', 'https://example.test/', '', '', 'generation',
]));
check($nullOptionKey === $expectedNullOptionKey, 'page cache key failed with null theme/language options');
$generation = \Typecho\PageCache::generation();
\Typecho\PageCache::invalidate();
check(\Typecho\PageCache::generation() !== $generation, 'page cache generation did not advance');
$server = $_SERVER;
$get = $_GET;
$cookie = $_COOKIE;
\Typecho\Plugin::init([]);
$_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];
$_GET = [];
$_COOKIE = [];
check(\Typecho\PageCache::eligible(), 'page cache rejected an anonymous public GET');
$_SERVER = [
    'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_COOKIE' => '', 'REDIRECT_HTTP_COOKIE' => '',
    'HTTP_AUTHORIZATION' => '', 'REDIRECT_HTTP_AUTHORIZATION' => '',
];
check(\Typecho\PageCache::eligible(), 'page cache rejected empty FastCGI cookie/auth params');
$_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'];
check(!\Typecho\PageCache::eligible(), 'page cache accepted a non-GET request');
$_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/?x=1', 'QUERY_STRING' => 'x=1'];
check(!\Typecho\PageCache::eligible(), 'page cache accepted a query string');
$_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/'];
check(!\Typecho\PageCache::eligible(), 'page cache accepted an admin path');
foreach (['HTTP_COOKIE', 'REDIRECT_HTTP_COOKIE', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $header) {
    $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', $header => '0'];
    check(!\Typecho\PageCache::eligible(), "page cache accepted a non-empty {$header}");
}
\Typecho\Plugin::init(['activated' => ['test' => []]]);
$_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];
check(!\Typecho\PageCache::eligible(), 'page cache accepted an active plugin');
\Typecho\Plugin::init([]);
$_SERVER = $server;
$_GET = $get;
$_COOKIE = $cookie;
@unlink($cacheFile);
check(class_exists(\Widget\Comments\Ping::class), 'Ping class failed to load');
check(\Typecho\Common::isSameOrigin('https://example.test/admin/login.php', 'https://example.test'), 'same-origin check failed');
check(\Typecho\Common::isSameOrigin('https://example.test:443/', 'https://example.test'), 'default HTTPS port check failed');
check(!\Typecho\Common::isSameOrigin('https://not-example.test/', 'https://example.test'), 'lookalike host passed same-origin check');
check(!\Typecho\Common::isSameOrigin('https://example.test:444/', 'https://example.test'), 'different port passed same-origin check');

check(\Typecho\Common::hashValidate('password', \Typecho\Common::hashPassword('password')), 'password hash validation failed');
foreach (['$T$' . str_repeat('x', 40), '$P$' . str_repeat('x', 31), md5('password')] as $legacyPasswordHash) {
    check(!\Typecho\Common::hashValidate('password', $legacyPasswordHash), 'unsupported legacy password hash was accepted');
}
$authCodeHash = \Typecho\Common::hashAuthCode('auth-token');
check(\Typecho\Common::validateAuthCode('auth-token', $authCodeHash), 'auth code validation failed');
check(!\Typecho\Common::validateAuthCode('wrong-token', $authCodeHash), 'invalid auth code accepted');
check(\Typecho\Common::escape('<script>') === '&lt;script&gt;', 'HTML escaping failed');
check(\Typecho\Common::escape('&amp;') === '&amp;', 'HTML escaping double-encoded an existing entity');
check(\Typecho\Common::slugName('中文 测试') === '中文-测试', 'UTF-8 slug generation failed');
check(\Typecho\Common::checkSafeHost('127.0.0.1') === false, 'private IPv4 host accepted');
check(\Typecho\Common::checkSafeHost('localhost') === false, 'localhost accepted');
$apacheRules = file_get_contents(ROOT . '/.htaccess');
if (
    !is_string($apacheRules)
    || 1 !== preg_match('/<FilesMatch "([^"]*sqlite3[^"]*)">/', $apacheRules, $apacheMatch)
) {
    throw new RuntimeException('Apache rules do not define a SQLite database deny rule');
}
foreach ([
    'site.db', 'site.db-wal', 'site.db-shm', 'site.db-journal',
    'site.sqlite3', 'site.sqlite3-wal', 'site.sqlite3-shm', 'site.sqlite3-journal',
] as $databaseFile) {
    check(
        1 === preg_match('~' . $apacheMatch[1] . '~', $databaseFile),
        "Apache rules do not deny {$databaseFile}"
    );
}
$iisRules = file_get_contents(ROOT . '/web.config');
check(
    is_string($iisRules)
        && str_contains($iisRules, 'fileExtension=".db-wal"')
        && str_contains($iisRules, 'fileExtension=".db-shm"')
        && str_contains($iisRules, 'fileExtension=".sqlite3-journal"'),
    'IIS rules do not deny SQLite WAL/SHM/journal files'
);

// PHP 8.5: HMAC-SHA256 短时 token (替代旧 sha1 + 非恒定时间比较)
$token = \Typecho\Common::timeToken('smoke-secret');
check(strlen($token) === 64, 'time token is not a SHA-256 HMAC hex digest');
check(\Typecho\Common::timeTokenValidate($token, 'smoke-secret', 5), 'valid time token rejected');
check(!\Typecho\Common::timeTokenValidate($token, 'other-secret', 5), 'time token accepted for wrong secret');
check(!\Typecho\Common::timeTokenValidate(str_repeat('0', 64), 'smoke-secret', 5), 'forged time token accepted');
check(!\Typecho\Common::timeTokenValidate('short', 'smoke-secret', 5), 'malformed time token accepted');

// PHP 8.5: json_validate 可用
check(json_validate('[1,2,3]'), 'json_validate failed on valid JSON');
check(!json_validate('{invalid'), 'json_validate accepted invalid JSON');
check(function_exists('array_first') && function_exists('array_last'), 'PHP 8.5 array_first/array_last missing');

// 备份缓冲: 新格式 SHA-256, 兼容旧格式 MD5
$v2 = \Typecho\Common::buildBackupBuffer('1', '{"a":1}', 'body');
check(strlen($v2) === 8 + 7 + 4 + 64, 'v2 backup buffer is not SHA-256 terminated');
check(substr($v2, -64) === hash('sha256', substr($v2, 0, -64)), 'v2 backup SHA-256 mismatch');

$v2file = tempnam(sys_get_temp_dir(), 'typechore-bak2-');
file_put_contents($v2file, $v2);
$fp = fopen($v2file, 'rb');
$offset = 0;
$parsed = \Typecho\Common::extractBackupBuffer($fp, $offset, '0002');
fclose($fp);
@unlink($v2file);
check($parsed === [1, '{"a":1}', 'body'], 'v2 backup buffer round-trip failed');

$legacy = pack('vvV', 2, 2, 1) . 'ab' . 'c';
$legacy .= md5($legacy);
$v1file = tempnam(sys_get_temp_dir(), 'typechore-bak1-');
file_put_contents($v1file, $legacy);
$fp = fopen($v1file, 'rb');
$offset = 0;
$parsed = \Typecho\Common::extractBackupBuffer($fp, $offset, '0001');
fclose($fp);
@unlink($v1file);
check($parsed === [2, 'ab', 'c'], 'legacy v1 MD5 backup buffer round-trip failed');

// Config 实现 Iterator/ArrayAccess 的原生 mixed 返回类型
$config = new \Typecho\Config(['a' => 1, 'b' => 2]);
check($config['a'] === 1 && $config->b === 2, 'Config ArrayAccess/magic access failed');
check(iterator_to_array($config, true) === ['a' => 1, 'b' => 2], 'Config iteration failed');

// MIME 探测: 启用 fileinfo 后基于文件内容识别
$pngFile = tempnam(sys_get_temp_dir(), 'typechore-mime-');
file_put_contents(
    $pngFile,
    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
);
check(\Typecho\Common::mimeContentType($pngFile) === 'image/png', 'PNG content MIME detection failed');
@unlink($pngFile);

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10';
$request = \Typecho\Request::getInstance();
check($request->getIp() === '127.0.0.1', 'untrusted forwarded IP was accepted');
unset($_SERVER['HTTP_X_FORWARDED_FOR']);

$webauthn = new \lbuchs\WebAuthn\WebAuthn('TypechoRe', 'localhost', ['none'], true);
$passkeyArgs = $webauthn->getCreateArgs('1', 'admin', 'Admin', 60, 'required', 'required');
check(isset($passkeyArgs->publicKey->challenge, $passkeyArgs->publicKey->user->id), 'WebAuthn create options failed');
$originCheck = new ReflectionMethod($webauthn, '_checkOrigin');
check($originCheck->invoke($webauthn, 'https://localhost'), 'valid WebAuthn origin rejected');
$rpWebAuthn = new \lbuchs\WebAuthn\WebAuthn('TypechoRe', 'example.test', ['none'], true);
$originCheck = new ReflectionMethod($rpWebAuthn, '_checkOrigin');
check($originCheck->invoke($rpWebAuthn, 'https://sub.example.test'), 'valid RP subdomain origin rejected');
check(!$originCheck->invoke($rpWebAuthn, 'https://not-example.test'), 'lookalike RP origin accepted');

$dbFile = tempnam(sys_get_temp_dir(), 'typechore-');
check(false !== $dbFile, 'could not create temporary SQLite file');
try {
    $adapter = new \Typecho\Db\Adapter\SQLite();
    $config = new \Typecho\Config(['file' => $dbFile]);
    $handle = $adapter->connect($config);
    check(
        \SQLite3Result::class === $adapter->query(
            'CREATE TABLE smoke (id INTEGER PRIMARY KEY, value TEXT)',
            $handle
        )::class,
        'SQLite DDL failed'
    );
    check(
        \SQLite3Result::class === $adapter->query("INSERT INTO smoke (value) VALUES ('ok')", $handle)::class,
        'SQLite insert failed'
    );
    $result = $adapter->query('SELECT value FROM smoke', $handle);
    check($adapter->fetch($result)['value'] === 'ok', 'SQLite fetch failed');
    $handle->close();
} finally {
    @unlink($dbFile);
}

// 遗留类名别名表: 每个目标都必须能加载, 自动加载器也必须能解析遗留名
foreach (\Typecho\Common::CLASS_ALIASES as $legacyName => $target) {
    check(
        class_exists($target) || interface_exists($target),
        "legacy alias target {$target} ({$legacyName}) does not exist"
    );
}
check(
    interface_exists('Typecho_Plugin_Interface'),
    'legacy alias Typecho_Plugin_Interface failed to resolve to Typecho\\Plugin\\PluginInterface'
);
check(
    class_exists('Widget_Abstract_Contents'),
    'legacy alias Widget_Abstract_Contents failed to resolve to Widget\\Base\\Contents'
);
check(
    class_exists('Typecho_Db_Adapter_Mysql'),
    'legacy alias Typecho_Db_Adapter_Mysql failed to resolve to Typecho\\Db\\Adapter\\Mysqli'
);

// XmlRpc::mtSetPostCategories() 从外部调用 setCategories(), 必须保持 public
check(
    (new ReflectionMethod(\Widget\Contents\Post\Edit::class, 'setCategories'))->isPublic(),
    'Post\Edit::setCategories() must be public for XmlRpc'
);
// 附件编辑需要分页定位方法 (先前缺失, 调用会被 __call 吞掉)
check(
    (new ReflectionMethod(\Widget\Contents\Attachment\Edit::class, 'getPageOffset'))->isProtected(),
    'Attachment\Edit does not expose PageOffsetTrait::getPageOffset()'
);
// ceil(total/pageSize) 的整数等价实现
$navigator = new \Typecho\Widget\Helper\PageNavigator\Box(10, 1, 5, '/page/{page}');
check(
    2 === (new ReflectionProperty($navigator, 'totalPage'))->getValue($navigator),
    'PageNavigator totalPage should be 2 for 10 items / 5 per page'
);
$navigator = new \Typecho\Widget\Helper\PageNavigator\Box(10, 1, 4, '/page/{page}');
check(
    3 === (new ReflectionProperty($navigator, 'totalPage'))->getValue($navigator),
    'PageNavigator totalPage should round up (10 items / 4 per page -> 3)'
);

// 评论 HTML 过滤: 白名单只管标签与属性名, 属性值里的危险协议必须被拦掉
$commentTags = '<p><br> <strong> <code> <a href title rel>';
$safeComment = '<p><strong>粗体</strong>、<code>x</code> 与 <a href="https://example.com" title="链接">链接</a></p>';
check(
    \Typecho\Common::stripTags($safeComment, $commentTags) === $safeComment,
    'Comment filtering dropped safe markdown markup or a safe https link'
);
check(
    str_contains(\Typecho\Common::stripTags('<p><a href="/x?a=1&amp;b=2">x</a></p>', $commentTags), 'href="/x?a=1&amp;b=2"'),
    'Comment filtering rewrote a safe attribute value'
);
check(
    !str_contains(\Typecho\Common::stripTags('<p><a href="/x" onclick="evil()">x</a></p>', $commentTags), 'onclick'),
    'Comment filtering kept an attribute outside the whitelist'
);

foreach ([
    'javascript: 协议' => '<a href="javascript:alert(1)">x</a>',
    '实体编码协议'     => '<a href="java&#115;cript:alert(1)">x</a>',
    '制表符拆开协议'   => "<a href=\"java\tscript:alert(1)\">x</a>",
    '换行拆开协议'     => "<a href=\"java\nscript:alert(1)\">x</a>",
    '大小写混写'       => '<a href="JaVaScRiPt:alert(1)">x</a>',
    '前导空白'         => '<a href="  javascript:alert(1)">x</a>',
    'data: 协议'       => '<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>',
    'vbscript: 协议'   => '<a href="vbscript:msgbox(1)">x</a>',
] as $vector => $payload) {
    $filtered = \Typecho\Common::stripTags('<p>' . $payload . '</p>', $commentTags);

    check(
        !preg_match('/javascript|vbscript|data:/i', $filtered) && str_contains($filtered, 'href="#"'),
        "Dangerous link scheme survived comment filtering ({$vector}): {$filtered}"
    );
}

// 评论 Markdown: 打开 commentsMarkdown 时, 内核要放行 Markdown 生成的标签
$markdownTags = (new ReflectionClassConstant(\Widget\Base\Comments::class, 'MARKDOWN_ALLOWED_TAGS'))->getValue();
check(is_string($markdownTags) && '' !== $markdownTags, 'Comments::MARKDOWN_ALLOWED_TAGS is missing');

$markdownCommentSpec = '<p><br> ' . $markdownTags;

foreach (['strong', 'em', 'del', 'code', 'pre', 'blockquote', 'ul', 'ol', 'li', 'h2', 'hr', 'a'] as $tag) {
    check(
        str_contains($markdownCommentSpec, '<' . $tag),
        "Markdown comment whitelist does not allow <{$tag}>"
    );
}

foreach (['img', 'script', 'iframe', 'style', 'form', 'input', 'object', 'embed'] as $tag) {
    check(
        !str_contains($markdownCommentSpec, '<' . $tag . '>') && !str_contains($markdownCommentSpec, '<' . $tag . ' '),
        "Markdown comment whitelist must not allow <{$tag}>"
    );
}

check(
    str_contains(\Typecho\Common::stripTags('<p><strong>粗</strong> <em>斜</em></p>', $markdownCommentSpec), '<strong>粗</strong>'),
    'Markdown markup was stripped even with the markdown whitelist'
);

// Markdown 侧的第一道协议检查: 危险协议换成 #, 而且不能触发 PHP 警告
check(
    str_contains(\Utils\Markdown::convert('[x](javascript:alert(1))'), '<a href="#">'),
    'Markdown link with a dangerous scheme was not neutralised'
);
check(
    str_contains(\Utils\Markdown::convert('[x](https://example.com)'), '<a href="https://example.com">'),
    'Markdown link with a safe scheme was mangled'
);

// IXR: 联合类型参数不能触发 ReflectionType::getName() 致命错误, 错误必须被识别
$ixrProbe = new class () {
    public function union(int|string $value): string
    {
        return 'ok:' . get_debug_type($value);
    }

    public function boom(): string
    {
        throw new \IXR\Exception('boom', 42);
    }
};
$ixrServer = new \IXR\Server([
    'probe.union' => [$ixrProbe, 'union'],
    'probe.boom'  => [$ixrProbe, 'boom'],
]);
$ixrResult = $ixrServer->multiCall([
    ['methodName' => 'probe.union', 'params' => [42]],
    ['methodName' => 'probe.boom', 'params' => []],
    ['methodName' => 'probe.missing', 'params' => []],
]);
check($ixrResult[0] === ['ok:int'], 'IXR union-typed parameter call failed');
check(
    isset($ixrResult[1]['faultCode']) && 42 === $ixrResult[1]['faultCode'],
    'IXR multiCall did not report a thrown IXR\Exception as a fault'
);
check(
    isset($ixrResult[2]['faultCode']) && -32601 === $ixrResult[2]['faultCode'],
    'IXR multiCall did not report an unknown method as a fault'
);

// 全量类加载: 任何 #[\Override] 签名漂移/类链接错误都会在此致命失败
$classFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . '/var'));
$classNames = [];
foreach ($classFiles as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $src = file_get_contents($file->getPathname());
    if (!preg_match_all('/^namespace\s+([^;]+);/m', $src, $nsMatches)) {
        continue;
    }
    $ns = trim($nsMatches[1][0]);
    if (str_starts_with($ns, 'lbuchs')) {
        continue;
    }
    if (preg_match_all('/^\s*(?:final\s+|abstract\s+)?(?:class|interface|trait)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $src, $cMatches)) {
        foreach ($cMatches[1] as $short) {
            $classNames[] = $ns . '\\' . $short;
        }
    }
}
$classNames = array_values(array_unique($classNames));
check(count($classNames) > 100, 'class discovery failed');
foreach ($classNames as $className) {
    class_exists($className) || interface_exists($className) || trait_exists($className);
}
check(true, 'class sweep completed');

echo "PASS: PHP 8.5 smoke tests\n";
