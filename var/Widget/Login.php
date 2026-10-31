<?php

namespace Widget;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Throttle;
use Typecho\Validate;
use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 登录组件
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Login extends Users implements ActionInterface
{
    /**
     * 初始化函数
     *
     * @access public
     * @return void
     */
    #[\Override]
    public function action()
    {
        // 凭据只允许 POST, 否则密码会出现在访问日志 / Referer / 历史记录里
        if (!$this->request->isPost()) {
            throw new \Typecho\Widget\Exception(_t('请使用 POST 方式登录'), 405);
        }

        $origin = $this->request->getServer('HTTP_ORIGIN') ?: $this->request->getReferer();
        if (
            !Common::isSameOrigin($origin, $this->options->rootUrl)
            && !Common::isSameOrigin($origin, $this->options->adminUrl)
        ) {
            throw new \Typecho\Widget\Exception(_t('登录请求来源无效'), 403);
        }

        $this->security->protect();

        /** 如果已经登录 */
        if ($this->user->hasLogin()) {
            /** 直接返回 */
            $this->response->redirect($this->options->index);
        }

        $credentials = [
            'name' => is_string($_POST['name'] ?? null) ? $_POST['name'] : '',
            'password' => is_string($_POST['password'] ?? null) ? $_POST['password'] : '',
        ];
        $remember = ($_POST['remember'] ?? '') === '1';

        /** 拦截暴力破解: session 计数可以被客户端丢 cookie 清零, 这里存到临时目录 */
        $throttleSubject = $credentials['name'] . '|' . $this->request->getIp();
        if (Throttle::tooMany('login', $throttleSubject)) {
            Notice::alloc()->set(_t('尝试次数过多, 请稍后再试'), 'error');
            $this->response->goBack();
        }

        /** 初始化验证类 */
        $validator = new Validate();
        $validator->addRule('name', 'required', _t('请输入用户名'));
        $validator->addRule('password', 'required', _t('请输入密码'));
        $expire = 30 * 24 * 3600;

        /** 记住密码状态 */
        if ($remember) {
            Cookie::set('__typecho_remember_remember', 1, $expire);
        } elseif (Cookie::get('__typecho_remember_remember')) {
            Cookie::delete('__typecho_remember_remember');
        }

        /** 截获验证异常 */
        if ($error = $validator->run($credentials)) {
            Cookie::set('__typecho_remember_name', $credentials['name']);

            /** 设置提示信息 */
            Notice::alloc()->set($error);
            $this->response->goBack();
        }

        /** 开始验证用户 **/
        $valid = $this->user->login(
            $credentials['name'],
            $credentials['password'],
            false,
            $remember ? $expire : 0
        );

        /** 比对密码 */
        if (!$valid) {
            Throttle::fail('login', $throttleSubject);

            self::pluginHandle()->call(
                'loginFailure',
                $this->user,
                $credentials['name'],
                $credentials['password'],
                $remember
            );

            Cookie::set('__typecho_remember_name', $credentials['name']);
            Notice::alloc()->set(_t('用户名或密码无效'), 'error');
            $this->response->goBack('?referer=' . urlencode((string) $this->request->get('referer', '')));
        }

        Throttle::clear('login', $throttleSubject);

        self::pluginHandle()->call(
            'loginSuccess',
            $this->user,
            $credentials['name'],
            $credentials['password'],
            $remember
        );

        /** 跳转验证后地址 */
        if (!empty($this->request->referer)) {
            // Validate the destination after the redirect layer's normalization.
            $referer = Common::safeUrl((string) $this->request->referer);
            if (
                Common::isSameOrigin($referer, $this->options->siteUrl)
                || Common::isSameOrigin($referer, $this->options->adminUrl)
                || (str_starts_with($referer, '/')
                    && !str_starts_with($referer, '//') && !str_starts_with($referer, '/\\'))
            ) {
                if (str_starts_with($referer, '/')) {
                    $origin = array_intersect_key(parse_url($this->options->siteUrl), array_flip(['scheme', 'host', 'port']));
                    $referer = Common::buildUrl($origin) . $referer;
                }
                $this->response->redirect($referer);
            }
        } elseif (!$this->user->pass('contributor', true)) {
            /** 不允许普通用户直接跳转后台 */
            $this->response->redirect($this->options->profileUrl);
        }

        $this->response->redirect($this->options->adminUrl);
    }
}
