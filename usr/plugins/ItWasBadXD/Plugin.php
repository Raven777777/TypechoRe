<?php

namespace TypechoPlugin\ItWasBadXD;

use Typecho\Config;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Widget\Archive;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 离开标签页时, 用打字机效果循环播放一句话
 * 句库、光标、各种时间范围都可自定义
 *
 * @package 崩溃啦XD
 * @author 井水玉藻
 * @version 1.4.0
 * @update 2026.10.18
 * @link https://love4z.cn/
 */
class Plugin implements PluginInterface
{
    /**
     * 默认句库 (就是原作者脚本里的那几句)
     */
    private const DEFAULT_MESSAGES = "醒醒我们还有座城要烧🔥\n我是 V，一名雇佣兵。\n错误城市，错误的人。\nNothing personal，choom。\n夜之城，梦想的坟场 🌃\nJohnny，从我脑子里滚出去。\n义体很烫，灵魂很冷。\n我会成为夜之城的传奇。\n再见，夜之城。";

    /**
     * 启用插件
     */
    #[\Override]
    public static function activate()
    {
        \Typecho\Plugin::factory(Archive::class)->header = [__CLASS__, 'header'];
        return _t('插件已启用');
    }

    /**
     * 禁用插件
     */
    #[\Override]
    public static function deactivate()
    {
        return _t('插件已禁用');
    }

    /**
     * 插件配置面板
     *
     * @param Form $form 配置面板
     */
    #[\Override]
    public static function config(Form $form)
    {
        $messages = new Textarea(
            'messages',
            null,
            self::DEFAULT_MESSAGES,
            _t('要播放的句子'),
            _t('一行一句, 会按顺序循环播放; 留空则退回使用「失去焦点显示的标题」')
        );
        $messages->input->setAttribute('rows', '9')->setAttribute('cols', '80');
        $form->addInput($messages);

        $charMin = new Text(
            'charMin',
            null,
            '80',
            _t('打字间隔范围 (毫秒), 最小'),
            _t('每打出一个字符等待的时间, 会在这个范围里随机取值')
        );
        $form->addInput($charMin->addRule('isInteger', _t('必须为整数'))->addRule('regexp', _t('请输入大于等于 0 的整数'), '/^[0-9]+$/'));

        $charMax = new Text(
            'charMax',
            null,
            '120',
            _t('打字间隔范围 (毫秒), 最大'),
            _t('小于最小值时插件会自动对调两个值')
        );
        $form->addInput($charMax->addRule('isInteger', _t('必须为整数'))->addRule('regexp', _t('请输入大于等于 0 的整数'), '/^[0-9]+$/'));

        $holdMin = new Text(
            'holdMin',
            null,
            '1200',
            _t('一句话打完后的停留范围 (毫秒), 最小'),
            _t('整句显示完, 光标变成停顿光标, 等待这个时间后开始逐字删除')
        );
        $form->addInput($holdMin->addRule('isInteger', _t('必须为整数'))->addRule('regexp', _t('请输入大于等于 0 的整数'), '/^[0-9]+$/'));

        $holdMax = new Text(
            'holdMax',
            null,
            '1800',
            _t('一句话打完后的停留范围 (毫秒), 最大'),
            _t('每句话都会重新随机一次')
        );
        $form->addInput($holdMax->addRule('isInteger', _t('必须为整数'))->addRule('regexp', _t('请输入大于等于 0 的整数'), '/^[0-9]+$/'));

        $gapMin = new Text(
            'gapMin',
            null,
            '400',
            _t('句子之间的间隔范围 (毫秒), 最小'),
            _t('上一句删完到下一句开始打字之间等待的时间')
        );
        $form->addInput($gapMin->addRule('isInteger', _t('必须为整数'))->addRule('regexp', _t('请输入大于等于 0 的整数'), '/^[0-9]+$/'));

        $gapMax = new Text(
            'gapMax',
            null,
            '600',
            _t('句子之间的间隔范围 (毫秒), 最大'),
            _t('想多变就调多大')
        );
        $form->addInput($gapMax->addRule('isInteger', _t('必须为整数'))->addRule('regexp', _t('请输入大于等于 0 的整数'), '/^[0-9]+$/'));

        $cursorTyping = new Text(
            'cursorTyping',
            null,
            '▌',
            _t('打字时的光标'),
            _t('默认 ▌, 可以换成 | 或别的字符, 留空则不显示')
        );
        $form->addInput($cursorTyping);

        $cursorHold = new Text(
            'cursorHold',
            null,
            '▁',
            _t('停顿/删除时的光标'),
            _t('默认 ▁, 留空则不显示')
        );
        $form->addInput($cursorHold);

        $focusTitle = new Text(
            'focusTitle',
            null,
            '骗你的啦WWW',
            _t('回到标签页时显示的标题'),
            _t('显示这个标题一小会儿之后恢复原来的标题, 支持 HTML 特殊字符, 但会被转义')
        );
        $form->addInput($focusTitle->addRule('xssCheck', _t('请勿包含非法字符')));

        $focusDelay = new Text(
            'focusDelay',
            null,
            '1000',
            _t('回到标签页时标题停留时间 (毫秒)'),
            _t('显示「回到标签页时显示的标题」多久后恢复原标题')
        );
        $form->addInput($focusDelay->addRule('isInteger', _t('必须为整数'))->addRule('regexp', _t('请输入大于等于 0 的整数'), '/^[0-9]+$/'));

        $playMode = new Radio(
            'playMode',
            ['blur' => _t('离开标签页时'), 'always' => _t('始终播放')],
            'blur',
            _t('播放时机'),
            _t('选「始终播放」时, 打开页面就开始打字, 也不会再显示上面的标题')
        );
        $form->addInput($playMode);

        $blurTitle = new Text(
            'blurTitle',
            null,
            '崩溃啦XD',
            _t('失去焦点显示的标题 (旧配置)'),
            _t('只有在「要播放的句子」留空时才会用到')
        );
        $form->addInput($blurTitle->addRule('xssCheck', _t('请勿包含非法字符')));
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form 配置面板
     */
    #[\Override]
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 在页面头部输出打字机脚本
     */
    public static function header(): void
    {
        try {
            $options = Options::alloc()->plugin('ItWasBadXD');
        } catch (\Typecho\Plugin\Exception $e) {
            return;
        }

        $config = [
            'messages' => self::messages($options),
            'charMin' => self::intOption($options, 'charMin', 80),
            'charMax' => self::intOption($options, 'charMax', 120),
            'holdMin' => self::intOption($options, 'holdMin', 1200),
            'holdMax' => self::intOption($options, 'holdMax', 1800),
            'gapMin' => self::intOption($options, 'gapMin', 400),
            'gapMax' => self::intOption($options, 'gapMax', 600),
            'cursorTyping' => self::optionalString($options, 'cursorTyping', '▌'),
            'cursorHold' => self::optionalString($options, 'cursorHold', '▁'),
            'focusTitle' => self::stringOption($options, 'focusTitle', '骗你的啦WWW'),
            'focusDelay' => self::intOption($options, 'focusDelay', 1000),
            'playMode' => 'always' === $options->playMode ? 'always' : 'blur',
        ];

        /** 句库里可能有引号、尖括号、emoji, 统一按 JS 字符串字面量安全输出 */
        $json = json_encode(
            $config,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        if (false === $json) {
            return;
        }

        echo <<<HTML
<script type="text/javascript">
(function () {
    var config = {$json};
    var messages = config.messages;
    var originalTitle = null;
    var timer = null;
    var running = false;
    var index = 0;
    var count = 0;
    var holding = false;

    function randomDelay(min, max) {
        min = Math.max(0, Math.floor(min) || 0);
        max = Math.max(0, Math.floor(max) || 0);

        if (max < min) {
            var swap = min;
            min = max;
            max = swap;
        }

        return min + Math.floor(Math.random() * (max - min + 1));
    }

    function clearTimer() {
        if (null !== timer) {
            clearTimeout(timer);
            timer = null;
        }
    }

    function stop() {
        running = false;
        clearTimer();
    }

    function tick() {
        timer = null;

        if (!running) {
            return;
        }

        var message = messages[index];
        var delay;

        if (holding) {
            /** 停顿结束, 逐字删除 (光标用停顿光标) */
            count -= 1;

            if (count > 0) {
                document.title = message.slice(0, count) + config.cursorHold;
                delay = randomDelay(config.charMin, config.charMax);
            } else {
                count = 0;
                holding = false;
                index = (index + 1) % messages.length;
                document.title = config.cursorHold;
                delay = randomDelay(config.gapMin, config.gapMax);
            }
        } else {
            /** 逐字打出 */
            count += 1;
            document.title = message.slice(0, count) + config.cursorTyping;

            if (count >= message.length) {
                holding = true;
                delay = randomDelay(config.holdMin, config.holdMax);
            } else {
                delay = randomDelay(config.charMin, config.charMax);
            }
        }

        timer = setTimeout(tick, delay);
    }

    function play() {
        if (running) {
            return;
        }

        if (null === originalTitle) {
            originalTitle = document.title;
        }

        running = true;
        index = 0;
        count = 0;
        holding = false;
        clearTimer();
        tick();
    }

    function restore() {
        if (!running) {
            return;
        }

        stop();
        document.title = config.focusTitle;
        timer = setTimeout(function () {
            timer = null;
            document.title = null === originalTitle ? '' : originalTitle;
        }, config.focusDelay);
    }

    if ('always' === config.playMode) {
        play();
        return;
    }

    if ('hidden' in document) {
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                play();
            } else {
                restore();
            }
        });
    } else {
        window.addEventListener('blur', play);
        window.addEventListener('focus', restore);
    }
})();
</script>
HTML;
    }

    /**
     * 解析句子列表: 一行一句, 去掉空行
     *
     * @return string[]
     */
    private static function messages(Config $options): array
    {
        $messages = [];

        foreach (preg_split('/\r\n|\r|\n/', (string) $options->messages) ?: [] as $line) {
            $line = trim($line);

            if ('' !== $line) {
                $messages[] = $line;
            }
        }

        if (empty($messages)) {
            /** 兼容旧配置: 没有句库时用原来的「失去焦点显示的标题」 */
            $fallback = trim((string) self::stringOption($options, 'blurTitle', ''));

            $messages[] = '' === $fallback ? '崩溃啦XD' : $fallback;
        }

        return $messages;
    }

    /**
     * 读取非负整数配置
     */
    private static function intOption(Config $options, string $key, int $default): int
    {
        $value = $options->{$key};

        if (null === $value || '' === $value) {
            return $default;
        }

        return max(0, (int) $value);
    }

    /**
     * 读取字符串配置, 只有配置项不存在时才退回默认值 (允许显式留空)
     */
    private static function optionalString(Config $options, string $key, string $default): string
    {
        $value = $options->{$key};

        return null === $value ? $default : (string) $value;
    }

    /**
     * 读取字符串配置, 空值退回默认值
     */
    private static function stringOption(Config $options, string $key, string $default): string
    {
        $value = (string) $options->{$key};

        return '' === $value ? $default : $value;
    }
}
