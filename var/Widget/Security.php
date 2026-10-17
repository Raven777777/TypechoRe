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

        if ($this->enabled && (!is_string($provided) || !hash_equals($this->getToken($this->currentPath()), $provided))) {
            $this->response->goBack();
        }
    }

    /**
     * 获取token
     *
     * token 由服务端可知的 secret / authCode / uid 派生, 并绑定到提交目标路径,
     * 因此一个泄漏的 token 只对生成它的那个端点有效 (例如后台页面 URL 出现在
     * Referer 或访问日志里时, 无法拿去重放其它后台操作).
     *
     * 绑定的是路径而不是 Referer: Referer 可能被 CSP / 隐私策略剥离, 那会让
     * 整个后台无法提交; 路径在请求行里, 不受客户端策略影响.
     *
     * @param string|null $suffix 绑定的地址 (只取其中的路径部分), null 表示当前请求路径
     * @return string
     */
    public function getToken(?string $suffix): string
    {
        return hash('sha256', $this->token . '&csrf&' . $this->normalizeTokenPath($suffix ?? $this->currentPath()));
    }

    /**
     * 当前请求的路径 (不含查询串), 用于 token 校验
     */
    private function currentPath(): string
    {
        return $this->normalizeTokenPath($this->request->getRequestUri() ?? '/');
    }

    /**
     * 把地址规整成「相对站点根目录、去掉入口脚本」的路径
     *
     * 生成侧 (getTokenUrl 收到的 action 地址) 与校验侧 (REQUEST_URI) 必须
     * 得到完全相同的结果, 否则合法表单会被拒绝. 处理三种差异:
     * - 绝对地址 / 带查询串的地址 -> 只取 path
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

        return '/' . ltrim($path, '/');
    }

    /**
     * 获取绝对路由路径
     *
     * @param string|null $path
     * @return string
     */
    public function getRootUrl(?string $path): string
    {
        return Common::url($this->getTokenUrl($path), $this->options->rootUrl);
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
        return Common::url($this->getTokenUrl($path), $this->options->adminUrl);
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
        return Common::url($this->getTokenUrl($path), $this->options->index);
    }
}
 
