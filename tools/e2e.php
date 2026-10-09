<?php

declare(strict_types=1);

/**
 * 真实站点端到端回归测试 (E2E)
 *
 * 用发布包在临时目录搭建一个真实站点, 通过 PHP 内置服务器发真实 HTTP 请求,
 * 覆盖: 安装向导、后台全部页面、发布文章、前台文章页、评论提交、图片上传、
 * Sitemap、404、合成 Passkey 注册/签名认证、XML-RPC 开关与请求、1.3.2 升级脚本。
 *
 * 用法:
 *   php tools/e2e.php                      # 自动构建发布包并测试
 *   php tools/e2e.php --zip=dist/x.zip     # 使用已有发布包
 *   php tools/e2e.php --port=8299 --keep   # 保留临时站点便于排查
 *   php tools/e2e.php --php=/usr/bin/php   # 指定 PHP CLI
 *
 * 退出码: 0 = 全部通过, 1 = 失败
 */

const ROOT = __DIR__ . '/..';
require_once ROOT . '/tests/WebAuthnTestFixture.php';

/** 响应体中出现的 PHP 错误标记 */
const BODY_ERROR_MARKERS = [
    '<b>Warning</b>',
    '<b>Notice</b>',
    '<b>Deprecated</b>',
    'Fatal error',
    'Uncaught ',
    'Parse error',
];

/** 服务器日志中出现的 PHP 错误标记 */
const LOG_ERROR_MARKERS = [
    'PHP Warning',
    'PHP Notice',
    'PHP Deprecated',
    'PHP Fatal error',
    'PHP Parse error',
    'Uncaught ',
];

$options = getopt('', ['zip::', 'port::', 'php::', 'keep', 'no-build', 'verbose']);
$verbose = isset($options['verbose']);
$keep = isset($options['keep']);
$port = (int) ($options['port'] ?? 8288);

$passed = 0;

function out(string $message): void
{
    echo $message . "\n";
}

function step(string $message): void
{
    echo "==> {$message}\n";
}

function fail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertTrue(bool $condition, string $message): void
{
    global $passed;
    if (!$condition) {
        fail($message);
    }
    $passed++;
}

/**
 * @param array{status: int, headers: array<string, string>, rawHeaders?: string, body: string, url: string} $response
 */
function assertResponse(array $response, array $allowedStatus, string $label): string
{
    if (!in_array($response['status'], $allowedStatus, true)) {
        $location = $response['headers']['location'] ?? '';
        fail(
            "{$label}: expected HTTP " . implode('/', $allowedStatus) . ", got {$response['status']}"
            . ($location !== '' ? " (Location: {$location})" : '')
            . "\n--- headers ---\n" . trim((string) ($response['rawHeaders'] ?? ''))
            . ($response['body'] !== '' ? "\n--- body ---\n" . substr($response['body'], 0, 500) : '')
        );
    }

    foreach (BODY_ERROR_MARKERS as $marker) {
        if (str_contains($response['body'], $marker)) {
            fail("{$label}: response contains '{$marker}'\n" . substr($response['body'], 0, 1200));
        }
    }

    global $passed;
    $passed++;
    return $response['body'];
}

function findPhp(?string $given): string
{
    $candidates = array_filter([
        $given,
        getenv('PHP_EXE') ?: null,
        is_file(ROOT . '/php-8.5.10/php.exe') ? ROOT . '/php-8.5.10/php.exe' : null,
        is_file(ROOT . '/php-8.5.10/php') ? ROOT . '/php-8.5.10/php' : null,
        'php',
    ]);

    foreach ($candidates as $candidate) {
        $output = [];
        $code = 0;
        exec(escapeshellarg($candidate) . ' -r ' . escapeshellarg('echo PHP_VERSION;') . ' 2>&1', $output, $code);
        if (0 === $code && $output !== []) {
            return $candidate;
        }
    }

    fail('PHP CLI not found, pass --php=<path>');
}

function findPython(): string
{
    foreach (['python', 'python3', 'py'] as $candidate) {
        $output = [];
        $code = 0;
        exec(escapeshellarg($candidate) . ' --version 2>&1', $output, $code);
        if (0 === $code) {
            return $candidate;
        }
    }

    fail('Python 3 not found (needed to build the release package)');
}

function buildPackage(?string $zip): string
{
    if (null !== $zip) {
        $path = str_starts_with($zip, DIRECTORY_SEPARATOR) || preg_match('#^[A-Za-z]:#', $zip)
            ? $zip
            : ROOT . '/' . $zip;
        if (!is_file($path)) {
            fail("package not found: {$path}");
        }
        return $path;
    }

    $python = findPython();
    $output = [];
    $code = 0;
    exec(escapeshellarg($python) . ' ' . escapeshellarg(ROOT . '/tools/build_release.py') . ' 2>&1', $output, $code);
    if (0 !== $code) {
        fail("build_release.py failed:\n" . implode("\n", $output));
    }

    foreach ($output as $line) {
        if (preg_match('#^Built\s+(.+?)\s+\(#', trim($line), $matches)) {
            $path = ROOT . '/' . str_replace('\\', '/', $matches[1]);
            if (is_file($path)) {
                return $path;
            }
        }
    }

    fail("could not parse the built package path:\n" . implode("\n", $output));
}

/**
 * 轻量 HTTP 客户端 (curl + cookie jar)
 */
final class Site
{
    /** @var array<string, string> */
    private array $cookies = [];

    public function __construct(private readonly string $base, private readonly bool $verbose = false)
    {
    }

    /**
     * 解析并保存响应中的 Set-Cookie
     */
    private function storeCookies(string $rawHeaders): void
    {
        if (!preg_match_all('/^Set-Cookie:\s*(.+)$/mi', $rawHeaders, $matches)) {
            return;
        }

        foreach ($matches[1] as $line) {
            $pair = trim(explode(';', $line, 2)[0]);
            if (!str_contains($pair, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $pair, 2);
            $name = trim($name);
            if ($value === '') {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = $value;
            }
        }
    }

    private function cookieHeader(): string
    {
        $pairs = [];
        foreach ($this->cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        return implode('; ', $pairs);
    }

    public function path(string $url): string
    {
        if (str_starts_with($url, $this->base)) {
            return substr($url, strlen($this->base));
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return '/' . ltrim($url, '/');
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, string> $files field => local path
     * @return array{status: int, headers: array<string, string>, body: string, url: string}
     */
    public function request(
        string $method,
        string $path,
        array $fields = [],
        array $files = [],
        array $headers = []
    ): array {
        $url = str_starts_with($path, 'http') ? $path : $this->base . $this->path($path);
        $curl = curl_init($url);

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'TypechoRe-E2E/1.0',
        ];

        $cookieHeader = $this->cookieHeader();
        if ($cookieHeader !== '') {
            $curlOptions[CURLOPT_COOKIE] = $cookieHeader;
        }

        if (strtoupper($method) === 'POST') {
            $curlOptions[CURLOPT_POST] = true;
            if ($files !== []) {
                $postFields = $fields;
                foreach ($files as $field => $file) {
                    $postFields[$field] = new CURLFile($file);
                }
                $curlOptions[CURLOPT_POSTFIELDS] = $postFields;
            } else {
                $curlOptions[CURLOPT_POSTFIELDS] = http_build_query($fields);
            }
        }

        if ($headers !== []) {
            $curlOptions[CURLOPT_HTTPHEADER] = array_map(
                static fn(string $key, string $value): string => "{$key}: {$value}",
                array_keys($headers),
                $headers
            );
        }

        curl_setopt_array($curl, $curlOptions);

        $raw = curl_exec($curl);
        if (false === $raw) {
            $error = curl_error($curl);
            throw new RuntimeException("curl failed for {$url}: {$error}");
        }

        $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        $rawHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        $this->storeCookies($rawHeaders);
        $parsedHeaders = [];

        foreach (preg_split("/\r\n|\n/", $rawHeaders) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $parsedHeaders[strtolower(trim($key))] = trim($value);
            }
        }

        if ($this->verbose) {
            out(sprintf('    %s %s -> %d', $method, $url, $status));
            if ($cookieHeader !== '') {
                out('        cookie: ' . substr($cookieHeader, 0, 160));
            }
            if (preg_match_all('/^Set-Cookie:\s*([^=;]+)=/mi', $rawHeaders, $setCookies)) {
                out('        set-cookie names: ' . implode(', ', $setCookies[1]));
            }
        }

        return [
            'status' => $status,
            'headers' => $parsedHeaders,
            'rawHeaders' => $rawHeaders,
            'body' => $body,
            'url' => $url,
        ];
    }

    public function get(string $path, array $headers = []): array
    {
        return $this->request('GET', $path, [], [], $headers);
    }

    public function post(string $path, array $fields = [], array $headers = []): array
    {
        return $this->request('POST', $path, $fields, [], $headers);
    }

    public function postRaw(string $path, string $body, array $headers = []): array
    {
        $url = str_starts_with($path, 'http') ? $path : $this->base . $this->path($path);
        $curl = curl_init($url);
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'TypechoRe-E2E/1.0',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array_map(
                static fn(string $key, string $value): string => "{$key}: {$value}",
                array_keys($headers),
                $headers
            ),
        ];
        $cookieHeader = $this->cookieHeader();
        if ('' !== $cookieHeader) {
            $curlOptions[CURLOPT_COOKIE] = $cookieHeader;
        }
        curl_setopt_array($curl, $curlOptions);

        $raw = curl_exec($curl);
        if (false === $raw) {
            $error = curl_error($curl);
            throw new RuntimeException("curl failed for {$url}: {$error}");
        }

        $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        $rawHeaders = substr($raw, 0, $headerSize);
        $this->storeCookies($rawHeaders);

        if ($this->verbose) {
            out(sprintf('    POST(raw) %s -> %d', $url, $status));
        }

        return [
            'status' => $status,
            'headers' => [],
            'rawHeaders' => $rawHeaders,
            'body' => substr($raw, $headerSize),
            'url' => $url,
        ];
    }

    /**
     * @param array<string, string> $files
     */
    public function postMultipart(string $path, array $fields, array $files, array $headers = []): array
    {
        return $this->request('POST', $path, $fields, $files, $headers);
    }
}

function csrfToken(string $html): string
{
    if (preg_match('/name="_"\s+value="([0-9a-f]{64})"/i', $html, $matches)) {
        return $matches[1];
    }
    if (preg_match('/[?&]_=([0-9a-f]{64})/i', $html, $matches)) {
        return $matches[1];
    }

    fail("CSRF token not found in response:\n" . substr($html, 0, 800));
}

/**
 * 在临时站点上执行 PHP 片段 (访问该站点的数据库)
 *
 * @param string $phpCode 片段, 已提供 $db 与 $options 变量
 */
function sitePhp(string $php, string $siteDir, string $phpCode): string
{
    $script = "<?php\nrequire " . var_export($siteDir . '/config.inc.php', true) . ";\n"
        . "\$db = \\Typecho\\Db::get();\n"
        . "\$options = \\Widget\\Options::alloc();\n"
        . $phpCode . "\n";
    $tmp = dirname($siteDir) . '/site-check.php';
    // These tiny probes do not measure performance; disable CLI OPcache/JIT to avoid unstable
    // Windows JIT crashes when repeatedly spawning short-lived PHP processes.
    $command = escapeshellarg($php) . ' -d opcache.enable_cli=0 -d opcache.jit=off '
        . escapeshellarg($tmp) . ' 2>&1';

    $attempts = [];
    for ($try = 0; $try < 3; $try++) {
        file_put_contents($tmp, $script);
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if (0 === $exitCode) {
            @unlink($tmp);
            return trim(implode("\n", $output));
        }

        $attempts[] = 'attempt ' . ($try + 1) . " (exit {$exitCode}):\n" . implode("\n", $output);
        usleep(200000);
    }

    @unlink($tmp);
    fail("site PHP snippet failed:\n\n" . implode("\n\n", $attempts) . "\n--- snippet ---\n" . $phpCode);
}

// ---------------------------------------------------------------- setup

$php = findPhp($options['php'] ?? null);
out("PHP: {$php}");
$zip = buildPackage($options['zip'] ?? null);
out("package: " . str_replace(ROOT . '/', '', str_replace('\\', '/', $zip)));

// 显式使用带前缀的目录名 (Windows 上 tempnam() 只保留前 3 个字符, 不安全)
$workDir = (string) ($options['dir'] ?? sys_get_temp_dir() . '/typechore-e2e-' . bin2hex(random_bytes(4)));
if (file_exists($workDir)) {
    fail("work directory already exists: {$workDir} (use --dir=<new path> or remove it)");
}
mkdir($workDir, 0777, true);
$siteDir = $workDir . '/site';

$archive = new ZipArchive();
if (true !== $archive->open($zip)) {
    fail("cannot open package: {$zip}");
}
$archive->extractTo($siteDir);
$archive->close();

$serverOut = $workDir . '/server.out.log';
$serverErr = $workDir . '/server.err.log';
$process = proc_open(
    [
        $php,
        '-S',
        "127.0.0.1:{$port}",
        '-t',
        $siteDir,
        '-d',
        'display_errors=1',
        '-d',
        'error_reporting=E_ALL',
        '-d',
        'log_errors=0',
    ],
    [1 => ['file', $serverOut, 'a'], 2 => ['file', $serverErr, 'a']],
    $pipes,
    $siteDir
);

if (!is_resource($process)) {
    fail('could not start the PHP built-in server');
}

$site = new Site("http://127.0.0.1:{$port}", $verbose);
$cleanup = static function () use ($process, $workDir, $keep): void {
    // 服务器进程在脚本退出前一定还在运行; 用 @ 兜住进程已自行退出等边界情况
    @proc_terminate($process);
    @proc_close($process);

    if (!$keep) {
        $remove = static function (string $dir) use (&$remove): void {
            foreach (scandir($dir) ?: [] as $entry) {
                if ('.' === $entry || '..' === $entry) {
                    continue;
                }
                $path = $dir . '/' . $entry;
                is_dir($path) ? $remove($path) : @unlink($path);
            }
            @rmdir($dir);
        };
        $remove($workDir);
    } else {
        out("site kept at: {$workDir}");
    }
};

register_shutdown_function(static function () use ($cleanup): void {
    $cleanup();
});

// wait for the server
$ready = false;
for ($i = 0; $i < 100; $i++) {
    try {
        $response = $site->get('/install.php');
        if (200 === $response['status']) {
            $ready = true;
            break;
        }
    } catch (Throwable $e) {
        // not ready yet
    }
    usleep(100000);
}
assertTrue($ready, 'the built-in server did not become ready');

// ---------------------------------------------------------------- installer

step('install wizard');
$response = $site->post('/install.php?step=1', ['step' => '1']);
$body = assertResponse($response, [200], 'install step 1');
$json = json_decode($body, true);
assertTrue(is_array($json) && ($json['success'] ?? false) && 2 === ($json['message'] ?? null), 'install step 1 did not advance to step 2');

$dbFile = $siteDir . '/usr/e2e.db';
$response = $site->post('/install.php?step=2', [
    'step' => '2',
    'dbAdapter' => 'Pdo_SQLite',
    'dbNext' => 'none',
    'dbPrefix' => 'typecho_',
    'dbFile' => $dbFile,
]);
$body = assertResponse($response, [200], 'install step 2');
$json = json_decode($body, true);
assertTrue(is_array($json) && ($json['success'] ?? false) && 3 === ($json['message'] ?? null), 'install step 2 did not advance to step 3: ' . $body);
assertTrue(is_file($siteDir . '/config.inc.php'), 'config.inc.php was not created');
assertTrue(is_file($dbFile), 'SQLite database was not created');

$adminUser = 'e2eadmin';
$adminPassword = 'e2e-Passw0rd!';
$response = $site->post('/install.php?step=3', [
    'step' => '3',
    'userUrl' => "http://127.0.0.1:{$port}",
    'userName' => $adminUser,
    'userPassword' => $adminPassword,
    'userMail' => 'e2e@example.test',
]);
$body = assertResponse($response, [200], 'install step 3');
$json = json_decode($body, true);
assertTrue(is_array($json) && ($json['success'] ?? false) && 0 === ($json['message'] ?? null), 'install step 3 did not finish: ' . $body);
out('    installed: ' . $dbFile);

$installedVersion = sitePhp($php, $siteDir, "echo \$options->version;");
assertTrue('1.3.2' === $installedVersion, "installed version should be 1.3.2, got {$installedVersion}");

// ---------------------------------------------------------------- frontend (anonymous)

step('frontend');
$home = assertResponse($site->get('/'), [200], 'GET /');
assertTrue(str_contains($home, '<title>'), 'home page has no <title>');

$notFound = assertResponse($site->get('/index.php/definitely-not-here/'), [404], 'GET unknown URL');
assertTrue(str_contains($notFound, '404') || str_contains($notFound, '没有找到'), '404 page did not render the theme error page');

assertResponse($site->get('/index.php/sitemap.xml'), [200], 'GET sitemap.xml');
assertResponse($site->get('/index.php/feed/'), [200], 'GET feed');

// ---------------------------------------------------------------- admin login

step('admin login');
$loginPage = assertResponse($site->get('/admin/login.php'), [200], 'GET /admin/login.php');
assertTrue(str_contains($loginPage, 'name="name"'), 'login form missing');
assertResponse($site->get('/admin/'), [302], 'GET /admin/ before login');

$loginAction = 'http://127.0.0.1:' . $port . '/admin/login.php';
if (preg_match('/<form[^>]+action="([^"]+)"/', $loginPage, $matches)) {
    $loginAction = html_entity_decode($matches[1]);
}
$response = $site->post(
    $loginAction,
    ['name' => $adminUser, 'password' => $adminPassword, 'referer' => ''],
    ['Referer' => "http://127.0.0.1:{$port}/admin/login.php"]
);
if ($verbose) {
    out('    login action: ' . $loginAction);
    out('    login status: ' . $response['status'] . ' location: ' . ($response['headers']['location'] ?? ''));
}
assertResponse($response, [302], 'POST login');
if (str_contains($response['headers']['location'] ?? '', 'login.php')) {
    $retry = $site->get('/admin/login.php');
    if (preg_match('/notice.*?<[^>]+>(.*?)<\/[a-z]/is', $retry['body'], $notice)) {
        fail('login rejected: ' . trim(strip_tags($notice[1])));
    }
    fail('login rejected (redirected back to login.php)');
}

// ---------------------------------------------------------------- admin pages

step('admin page sweep');
// 首次登录会先跳一次欢迎页 (由 __typecho_first_run cookie 控制)
$firstAdmin = $site->get('/admin/');
if (302 === $firstAdmin['status'] && str_contains($firstAdmin['headers']['location'] ?? '', 'welcome.php')) {
    assertResponse($site->get('/admin/welcome.php'), [200], 'GET welcome.php (first run)');
    assertResponse($site->get('/admin/'), [200], 'GET /admin/ after the first-run welcome');
}
$adminPages = [
    '/admin/' => [200],
    '/admin/index.php' => [200],
    '/admin/write-post.php' => [200],
    '/admin/write-page.php' => [200],
    '/admin/manage-posts.php' => [200],
    '/admin/manage-pages.php' => [200],
    '/admin/manage-comments.php' => [200],
    '/admin/manage-comments.php?status=waiting' => [200],
    '/admin/manage-medias.php' => [200],
    '/admin/manage-categories.php' => [200],
    '/admin/manage-tags.php' => [200],
    '/admin/manage-users.php' => [200],
    '/admin/profile.php' => [200],
    '/admin/themes.php' => [200],
    '/admin/theme-tabs.php' => [200],
    '/admin/theme-editor.php?theme=default&file=index.php' => [200],
    '/admin/options-general.php' => [200],
    '/admin/options-discussion.php' => [200],
    '/admin/options-reading.php' => [200],
    '/admin/options-permalink.php' => [200],
    '/admin/options-theme.php' => [200],
    '/admin/plugins.php' => [200],
    '/admin/backup.php' => [200],
    '/admin/upgrade.php' => [302],
    '/admin/welcome.php' => [200, 302],
    '/admin/preview.php' => [200, 302],
    '/admin/login.php' => [302],
    '/admin/register.php' => [200, 302],
];

foreach ($adminPages as $path => $statuses) {
    $body = assertResponse($site->get($path), $statuses, 'GET ' . $path);
    if ('/admin/' === $path) {
        assertTrue(str_contains($body, '目前有') || str_contains($body, '撰写新文章'), 'dashboard content missing');
    }
}

// keep one token for every protected action (token is not bound to the URL)
$writePage = assertResponse($site->get('/admin/write-post.php'), [200], 'GET write-post for token');
$token = csrfToken($writePage);

step('plugin activate / deactivate');
$pluginsPage = assertResponse($site->get('/admin/plugins.php'), [200], 'GET plugins.php');
assertTrue(str_contains($pluginsPage, 'HelloWorld'), 'HelloWorld plugin not listed as available');
assertResponse(
    $site->get('/index.php/action/plugins-edit?activate=HelloWorld&_=' . urlencode($token)),
    [302],
    'GET activate HelloWorld'
);
assertTrue(
    str_contains(sitePhp($php, $siteDir, "echo (string) \$db->fetchObject(\$db->select('value')->from('table.options')->where('name = ?', 'plugins'))->value;"), 'HelloWorld'),
    'plugin activation was not persisted'
);
$pluginConfigPage = assertResponse(
    $site->get('/admin/options-plugin.php?config=HelloWorld'),
    [200],
    'GET options-plugin.php?config=HelloWorld'
);
assertTrue(str_contains($pluginConfigPage, 'HelloWorld') || str_contains($pluginConfigPage, 'hello'), 'plugin config page is empty');
assertResponse(
    $site->get('/index.php/action/plugins-edit?deactivate=HelloWorld&_=' . urlencode($token)),
    [302],
    'GET deactivate HelloWorld'
);


// ---------------------------------------------------------------- publish a post

step('publish post');
if (!preg_match('/<form[^>]+action="([^"]*contents-post-edit[^"]*)"/', $writePage, $matches)) {
    fail('write-post form action not found');
}
$postAction = html_entity_decode($matches[1]);
if (!preg_match_all('/<input[^>]*name="category\[\]"[^>]*>/is', $writePage, $categoryInputs)) {
    fail('no category checkbox found on write-post.php');
}
assertTrue(preg_match('/value="(\d+)"/', $categoryInputs[0][0], $categoryMatch) === 1, 'category checkbox has no value');

$response = $site->post($postAction, [
    'do' => 'publish',
    'title' => 'E2E 回归文章',
    'slug' => 'e2e-regression-post',
    'text' => '<p>hello e2e</p>',
    'tags' => 'e2e,regression',
    'category' => [$categoryMatch[1]],
    'visibility' => 'publish',
    'allowComment' => '1',
    'allowPing' => '0',
    'allowFeed' => '1',
    'markdown' => '0',
    'trackback' => '',
]);
assertResponse($response, [302], 'POST publish');
assertTrue(
    str_contains($response['headers']['location'] ?? '', 'manage-posts.php'),
    'publish did not redirect to manage-posts.php: ' . ($response['headers']['location'] ?? '')
);

$cid = (int) sitePhp($php, $siteDir, "echo (int) \$db->fetchObject(\$db->select(['MAX(cid)' => 'cid'])->from('table.contents'))->cid;");
assertTrue($cid > 0, 'published post cid not found');

$postBody = assertResponse($site->get("/index.php/archives/{$cid}/"), [200], 'GET published post');
assertTrue(str_contains($postBody, 'E2E 回归文章'), 'published post title missing on the frontend');

$managePosts = assertResponse($site->get('/admin/manage-posts.php'), [200], 'GET manage-posts after publish');
assertTrue(str_contains($managePosts, 'E2E 回归文章'), 'published post missing in the admin list');

// ---------------------------------------------------------------- comment

step('comment (anonymous visitor)');
// 用独立会话模拟未登录访客, 覆盖匿名评论的 CSRF / 反垃圾路径
$guest = new Site("http://127.0.0.1:{$port}", $verbose);
$postUrl = "/index.php/archives/{$cid}/";
$guestPostBody = assertResponse($guest->get($postUrl), [200], 'GET post as guest');
if (!preg_match('/<form[^>]*id="comment-form"[^>]*>/is', $guestPostBody, $formTag)) {
    fail('comment form not found on the post page');
}
if (!preg_match('/action="([^"]+)"/', $formTag[0], $matches)) {
    fail('comment form action not found');
}
$commentAction = html_entity_decode($matches[1]);
$commentsBefore = (int) sitePhp($php, $siteDir, "echo (int) \$db->fetchObject(\$db->select(['COUNT(*)' => 'num'])->from('table.comments'))->num;");

$response = $guest->post($commentAction, [
    'author' => 'E2E 访客',
    'mail' => 'visitor@example.test',
    'url' => '',
    'text' => '这是一条 E2E 回归评论',
    'parent' => '0',
], ['Referer' => "http://127.0.0.1:{$port}{$postUrl}"]);
assertResponse($response, [200, 302], 'POST comment');

$commentsAfter = (int) sitePhp($php, $siteDir, "echo (int) \$db->fetchObject(\$db->select(['COUNT(*)' => 'num'])->from('table.comments'))->num;");
assertTrue($commentsAfter === $commentsBefore + 1, "comment row not inserted ({$commentsBefore} -> {$commentsAfter})");

$approved = sitePhp(
    $php,
    $siteDir,
    "echo (int) \$db->fetchObject(\$db->select(['COUNT(*)' => 'num'])->from('table.comments')->where('author = ? AND status = ?', 'E2E 访客', 'approved'))->num;"
);
assertTrue($approved >= 1, 'comment was not approved');

$postBody = assertResponse($site->get($postUrl), [200], 'GET post with comment');
assertTrue(str_contains($postBody, '这是一条 E2E 回归评论'), 'comment missing on the post page');

// ---------------------------------------------------------------- upload

step('upload image');
$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
);
$pngFile = $workDir . '/e2e-upload.png';
file_put_contents($pngFile, $png);

$response = $site->postMultipart(
    '/index.php/action/upload?_=' . urlencode($token),
    ['cid' => (string) $cid],
    ['file' => $pngFile],
    ['Referer' => "http://127.0.0.1:{$port}/admin/write-post.php"]
);
$body = assertResponse($response, [200], 'POST upload');
$json = json_decode($body, true);
assertTrue(is_array($json) && is_string($json[0] ?? null) && str_ends_with($json[0], '.png'), 'upload did not return the attachment url: ' . $body);
assertTrue(isset($json[1]['cid']) && $json[1]['cid'] > 0, 'upload did not return the attachment cid: ' . $body);
$attachments = (int) sitePhp($php, $siteDir, "echo (int) \$db->fetchObject(\$db->select(['COUNT(*)' => 'num'])->from('table.contents')->where(\"type = ?\", 'attachment'))->num;");
assertTrue($attachments >= 1, 'attachment row not created');

$attachmentCid = (int) sitePhp($php, $siteDir, "echo (int) \$db->fetchObject(\$db->select(['MAX(cid)' => 'cid'])->from('table.contents')->where(\"type = ?\", 'attachment'))->cid;");
assertTrue($attachmentCid > 0, 'attachment cid not found');
$mediaPage = assertResponse($site->get('/admin/media.php?cid=' . $attachmentCid), [200], 'GET media.php?cid=');
assertTrue(str_contains($mediaPage, 'e2e-upload.png'), 'attachment editor does not show the uploaded file name');
assertResponse($site->get('/admin/manage-medias.php'), [200], 'GET manage-medias after upload');

// ---------------------------------------------------------------- passkey

step('passkey registration and assertion');
$profile = assertResponse($site->get('/admin/profile.php'), [200], 'GET profile (passkey section)');
assertTrue(str_contains($profile, 'passkey-management'), 'passkey management section missing in profile');
$passkeyJson = assertResponse(
    $site->get('/index.php/action/passkey?do=create-options&_=' . urlencode($token)),
    [200],
    'GET passkey create-options'
);
$passkey = json_decode($passkeyJson, true);
if (!is_array($passkey) || empty($passkey['success'])) {
    fail('passkey create-options failed: ' . substr($passkeyJson, 0, 300));
}
$createChallengeEncoded = $passkey['options']['publicKey']['challenge'] ?? null;
if (!is_string($createChallengeEncoded)) {
    fail('passkey create-options did not return a challenge');
}
$createChallenge = \TypechoRe\Tests\WebAuthnTestFixture::base64UrlDecode($createChallengeEncoded);
$testPrivateKey = \TypechoRe\Tests\WebAuthnTestFixture::createP256PrivateKey();
$testCredentialId = random_bytes(32);
$testRpId = '127.0.0.1';
$testOrigin = 'https://127.0.0.1';
$createClientData = \TypechoRe\Tests\WebAuthnTestFixture::clientData('webauthn.create', $createChallenge, $testOrigin);
$testAttestation = \TypechoRe\Tests\WebAuthnTestFixture::attestationObject(
    $testPrivateKey,
    $testRpId,
    $testCredentialId
);
$createPayload = json_encode([
    'clientDataJSON' => \TypechoRe\Tests\WebAuthnTestFixture::base64UrlEncode($createClientData),
    'attestationObject' => \TypechoRe\Tests\WebAuthnTestFixture::base64UrlEncode($testAttestation),
    'name' => 'E2E synthetic passkey',
], JSON_THROW_ON_ERROR);
$createResultBody = assertResponse(
    $site->postRaw(
        '/index.php/action/passkey?do=process-create&_=' . urlencode($token),
        $createPayload,
        ['Content-Type' => 'application/json']
    ),
    [200],
    'POST passkey process-create'
);
$createResult = json_decode($createResultBody, true);
assertTrue(is_array($createResult) && ($createResult['success'] ?? false) === true, 'passkey registration failed: ' . $createResultBody);

$adminUid = (int) sitePhp(
    $php,
    $siteDir,
    "echo (int) \$db->fetchObject(\$db->select('uid')->from('table.users')->where('name = ?', " . var_export($adminUser, true) . "))->uid;"
);
$passkeyRows = (int) sitePhp(
    $php,
    $siteDir,
    "echo (int) \$db->fetchObject(\$db->select(['COUNT(*)' => 'num'])->from('table.passkeys')->where('uid = ?', " . $adminUid . "))->num;"
);
assertTrue(1 === $passkeyRows, 'successful passkey registration was not stored in the database');

$passkeyLoginSite = new Site("http://127.0.0.1:{$port}", $verbose);
$getOptionsBody = assertResponse(
    $passkeyLoginSite->get('/index.php/action/passkey?do=get-options'),
    [200],
    'GET passkey get-options'
);
$getOptions = json_decode($getOptionsBody, true);
if (!is_array($getOptions) || empty($getOptions['success'])) {
    fail('passkey get-options failed: ' . substr($getOptionsBody, 0, 300));
}
$assertionChallengeEncoded = $getOptions['options']['publicKey']['challenge'] ?? null;
if (!is_string($assertionChallengeEncoded)) {
    fail('passkey get-options did not return a challenge');
}
$assertionChallenge = \TypechoRe\Tests\WebAuthnTestFixture::base64UrlDecode($assertionChallengeEncoded);
$assertionClientData = \TypechoRe\Tests\WebAuthnTestFixture::clientData('webauthn.get', $assertionChallenge, $testOrigin);
$assertionAuthenticatorData = \TypechoRe\Tests\WebAuthnTestFixture::authenticatorData($testRpId, 0x05, 2);
$assertionSignature = \TypechoRe\Tests\WebAuthnTestFixture::signAssertion(
    $testPrivateKey,
    $assertionClientData,
    $assertionAuthenticatorData
);
$assertionPayload = [
    'id' => \TypechoRe\Tests\WebAuthnTestFixture::base64UrlEncode($testCredentialId),
    'userHandle' => \TypechoRe\Tests\WebAuthnTestFixture::base64UrlEncode((string) $adminUid),
    'clientDataJSON' => \TypechoRe\Tests\WebAuthnTestFixture::base64UrlEncode($assertionClientData),
    'authenticatorData' => \TypechoRe\Tests\WebAuthnTestFixture::base64UrlEncode($assertionAuthenticatorData),
    'signature' => \TypechoRe\Tests\WebAuthnTestFixture::base64UrlEncode($assertionSignature),
    'remember' => false,
];
$wrongHandleAssertion = $assertionPayload;
$wrongHandleAssertion['userHandle'] = \TypechoRe\Tests\WebAuthnTestFixture::base64UrlEncode((string) ($adminUid + 1));
$wrongHandleResultBody = assertResponse(
    $passkeyLoginSite->postRaw(
        '/index.php/action/passkey?do=process-get',
        json_encode($wrongHandleAssertion, JSON_THROW_ON_ERROR),
        ['Content-Type' => 'application/json']
    ),
    [200],
    'POST passkey assertion with wrong userHandle'
);
$wrongHandleResult = json_decode($wrongHandleResultBody, true);
assertTrue(is_array($wrongHandleResult) && ($wrongHandleResult['success'] ?? true) === false, 'passkey assertion with wrong userHandle was accepted');

$forgedAssertion = $assertionPayload;
$forgedSignature = \TypechoRe\Tests\WebAuthnTestFixture::base64UrlDecode($forgedAssertion['signature']);
$forgedSignature[0] = chr(ord($forgedSignature[0]) ^ 1);
$forgedAssertion['signature'] = \TypechoRe\Tests\WebAuthnTestFixture::base64UrlEncode($forgedSignature);
$forgedResultBody = assertResponse(
    $passkeyLoginSite->postRaw(
        '/index.php/action/passkey?do=process-get',
        json_encode($forgedAssertion, JSON_THROW_ON_ERROR),
        ['Content-Type' => 'application/json']
    ),
    [200],
    'POST forged passkey assertion'
);
$forgedResult = json_decode($forgedResultBody, true);
assertTrue(is_array($forgedResult) && ($forgedResult['success'] ?? true) === false, 'forged passkey signature was accepted');

$loginResultBody = assertResponse(
    $passkeyLoginSite->postRaw(
        '/index.php/action/passkey?do=process-get',
        json_encode($assertionPayload, JSON_THROW_ON_ERROR),
        ['Content-Type' => 'application/json']
    ),
    [200],
    'POST valid passkey assertion'
);
$loginResult = json_decode($loginResultBody, true);
assertTrue(is_array($loginResult) && ($loginResult['success'] ?? false) === true, 'valid passkey assertion failed: ' . $loginResultBody);
$passkeyDashboard = assertResponse($passkeyLoginSite->get('/admin/'), [200], 'GET admin after passkey login');
$site = $passkeyLoginSite;
$token = csrfToken($passkeyDashboard);

// ---------------------------------------------------------------- xmlrpc

step('xmlrpc');
assertResponse($site->post('/index.php/action/xmlrpc', []), [404], 'xmlrpc disabled by default');
sitePhp($php, $siteDir, "\$db->query(\$db->update('table.options')->rows(['value' => '1'])->where('name = ?', 'allowXmlRpc'));");

$xmlrpcBody = '<?xml version="1.0"?>'
    . '<methodCall><methodName>system.listMethods</methodName><params></params></methodCall>';
$response = $site->postRaw('/index.php/action/xmlrpc', $xmlrpcBody, ['Content-Type' => 'text/xml']);
$body = assertResponse($response, [200], 'xmlrpc system.listMethods');
assertTrue(str_contains($body, 'system.listMethods'), 'xmlrpc response does not list system.listMethods: ' . substr($body, 0, 300));

// ---------------------------------------------------------------- 1.3.2 upgrade path

step('1.3.2 upgrade path (old site without passkeys table)');
sitePhp(
    $php,
    $siteDir,
    "\$db->query(\$db->update('table.options')->rows(['value' => 'TypechoRe 1.3.1'])->where('name = ?', 'generator'));"
    . "\$db->query('DROP TABLE ' . \$db->getPrefix() . 'passkeys');"
);
assertTrue(
    'MISSING' === sitePhp($php, $siteDir, "try { \$db->fetchObject(\$db->select(['COUNT(*)' => 'num'])->from('table.passkeys')); echo 'PRESENT'; } catch (\\Throwable \$e) { echo 'MISSING'; }"),
    'passkeys table should be dropped for the simulation'
);

$response = $site->get('/admin/');
assertResponse($response, [302], 'GET /admin/ on an old-version site');
assertTrue(
    str_contains($response['headers']['location'] ?? '', 'upgrade.php'),
    'old version did not redirect to upgrade.php: ' . ($response['headers']['location'] ?? '')
);

$upgradePage = assertResponse($site->get('/admin/upgrade.php'), [200], 'GET upgrade.php');
if (!preg_match('/<form[^>]+action="([^"]*upgrade[^"]*)"/', $upgradePage, $matches)) {
    fail('upgrade form action not found');
}
$upgradeAction = html_entity_decode($matches[1]);
$response = $site->post($upgradeAction, []);
assertResponse($response, [200, 302], 'POST upgrade');

$upgradedVersion = sitePhp($php, $siteDir, "echo \$options->version;");
assertTrue('1.3.2' === $upgradedVersion, "upgrade did not set version 1.3.2, got {$upgradedVersion}");
assertTrue(
    'PRESENT' === sitePhp($php, $siteDir, "try { \$db->fetchObject(\$db->select(['COUNT(*)' => 'num'])->from('table.passkeys')); echo 'PRESENT'; } catch (\\Throwable \$e) { echo 'MISSING'; }"),
    'upgrade v1_3_2 did not create the passkeys table'
);

$passkeyJson = assertResponse(
    $site->get('/index.php/action/passkey?do=create-options&_=' . urlencode($token)),
    [200],
    'passkey create-options after upgrade'
);
assertTrue(str_contains($passkeyJson, 'challenge'), 'passkey create-options failed after the upgrade');

// ---------------------------------------------------------------- logout

step('logout');
$logout = assertResponse($site->get('/index.php/action/logout?_=' . urlencode($token)), [200, 302], 'GET logout');
assertResponse($site->get('/admin/'), [302], 'GET /admin/ after logout');

// ---------------------------------------------------------------- server log

step('server log');
$log = @file_get_contents($serverErr) ?: '';
foreach (LOG_ERROR_MARKERS as $marker) {
    if (str_contains($log, $marker)) {
        $lines = array_values(array_filter(
            explode("\n", $log),
            static fn(string $line): bool => str_contains($line, $marker)
        ));
        fail("server log contains '{$marker}':\n" . implode("\n", array_slice($lines, 0, 10)));
    }
}
$passed++;

out('');
out("PASS: end-to-end site test ({$passed} checks)");
