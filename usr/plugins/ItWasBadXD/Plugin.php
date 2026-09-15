<?php

namespace TypechoPlugin\ItWasBadXD;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Widget\Archive;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 当用户离开标签页时，页面标题变为“崩溃啦XD”
 * 可自定义
 * @package 崩溃啦XD
 * @author 井水玉藻
 * @version 1.3.0
 * @link https://love4z.cn/
 */
class Plugin implements PluginInterface
{
    /**
     * 启用插件
     */
    public static function activate()
    {
        \Typecho\Plugin::factory(Archive::class)->header = [__CLASS__, 'header'];
        return _t('插件已启用');
    }

    /**
     * 禁用插件
     */
    public static function deactivate()
    {
        return _t('插件已禁用');
    }

    /**
     * 插件配置面板
     *
     * @param Form $form 配置面板
     */
    public static function config(Form $form)
    {
        $blurTitle = new Text(
            'blurTitle',
            null,
            '崩溃啦XD',
            _t('失去焦点显示的标题'),
            _t('支持HTML特殊字符，但会被转义')
        );
        $form->addInput($blurTitle->addRule('xssCheck', _t('请勿包含非法字符')));

        $focusTitle = new Text(
            'focusTitle',
            null,
            '骗你的啦WWW',
            _t('获得焦点显示的标题'),
            _t('支持HTML特殊字符，但会被转义')
        );
        $form->addInput($focusTitle->addRule('xssCheck', _t('请勿包含非法字符')));

        $delayTime = new Text(
            'delayTime',
            null,
            '1000',
            _t('延迟时间（毫秒）'),
            _t('离开标签页后延迟多少毫秒再切换标题, 请输入大于0的整数')
        );
        $form->addInput($delayTime->addRule('isInteger', _t('必须为整数'))->addRule('regexp', _t('请输入大于 0 的整数'), '/^[1-9][0-9]*$/'));
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form 配置面板
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 在页面头部输出标题切换脚本
     */
    public static function header()
    {
        try {
            $options = Options::alloc()->plugin('ItWasBadXD');
        } catch (\Typecho\Plugin\Exception $e) {
            return;
        }

        $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
        $blurTitle = json_encode((string) $options->blurTitle, $flags);
        $focusTitle = json_encode((string) $options->focusTitle, $flags);
        $delayTime = (int) $options->delayTime ?: 1000;

        echo <<<HTML
<script type="text/javascript">
(function () {
    const blurTitle = {$blurTitle};
    const focusTitle = {$focusTitle};
    const delayTime = {$delayTime};
    let originalTitle = null;
    let timeoutId = null;
    let blurred = false;

    function rememberTitle() {
        if (null === originalTitle) {
            originalTitle = document.title;
        }
    }

    function handleBlur() {
        rememberTitle();
        clearTimeout(timeoutId);
        timeoutId = setTimeout(function () {
            blurred = true;
            document.title = blurTitle;
        }, delayTime);
    }

    function handleFocus() {
        clearTimeout(timeoutId);

        if (!blurred || null === originalTitle) {
            blurred = false;
            return;
        }

        blurred = false;
        document.title = focusTitle;
        timeoutId = setTimeout(function () {
            document.title = originalTitle;
        }, delayTime);
    }

    if (typeof document.hidden !== 'undefined') {
        document.addEventListener('visibilitychange', function () {
            document.hidden ? handleBlur() : handleFocus();
        });
    } else {
        window.addEventListener('blur', handleBlur);
        window.addEventListener('focus', handleFocus);
    }
})();
</script>
HTML;
    }
}
