<?php
/**
 * Minimal PHP 8.5 regression smoke tests.
 * Run from the project root: php tests/smoke.php
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/..';
define('__TYPECHO_ROOT_DIR__', ROOT);

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
