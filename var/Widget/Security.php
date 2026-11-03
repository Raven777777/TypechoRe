<?php

namespace Widget;

use Typecho\Common;
use Typecho\Response;
use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 安全选项组件
 *
 * @link typecho
 * @package Widget
 * @copyright Copyright (c) 2014 Typecho team (http://typecho.org)
 * @license GNU General Public License 2.0
 */
class Security extends Base
{
    /** token 有效期, 同时接受上一窗口以容忍跨窗口提交 */
    private const TOKEN_TTL = 3600;

    /**
     * @var string
     */
    private string $token;

    /**
     * @var boolean
     */
    private bool $enabled = true;

    /**
     * @param int $components
     */
    #[\Override]
    public function initComponents(int &$components)
    {
        $components = self::INIT_OPTIONS | self::INIT_USER;
    }

    /**
     * 初始化函数
     */
    #[\Override]
    public function execute()
    {
        $this->token = $this->options->secret;
        if ($this->user->hasLogin()) {
            $this->token .= '&' . $this->user->authCode . '&' . $this->user->uid;
        }
    }

    /**
     * @param bool $enabled
     */
    public function enable(bool $enabled = true)
    {
        $this->enabled = $enabled;
    }

    /**
     * 保护提交数据
     */
    public function protect()
    {
        $provided = $this->request->get('_');

        if ($this->enabled && (!is_string($provided) || !$this->verifyToken($provided, $this->currentPath()))) {
            $this->response->goBack();
        }
    }

    /**
     * 获取token
     *
     * token 由服务端可知的 secret / authCode / uid 派生, 并绑定到提交目标路径、
     * 查询参数 (动作和记录 id) 以及时间窗口, 因此泄漏的 token 只对同一个动作和
     * 记录有效, 并且会过期.
     *
     * 绑定的是请求行中的信息而不是 Referer: Referer 可能被 CSP / 隐私策略剥离,
     * 那会让整个后台无法提交.
     *
     * @param string|null $suffix 绑定的地址 (路径和查询串), null 表示当前请求
     * @return string
     */
    public function getToken(?string $suffix): string
    {
        return $this->tokenFor($suffix ?? $this->currentPath(), intdiv(time(), self::TOKEN_TTL));
    }

    private function tokenFor(string $suffix, int $window): string
    {
        return hash('sha256', $this->token . '&csrf&' . $this->normalizeTokenPath($suffix) . '&' . $window);
    }

    private function verifyToken(string $provided, string $suffix): bool
    {
        $window = intdiv(time(), self::TOKEN_TTL);

        return hash_equals($this->tokenFor($suffix, $window), $provided)
            || hash_equals($this->tokenFor($suffix, $window - 1), $provided);
    }

    /**
     * 当前请求的路径和查询串, 用于 token 校验
     */
    private function currentPath(): string
    {
        return $this->request->getRequestUri() ?? '/';
    }

    /**
     * 把地址规整成「相对站点根目录、去掉入口脚本的路径 + 排序后的查询串」
     *
     * 生成侧 (getTokenUrl 收到的 action 地址) 与校验侧 (REQUEST_URI) 必须
     * 得到完全相同的结果, 否则合法表单会被拒绝. 处理三种差异:
     * - 绝对地址 / 带查询串的地址 -> 只取 path 和 query
     * - 子目录安装 -> 去掉 rootUrl 的路径前缀
     * - 无 rewrite 时的 /index.php/... -> 去掉入口脚本
     */
    private function normalizeTokenPath(?string $path): string
    {
        if (null === $path || '' === $path) {
            return '/';
        }

        $parts = parse_url($path);
        $path = is_array($parts) && isset($parts['path']) ? $parts['path'] : '';

        if ('' === $path) {
            return '/';
        }

        $rootPath = (string) parse_url((string) $this->options->rootUrl, PHP_URL_PATH);
        $rootPath = rtrim($rootPath, '/');

        if ('' !== $rootPath) {
            if (0 === strpos($path, $rootPath . '/')) {
                $path = substr($path, strlen($rootPath));
            } elseif ($path === $rootPath) {
                $path = '/';
            }
        }

        $path = preg_replace('#^/index\.php(?=/|$)#i', '', $path) ?? $path;

        $query = [];
        if (is_array($parts) && !empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        unset($query['_']);
        ksort($query);
        $queryString = http_build_query($query);

        return '/' . ltrim($path, '/') . ('' === $queryString ? '' : '?' . $queryString);
    }

    /**
     * 获取绝对路由路径
     *
     * @param string|null $path
     * @return string
     */
    public function getRootUrl(?string $path): string
    {
        return $this->getTokenUrl(Common::url($path, $this->options->rootUrl));
    }

    /**
     * 生成带token的路径
     *
     * @param $path
     * @param string|null $url
     * @return string
     */
    public function getTokenUrl($path, ?string $url = null): string
    {
        $parts = parse_url($path);
        $params = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $params);
        }

        // 默认绑定到提交目标自身; $url 用于少数需要显式指定绑定地址的场景
        $params['_'] = $this->getToken($url ?? (string) $path);
        $parts['query'] = http_build_query($params);

        return Common::buildUrl($parts);
    }

    /**
     * 输出后台安全路径
     *
     * @param $path
     */
    public function adminUrl($path)
    {
        echo $this->getAdminUrl($path);
    }

    /**
     * 获取安全的后台路径
     *
     * @param string $path
     * @return string
     */
    public function getAdminUrl(string $path): string
    {
        return $this->getTokenUrl(Common::url($path, $this->options->adminUrl));
    }

    /**
     * 输出安全的路由路径
     *
     * @param $path
     */
    public function index($path)
    {
        echo $this->getIndex($path);
    }

    /**
     * 获取安全的路由路径
     *
     * @param $path
     * @return string
     */
    public function getIndex($path): string
    {
        return $this->getTokenUrl(Common::url($path, $this->options->index));
    }
}
 
