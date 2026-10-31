<?php

namespace Typecho;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class PageCache
{
    private const TTL = 60;
    private const LIMIT = 200;
    private const MAX_SIZE = 524288;

    public static function eligible(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
            && empty($_GET)
            && empty($_SERVER['QUERY_STRING'])
            && parse_url($uri, PHP_URL_QUERY) === null
            && !self::hasStateCookie()
            && ($_SERVER['HTTP_AUTHORIZATION'] ?? '') === ''
            && ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '') === ''
            && !preg_match('~/(?:admin|action|feed|sitemap)(?:/|\.|$)~i', $path)
            && !self::rendersWholePage();
    }

    /**
     * 只认 Typecho/会话自己的 cookie, 否则任何一个统计或 CDN cookie 都会关掉缓存
     */
    private static function hasStateCookie(): bool
    {
        $sessionName = (string) session_name();
        $prefix = Cookie::getPrefix();

        foreach ([$_SERVER['HTTP_COOKIE'] ?? '', $_SERVER['REDIRECT_HTTP_COOKIE'] ?? '', implode(';', array_keys($_COOKIE))] as $header) {
            foreach (explode(';', (string) $header) as $pair) {
                $name = trim(explode('=', $pair, 2)[0]);

                if ($name !== '' && (($sessionName !== '' && $name === $sessionName)
                    || ($prefix !== '' && str_starts_with($name, $prefix))
                    || str_starts_with($name, '__typecho_') || str_starts_with($name, 'protectPassword_'))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 挂了整页钩子的插件可以任意改写输出, 不缓存; 只钩子内容的插件对每个访客输出相同
     */
    private static function rendersWholePage(): bool
    {
        foreach (array_keys(Plugin::export()['handles'] ?? []) as $handle) {
            if (strtolower(ltrim((string) $handle, '\\')) === 'index.php') {
                return true;
            }
        }

        return false;
    }

    public static function key(string $generation, string $rootUrl, ?string $theme, ?string $language): string
    {
        // shortcut: add request headers here if the active theme varies output by them.
        return hash('sha256', implode("\n", [
            'private-cookie-fix-v2',
            $_SERVER['HTTP_HOST'] ?? '',
            $_SERVER['REQUEST_URI'] ?? '/',
            $rootUrl,
            $theme ?? '',
            $language ?? '',
            $generation,
        ]));
    }

    public static function get(string $key): ?string
    {
        $dir = self::directory();
        if ($dir === null) {
            return null;
        }
        $file = $dir . '/' . $key;
        $entry = @unserialize((string) @file_get_contents($file), ['allowed_classes' => false]);
        if (!is_array($entry) || ($entry['expires'] ?? 0) < time() || !is_string($entry['body'] ?? null)) {
            @unlink($file);
            return null;
        }
        return $entry['body'];
    }

    public static function put(string $key, string $body, int $ttl = self::TTL): bool
    {
        $dir = self::directory();
        if ($dir === null || strlen($body) > self::MAX_SIZE) {
            return false;
        }
        $temp = @tempnam($dir, '.tmp-');
        if ($temp === false) {
            return false;
        }
        @chmod($temp, 0600);
        $written = @file_put_contents($temp, serialize([
            'expires' => time() + max(1, min(86400, $ttl)),
            'body' => $body,
        ]), LOCK_EX) !== false
            && @rename($temp, $dir . '/' . $key);
        if (!$written) {
            @unlink($temp);
            return false;
        }
        self::cleanup($dir);
        return true;
    }

    public static function generation(): string
    {
        $dir = self::directory();
        if ($dir === null) {
            return '';
        }

        $generation = @file_get_contents($dir . '/generation');
        if (is_string($generation) && $generation !== '') {
            return $generation;
        }

        // 文件丢了就建一个新的随机 token: 计数器从 1 重新数会让旧条目重新生效
        return self::refresh($dir . '/generation', false);
    }

    public static function invalidate(): void
    {
        $dir = self::directory();
        if ($dir !== null) {
            self::refresh($dir . '/generation', true);
        }
    }

    /**
     * 写入新的 generation token, 返回生效的那个
     */
    private static function refresh(string $file, bool $force): string
    {
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return '';
        }

        @chmod($file, 0600);
        $generation = '';

        if (@flock($fp, LOCK_EX)) {
            $generation = trim((string) stream_get_contents($fp));

            if ($force || $generation === '') {
                $generation = bin2hex(random_bytes(16));
                rewind($fp);
                ftruncate($fp, 0);
                fwrite($fp, $generation);
                fflush($fp);
            }

            flock($fp, LOCK_UN);
        }

        fclose($fp);
        return $generation;
    }

    private static function directory(): ?string
    {
        // shortcut: without POSIX, use one FPM owner per cache path; split paths for multiple pools.
        $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
        $dir = defined('__TYPECHO_PAGE_CACHE_DIR__')
            ? __TYPECHO_PAGE_CACHE_DIR__
            : sys_get_temp_dir() . '/typechore-page-cache-' . hash('sha256', __TYPECHO_ROOT_DIR__ . ':' . ($uid ?? 'unknown'));
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return null;
        }
        if (is_link($dir) || ($uid !== null && @fileowner($dir) !== $uid)) {
            return null;
        }
        @chmod($dir, 0700);
        return is_writable($dir) && (@fileperms($dir) & 0077) === 0 ? $dir : null;
    }

    private static function cleanup(string $dir): void
    {
        $files = glob($dir . '/[a-f0-9]*') ?: [];
        if (count($files) <= self::LIMIT * 2) {
            return;
        }

        $ages = [];
        foreach ($files as $file) {
            $ages[$file] = @filemtime($file) ?: 0;
        }
        asort($ages);

        foreach (array_slice(array_keys($ages), 0, count($ages) - self::LIMIT) as $file) {
            @unlink($file);
        }
    }
}
