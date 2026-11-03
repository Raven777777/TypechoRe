<?php

namespace Widget;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Db\Exception;
use Typecho\Throttle;
use Typecho\Validate;
use Widget\Base\Users;
use Widget\Users\EditTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 注册组件
 *
 * @author qining
 * @category typecho
 * @package Widget
 */
class Register extends Users implements ActionInterface
{
    use EditTrait;

    /**
     * 初始化函数
     *
     * @throws Exception
     */
    #[\Override]
    public function action()
    {
        if (!$this->request->isPost()) {
            throw new \Typecho\Widget\Exception(_t('请使用 POST 方式注册'), 405);
        }
        $origin = $this->request->getServer('HTTP_ORIGIN') ?: $this->request->getReferer();
        if (!Common::isSameOrigin($origin, $this->options->rootUrl)
            && !Common::isSameOrigin($origin, $this->options->adminUrl)) {
            throw new \Typecho\Widget\Exception(_t('注册请求来源无效'), 403);
        }
        $this->security->protect();
        $credentials = [];
        foreach (['name', 'mail', 'password', 'confirm'] as $name) {
            $credentials[$name] = is_string($_POST[$name] ?? null) ? $_POST[$name] : '';
        }

        /** 如果已经登录 */
        if ($this->user->hasLogin() || !$this->options->allowRegister) {
            /** 直接返回 */
            $this->response->redirect($this->options->index);
        }

        /** 注册同样要限流: 每次尝试都会跑一次 bcrypt, 否则可以被打成 CPU/数据库洪水 */
        $throttleSubject = $this->request->getIp();
        if (Throttle::hit('register', $throttleSubject)) {
            Notice::alloc()->set(_t('注册尝试次数过多, 请稍后再试'), 'error');
            $this->response->goBack();
        }

        /** 初始化验证类 */
        $validator = new Validate();
        $validator->addRule('name', 'required', _t('必须填写用户名称'));
        $validator->addRule('name', 'minLength', _t('用户名至少包含2个字符'), 2);
        $validator->addRule('name', 'maxLength', _t('用户名最多包含32个字符'), 32);
        $validator->addRule('name', 'xssCheck', _t('请不要在用户名中使用特殊字符'));
        $validator->addRule('name', [$this, 'nameExists'], _t('用户名已经存在'));
        $validator->addRule('mail', 'required', _t('必须填写电子邮箱'));
        $validator->addRule('mail', [$this, 'mailExists'], _t('电子邮箱地址已经存在'));
        $validator->addRule('mail', 'email', _t('电子邮箱格式错误'));
        $validator->addRule('mail', 'maxLength', _t('电子邮箱最多包含64个字符'), 64);

        /** 密码由注册者自己设置: 之前这里生成随机密码并通过 notice 展示,
         *  notice 存在可被 JS 读取的 cookie 里, 等于把密码写进了前端 */
        $validator->addRule('password', 'required', _t('必须填写密码'));
        $validator->addRule('password', 'minLength', _t('为了保证账户安全, 请输入至少六位的密码'), 6);
        $validator->addRule('password', 'maxLength', _t('为了便于记忆, 密码长度请不要超过十八位'), 18);
        $validator->addRule('confirm', 'confirm', _t('两次输入的密码不一致'), 'password');

        /** 截获验证异常 */
        if ($error = $validator->run($credentials)) {
            Cookie::set('__typecho_remember_name', $credentials['name']);
            Cookie::set('__typecho_remember_mail', $credentials['mail']);

            /** 设置提示信息 */
            Notice::alloc()->set($error);
            $this->response->goBack();
        }

        $password = $credentials['password'];

        $dataStruct = [
            'name' => $credentials['name'],
            'mail' => $credentials['mail'],
            'screenName' => $credentials['name'],
            'password' => Common::hashPassword($password),
            'created' => $this->options->time,
            'group' => 'subscriber'
        ];

        $dataStruct = self::pluginHandle()->filter('register', $dataStruct);

        $insertId = $this->insert($dataStruct);
        $this->db->fetchRow($this->select()->where('uid = ?', $insertId)
            ->limit(1), [$this, 'push']);

        self::pluginHandle()->call('finishRegister', $this);
        Throttle::clear('register', $throttleSubject);

        $this->user->login($credentials['name'], $password);

        Cookie::delete('__typecho_first_run');
        Cookie::delete('__typecho_remember_name');
        Cookie::delete('__typecho_remember_mail');

        Notice::alloc()->set(
            _t(
                '用户 <strong>%s</strong> 已经成功注册',
                $this->screenName
            ),
            'success'
        );
        $this->response->redirect($this->options->adminUrl);
    }
}
