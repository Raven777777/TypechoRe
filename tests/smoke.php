<?php
/**
 * Minimal PHP 8.5 regression smoke tests.
 * Run from the project root: php tests/smoke.php
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/..';
define('__TYPECHO_ROOT_DIR__', ROOT);

spl_autoload_register(static function (string $class): void {
    foreach (['Typecho\\' => ROOT . '/var/Typecho/', 'Widget\\' => ROOT . '/var/Widget/', 'Utils\\' => ROOT . '/var/Utils/'] as $prefix => $base) {
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

check(\Typecho\Common::hashValidate('password', \Typecho\Common::hashPassword('password')), 'password hash validation failed');
$authCodeHash = \Typecho\Common::hashAuthCode('auth-token');
check(\Typecho\Common::validateAuthCode('auth-token', $authCodeHash), 'auth code validation failed');
check(!\Typecho\Common::validateAuthCode('wrong-token', $authCodeHash), 'invalid auth code accepted');
check(\Typecho\Common::escape('<script>') === '&lt;script&gt;', 'HTML escaping failed');
check(\Typecho\Common::slugName('中文 测试') === '中文-测试', 'UTF-8 slug generation failed');
check(\Typecho\Common::checkSafeHost('127.0.0.1') === false, 'private IPv4 host accepted');
check(\Typecho\Common::checkSafeHost('localhost') === false, 'localhost accepted');

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10';
$request = \Typecho\Request::getInstance();
check($request->getIp() === '127.0.0.1', 'untrusted forwarded IP was accepted');
unset($_SERVER['HTTP_X_FORWARDED_FOR']);

$webauthn = new \lbuchs\WebAuthn\WebAuthn('TypechoRe', 'localhost', ['none'], true);
$passkeyArgs = $webauthn->getCreateArgs('1', 'admin', 'Admin', 60, 'required', 'required');
check(isset($passkeyArgs->publicKey->challenge, $passkeyArgs->publicKey->user->id), 'WebAuthn create options failed');

$dbFile = tempnam(sys_get_temp_dir(), 'typechore-');
check(false !== $dbFile, 'could not create temporary SQLite file');
try {
    $adapter = new \Typecho\Db\Adapter\SQLite();
    $config = new \Typecho\Config(['file' => $dbFile]);
    $handle = $adapter->connect($config);
    check($adapter->query('CREATE TABLE smoke (id INTEGER PRIMARY KEY, value TEXT)', $handle) instanceof \SQLite3Result, 'SQLite DDL failed');
    check($adapter->query("INSERT INTO smoke (value) VALUES ('ok')", $handle) instanceof \SQLite3Result, 'SQLite insert failed');
    $result = $adapter->query('SELECT value FROM smoke', $handle);
    check($adapter->fetch($result)['value'] === 'ok', 'SQLite fetch failed');
    $handle->close();
} finally {
    @unlink($dbFile);
}

echo "PASS: PHP 8.5 smoke tests\n";
