<?php

namespace Widget;

use Typecho\Cookie;
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
    public function action()
    {
        // protect
        $this->security->protect();

        /** 如果已经登录 */
        if ($this->user->hasLogin()) {
            /** 直接返回 */
            $this->response->redirect($this->options->index);
        }

        /** 初始化验证类 */
        $validator = new Validate();
        $validator->addRule('name', 'required', _t('请输入用户名'));
        $validator->addRule('password', 'required', _t('请输入密码'));
        $expire = 30 * 24 * 3600;

        /** 记住密码状态 */
        if ($this->request->is('remember=1')) {
            Cookie::set('__typecho_remember_remember', 1, $expire);
        } elseif (Cookie::get('__typecho_remember_remember')) {
            Cookie::delete('__typecho_remember_remember');
        }

        /** 截获验证异常 */
        if ($error = $validator->run($this->request->from('name', 'password'))) {
            Cookie::set('__typecho_remember_name', $this->request->get('name'));

            /** 设置提示信息 */
            Notice::alloc()->set($error);
            $this->response->goBack();
        }

        /** 开始验证用户 **/
        $valid = $this->user->login(
            $this->request->get('name'),
            $this->request->get('password'),
            false,
            $this->request->is('remember=1') ? $expire : 0
        );

        /** 比对密码 */
        if (!$valid) {
            /** 防止穷举,休眠3秒 */
            sleep(3);

            self::pluginHandle()->call(
                'loginFailure',
                $this->user,
                $this->request->get('name'),
                $this->request->get('password'),
                $this->request->is('remember=1')
            );

            Cookie::set('__typecho_remember_name', $this->request->get('name'));
            Notice::alloc()->set(_t('用户名或密码无效'), 'error');
            $this->response->goBack('?referer=' . urlencode((string) $this->request->get('referer', '')));
        }

        self::pluginHandle()->call(
            'loginSuccess',
            $this->user,
            $this->request->get('name'),
            $this->request->get('password'),
            $this->request->is('remember=1')
        );

        /** 跳转验证后地址 */
        if (!empty($this->request->referer)) {
            /** fix #952 & validate redirect url */
            $refererParts = parse_url($this->request->referer);
            $siteParts = parse_url($this->options->siteUrl);
            $adminParts = parse_url($this->options->adminUrl);

            $refererHost = strtolower($refererParts['host'] ?? '');
            $sameHost = '' !== $refererHost
                && (($refererHost === strtolower($siteParts['host'] ?? '')
                    && !empty($siteParts['host']))
                || ($refererHost === strtolower($adminParts['host'] ?? '')
                    && !empty($adminParts['host'])));

            // scheme/host 必须属于本站, 防止开放重定向 (http://blog.com.evil.com 会绕过前缀比较)
            // 相对地址需排除 // 与 /\ 前缀 (浏览器将反斜杠规范化为斜杠, /\evil.com 等价于 //evil.com)
            if (
                ($sameHost && in_array(strtolower($refererParts['scheme'] ?? ''), ['http', 'https']))
                || ('/' === substr($this->request->referer, 0, 1)
                    && !in_array(substr($this->request->referer, 0, 2), ['//', '/\\'], true))
            ) {
                $this->response->redirect($this->request->referer);
            }
        } elseif (!$this->user->pass('contributor', true)) {
            /** 不允许普通用户直接跳转后台 */
            $this->response->redirect($this->options->profileUrl);
        }

        $this->response->redirect($this->options->adminUrl);
    }
}
