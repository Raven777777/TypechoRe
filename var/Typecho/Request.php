<?php

namespace Typecho;

/**
 * 服务器请求处理类
 *
 * @package Request
 */
class Request
{
    /**
     * 单例句柄
     *
     * @access private
     * @var Request
     */
    private static Request $instance;

    /**
     * 沙箱参数
     *
     * @access private
     * @var Config|null
     */
    private ?Config $sandbox;

    /**
     * 用户参数
     *
     * @access private
     * @var Config|null
     */
    private ?Config $params;

    /**
     * 路径信息
     *
     * @access private
     * @var string|null
     */
    private ?string $pathInfo = null;

    /**
     * requestUri
     *
     * @var string|null
     * @access private
     */
    private ?string $requestUri = null;

    /**
     * requestRoot
     *
     * @var string|null
     * @access private
     */
    private ?string $requestRoot = null;

    /**
     * 获取baseurl
     *
     * @var string|null
     * @access private
     */
    private ?string $baseUrl = null;

    /**
     * 客户端ip地址
     *
     * @access private
     * @var string|null
     */
    private ?string $ip = null;

    /**
     * 域名前缀
     *
     * @var string|null
     */
    private ?string $urlPrefix = null;

    /**
     * 获取单例句柄
     *
     * @access public
     * @return Request
     */
    public static function getInstance(): Request
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 初始化变量
     *
     * @return $this
     */
    public function beginSandbox(Config $sandbox): Request
    {
        $this->sandbox = $sandbox;
        return $this;
    }

    /**
     * @return $this
     */
    public function endSandbox(): Request
    {
        $this->sandbox = null;
        return $this;
    }

    /**
     * @param Config $params
     * @return $this
     */
    public function proxy(Config $params): Request
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return $this
     */
    public function endProxy(): Request
    {
        if (isset($this->params)) {
            $this->params = null;
        }

        return $this;
    }

    /**
     * 获取实际传递参数
     *
     * @param string $key 指定参数
     * @param mixed $default 默认参数 (default: NULL)
     * @param bool|null $exists detect exists
     * @return mixed
     */
    public function get(string $key, $default = null, ?bool &$exists = true)
    {
        $value = null;

        switch (true) {
            case isset($this->params) && isset($this->params[$key]):
                $value = $this->params[$key];
                break;
            case isset($this->sandbox):
                if (isset($this->sandbox[$key])) {
                    $value = $this->sandbox[$key];
                }
                break;
            case $key === '@json':
                if ($this->isJson()) {
                    $body = file_get_contents('php://input');

                    if (false !== $body) {
                        $value = json_decode($body, true, 16);
                        $default = $default ?? $value;
                    }
                }
                break;
            case isset($_GET[$key]):
                $value = $_GET[$key];
                break;
            case isset($_POST[$key]):
                $value = $_POST[$key];
                break;
            default:
                break;
        }

        if (isset($value) && $value !== '') {
            $exists = true;
            if (is_array($default) == is_array($value)) {
                return $value;
            } else {
                return $default;
            }
        } else {
            $exists = false;
            return $default;
        }
    }

    /**
     * 获取实际传递参数(magic)
     *
     * @deprecated ^1.3.0
     * @param string $key 指定参数
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * 判断参数是否存在
     *
     * @deprecated ^1.3.0
     * @param string $key 指定参数
     * @return boolean
     */
    public function __isset(string $key)
    {
        $this->get($key, null, $exists);
        return $exists;
    }

    /**
     * 获取一个数组
     *
     * @param $key
     * @return array
     */
    public function getArray($key): array
    {
        $result = $this->get($key, [], $exists);

        if (!empty($result) || !$exists) {
            return $result;
        }

        return [$this->get($key)];
    }

    /**
     * 从参数列表指定的值中获取http传递参数
     *
     * @param mixed $params 指定的参数
     * @return array
     */
    public function from($params): array
    {
        $result = [];
        $args = is_array($params) ? $params : func_get_args();

        foreach ($args as $arg) {
            $result[$arg] = $this->get($arg);
        }

        return $result;
    }

    /**
     * getRequestRoot
     *
     * @return string
     */
    public function getRequestRoot(): string
    {
        if (null === $this->requestRoot) {
            $root = rtrim($this->getUrlPrefix() . $this->getBaseUrl(), '/') . '/';

            $pos = strrpos($root, '.php/');
            if ($pos) {
                $root = dirname(substr($root, 0, $pos));
            }

            $this->requestRoot = rtrim($root, '/');
        }

        return $this->requestRoot;
    }

    /**
     * 获取当前请求url
     *
     * @return string
     */
    public function getRequestUrl(): string
    {
        return $this->getUrlPrefix() . $this->getRequestUri();
    }

    /**
     * 根据当前uri构造指定参数的uri
     *
     * @param mixed $parameter 指定的参数
     * @return string
     */
    public function makeUriByRequest($parameter = null): string
    {
        /** 初始化地址 */
        $requestUri = $this->getRequestUrl();
        $parts = parse_url($requestUri);

        /** 初始化参数 */
        if (is_string($parameter)) {
            parse_str($parameter, $args);
        } elseif (is_array($parameter)) {
            $args = $parameter;
        } else {
            return $requestUri;
        }

        /** 构造query */
        if (isset($parts['query'])) {
            parse_str($parts['query'], $currentArgs);
            $args = array_merge($currentArgs, $args);
        }
        $parts['query'] = http_build_query($args);

        /** 返回地址 */
        return Common::buildUrl($parts);
    }

    /**
     * 获取当前pathinfo
     *
     * @return string
     */
    public function getPathInfo(): ?string
    {
        /** 缓存信息 */
        if (null !== $this->pathInfo) {
            return $this->pathInfo;
        }

        //参考Zend Framework对pathinfo的处理, 更好的兼容性
        $pathInfo = null;

        //处理requestUri
        $requestUri = $this->getRequestUri();
        $finalBaseUrl = $this->getBaseUrl();

        // Remove the query string from REQUEST_URI
        // 注意必须用 false !== 判断: strpos() 在 '?' 位于首位时返回 0
        if (false !== ($pos = strpos($requestUri, '?'))) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        if (
            (null !== $finalBaseUrl)
            && (false === ($pathInfo = substr($requestUri, strlen($finalBaseUrl))))
        ) {
            // If substr() returns false then PATH_INFO is set to an empty string
            $pathInfo = '/';
        } elseif (null === $finalBaseUrl) {
            $pathInfo = $requestUri;
        }

        if (!empty($pathInfo)) {
            //针对iis的utf8编码做强制转换
            $pathInfo = defined('__TYPECHO_PATHINFO_ENCODING__') ?
                mb_convert_encoding($pathInfo, 'UTF-8', __TYPECHO_PATHINFO_ENCODING__) : $pathInfo;
        } else {
            $pathInfo = '/';
        }

        // fix issue 456
        return ($this->pathInfo = '/' . ltrim(urldecode($pathInfo), '/'));
    }

    /**
     * 获取请求的内容类型
     *
     * @return string|null
     */
    public function getContentType(): ?string
    {
        return $this->getHeader('Content-Type');
    }

    /**
     * 获取环境变量
     *
     * @param string $name 获取环境变量名
     * @param string|null $default
     * @return string|null
     */
    public function getServer(string $name, ?string $default = null): ?string
    {
        return $_SERVER[$name] ?? $default;
    }

    /**
     * 获取ip地址
     *
     * @return string
     */
    public function getIp(): string
    {
        if (null === $this->ip) {
            $remoteAddr = (string)($this->getServer('REMOTE_ADDR') ?? '');
            $trusted = self::getTrustedProxies();

            $header = defined('__TYPECHO_IP_SOURCE__') ? __TYPECHO_IP_SOURCE__ : 'X-Forwarded-For';
            $forwarded = (string)($this->getHeader($header, $this->getHeader('Client-Ip')) ?? '');

            $ip = null;

            // 只有在配置了可信代理且对端确实是代理时, 才采信转发头;
            // 否则任何人都能用 X-Forwarded-For 伪造来源, 绕过评论频率限制与 IP 黑名单
            if (!empty($trusted) && self::isTrustedProxy($remoteAddr, $trusted)) {
                // XFF 形如 "client, proxy1, proxy2": 自右向左跳过可信代理,
                // 第一个不可信地址才是真实客户端
                $ip = self::firstUntrustedIp($forwarded, $trusted);
            } elseif (empty($trusted)) {
                // 未配置可信代理: 保持原有行为 (取最左侧), 但跳过非法片段
                foreach (explode(',', $forwarded) as $candidate) {
                    $ip = self::normalizeIp($candidate);
                    if (null !== $ip) {
                        break;
                    }
                }
            }

            if (null === $ip) {
                $ip = self::normalizeIp($remoteAddr);
            }

            $this->ip = $ip ?? 'unknown';
        }

        return $this->ip;
    }

    /**
     * 读取受信任代理列表
     *
     * 通过 __TYPECHO_TRUSTED_PROXIES__ 配置, 支持 IP 或 CIDR, 逗号分隔.
     *
     * @return string[]
     */
    private static function getTrustedProxies(): array
    {
        static $trusted = null;

        if (null !== $trusted) {
            return $trusted;
        }

        $trusted = [];

        if (defined('__TYPECHO_TRUSTED_PROXIES__') && is_string(__TYPECHO_TRUSTED_PROXIES__)) {
            foreach (explode(',', __TYPECHO_TRUSTED_PROXIES__) as $item) {
                $item = strtolower(trim($item));
                if ('' !== $item) {
                    $trusted[] = $item;
                }
            }
        }

        return $trusted;
    }

    /**
     * 自右向左解析转发链, 返回第一个不在可信列表中的地址
     *
     * @param string $list
     * @param string[] $trusted
     * @return string|null
     */
    private static function firstUntrustedIp(string $list, array $trusted): ?string
    {
        $result = null;

        foreach (array_reverse(explode(',', $list)) as $candidate) {
            $ip = self::normalizeIp($candidate);

            if (null === $ip) {
                continue;
            }

            // 循环结束时保留的是最左侧地址, 用于整条链都是可信代理的情况
            $result = $ip;

            if (!self::isTrustedProxy($ip, $trusted)) {
                return $ip;
            }
        }

        return $result;
    }

    /**
     * 规范化并校验 IP
     *
     * @param string $value
     * @return string|null
     */
    private static function normalizeIp(string $value): ?string
    {
        $value = trim($value, " \t\n\r\0\x0B[]\"'");

        if ('' === $value) {
            return null;
        }

        // IPv4 可能携带端口 (1.2.3.4:5678), IPv6 的冒号不作处理
        if (1 === substr_count($value, ':') && false !== strpos($value, '.')) {
            $value = (string)strstr($value, ':', true);
        }

        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)
            ? $value
            : null;
    }

    /**
     * @param string $ip
     * @param string[] $trusted
     * @return bool
     */
    private static function isTrustedProxy(string $ip, array $trusted): bool
    {
        if (empty($trusted)) {
            return false;
        }

        $ip = strtolower(trim($ip));

        if (in_array($ip, $trusted, true)) {
            return true;
        }

        foreach ($trusted as $cidr) {
            if (false !== strpos($cidr, '/') && self::ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断 IP 是否落在 CIDR 网段内, 同时支持 IPv4 / IPv6
     *
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);

        if (2 !== count($parts)) {
            return false;
        }

        [$subnet, $bits] = $parts;
        $bits = (int)$bits;

        if ($bits < 0 || $bits > 128) {
            return false;
        }

        $isIpv4 = false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && false !== filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);

        if ($isIpv4) {
            if ($bits > 32) {
                return false;
            }

            if (0 === $bits) {
                return true;
            }

            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);

            if (false === $ipLong || false === $subnetLong) {
                return false;
            }

            $mask = -1 << (32 - $bits);

            return ($ipLong & $mask) === ($subnetLong & $mask);
        }

        $isIpv6 = false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && false !== filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

        if (!$isIpv6) {
            return false;
        }

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if (false === $ipBin || false === $subnetBin || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $remainBits = $bits % 8;

        if ($fullBytes > 0 && 0 !== strncmp($ipBin, $subnetBin, $fullBytes)) {
            return false;
        }

        if ($remainBits > 0) {
            $mask = chr(0xFF << (8 - $remainBits) & 0xFF);

            if (($ipBin[$fullBytes] & $mask) !== ($subnetBin[$fullBytes] & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * get header value
     *
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public function getHeader(string $key, ?string $default = null): ?string
    {
        $key = strtoupper(str_replace('-', '_', $key));
        
        // Content-Type 和 Content-Length 这两个 header 还需要从不带 HTTP_ 的 key 尝试获取
        if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
            $default = $this->getServer($key, $default);
        }

        return $this->getServer('HTTP_' . $key, $default);
    }

    /**
     * 获取客户端
     *
     * @return string
     */
    public function getAgent(): ?string
    {
        return $this->getHeader('User-Agent');
    }

    /**
     * 获取客户端
     *
     * @return string|null
     */
    public function getReferer(): ?string
    {
        return $this->getHeader('Referer');
    }

    /**
     * 判断是否为https
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && !strcasecmp('https', $_SERVER['HTTP_X_FORWARDED_PROTO']))
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && !strcasecmp('quic', $_SERVER['HTTP_X_FORWARDED_PROTO']))
            || (!empty($_SERVER['HTTP_X_FORWARDED_PORT']) && 443 == $_SERVER['HTTP_X_FORWARDED_PORT'])
            || (!empty($_SERVER['HTTPS']) && 'off' != strtolower($_SERVER['HTTPS']))
            || (!empty($_SERVER['SERVER_PORT']) && 443 == $_SERVER['SERVER_PORT'])
            || (defined('__TYPECHO_SECURE__') && __TYPECHO_SECURE__);
    }

    /**
     * @return bool
     */
    public function isCli(): bool
    {
        return php_sapi_name() == 'cli';
    }

    /**
     * 判断是否为get方法
     *
     * @return boolean
     */
    public function isGet(): bool
    {
        return 'GET' == $this->getServer('REQUEST_METHOD');
    }

    /**
     * 判断是否为post方法
     *
     * @return boolean
     */
    public function isPost(): bool
    {
        return 'POST' == $this->getServer('REQUEST_METHOD');
    }

    /**
     * 判断是否为put方法
     *
     * @return boolean
     */
    public function isPut(): bool
    {
        return 'PUT' == $this->getServer('REQUEST_METHOD');
    }

    /**
     * 判断是否为ajax
     *
     * @return boolean
     */
    public function isAjax(): bool
    {
        return 'XMLHttpRequest' == $this->getHeader('X-Requested-With');
    }

    /**
     * 判断是否为Json请求
     *
     * @return bool
     */
    public function isJson(): bool
    {
        return !!preg_match(
            "/^\s*application\/json(;|$)/i",
            $this->getContentType() ?? ''
        );
    }

    /**
     * 判断输入是否满足要求
     *
     * @param mixed $query 条件
     * @return boolean
     */
    public function is($query): bool
    {
        $validated = false;

        /** 解析串 */
        if (is_string($query)) {
            parse_str($query, $params);
        } elseif (is_array($query)) {
            $params = $query;
        }

        /** 验证串 */
        if (!empty($params)) {
            $validated = true;
            foreach ($params as $key => $val) {
                $param = $this->get($key, null, $exists);
                $validated = empty($val) ? $exists : ($val == $param);

                if (!$validated) {
                    break;
                }
            }
        }

        return $validated;
    }

    /**
     * 获取请求资源地址
     *
     * @return string|null
     */
    public function getRequestUri(): ?string
    {
        if (!empty($this->requestUri)) {
            return $this->requestUri;
        }

        //处理requestUri
        $requestUri = '/';

        if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // check this first so IIS will catch
            $requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (
            // IIS7 with URL Rewrite: make sure we get the unencoded url (double slash problem)
            isset($_SERVER['IIS_WasUrlRewritten'])
            && $_SERVER['IIS_WasUrlRewritten'] == '1'
            && isset($_SERVER['UNENCODED_URL'])
            && $_SERVER['UNENCODED_URL'] != ''
        ) {
            $requestUri = $_SERVER['UNENCODED_URL'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
            $parts = @parse_url($requestUri);

            if (isset($_SERVER['HTTP_HOST']) && strstr($requestUri, $_SERVER['HTTP_HOST'])) {
                if (false !== $parts) {
                    $requestUri = (empty($parts['path']) ? '' : $parts['path'])
                        . ((empty($parts['query'])) ? '' : '?' . $parts['query']);
                }
            } elseif (!empty($_SERVER['QUERY_STRING']) && empty($parts['query'])) {
                // fix query missing
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0, PHP as CGI
            $requestUri = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
        }

        return $this->requestUri = $requestUri;
    }

    /**
     * 获取url前缀
     *
     * @return string|null
     */
    public function getUrlPrefix(): ?string
    {
        if (empty($this->urlPrefix)) {
            if (defined('__TYPECHO_URL_PREFIX__')) {
                $this->urlPrefix = __TYPECHO_URL_PREFIX__;
            } elseif (php_sapi_name() != 'cli') {
                $this->urlPrefix = ($this->isSecure() ? 'https' : 'http') . '://'
                    . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME']);
            }
        }

        return $this->urlPrefix;
    }

    /**
     * getBaseUrl
     *
     * @return string
     */
    private function getBaseUrl(): ?string
    {
        if (null !== $this->baseUrl) {
            return $this->baseUrl;
        }

        //处理baseUrl
        $filename = (isset($_SERVER['SCRIPT_FILENAME'])) ? basename($_SERVER['SCRIPT_FILENAME']) : '';

        if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $filename) {
            $baseUrl = $_SERVER['SCRIPT_NAME'];
        } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $filename) {
            $baseUrl = $_SERVER['PHP_SELF'];
        } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $filename) {
            $baseUrl = $_SERVER['ORIG_SCRIPT_NAME']; // 1and1 shared hosting compatibility
        } else {
            // Backtrack up the script_filename to find the portion matching
            // php_self
            $path = $_SERVER['PHP_SELF'] ?? '';
            $file = $_SERVER['SCRIPT_FILENAME'] ?? '';
            $segs = explode('/', trim($file, '/'));
            $segs = array_reverse($segs);
            $index = 0;
            $last = count($segs);
            $baseUrl = '';
            do {
                $seg = $segs[$index];
                $baseUrl = '/' . $seg . $baseUrl;
                ++$index;
            } while (($last > $index) && (false !== ($pos = strpos($path, $baseUrl))) && (0 != $pos));
        }

        // Does the baseUrl have anything in common with the request_uri?
        $finalBaseUrl = null;
        $requestUri = $this->getRequestUri();

        if (0 === strpos($requestUri, $baseUrl)) {
            // full $baseUrl matches
            $finalBaseUrl = $baseUrl;
        } elseif (0 === strpos($requestUri, dirname($baseUrl))) {
            // directory portion of $baseUrl matches
            $finalBaseUrl = rtrim(dirname($baseUrl), '/');
        } elseif (!strpos($requestUri, basename($baseUrl))) {
            // no match whatsoever; set it blank
            $finalBaseUrl = '';
        } elseif (
            (strlen($requestUri) >= strlen($baseUrl))
            && ((false !== ($pos = strpos($requestUri, $baseUrl))) && ($pos !== 0))
        ) {
            // If using mod_rewrite or ISAPI_Rewrite strip the script filename
            // out of baseUrl. $pos !== 0 makes sure it is not matching a value
            // from PATH_INFO or QUERY_STRING
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }

        return ($this->baseUrl = (null === $finalBaseUrl) ? rtrim($baseUrl, '/') : $finalBaseUrl);
    }
}
