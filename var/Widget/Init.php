<?php

namespace Widget;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Date;
use Typecho\Db;
use Typecho\I18n;
use Typecho\Plugin;
use Typecho\Response;
use Typecho\Router;
use Typecho\Router\Parser;
use Typecho\Widget;
use Widget\Action\Sitemap;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 初始化模块
 *
 * @package Widget
 */
class Init extends Widget
{
    /**
     * 入口函数,初始化路由器
     *
     * @access public
     * @return void
     * @throws Db\Exception
     */
    #[\Override]
    public function execute()
    {
        /** 初始化exception */
        if (!defined('__TYPECHO_DEBUG__') || !__TYPECHO_DEBUG__) {
            set_exception_handler(function (\Throwable $exception) {
                Response::getInstance()->clean();
                ob_end_clean();

                ob_start(function ($content) {
                    Response::getInstance()->sendHeaders();
                    return $content;
                });

                if (404 == $exception->getCode()) {
                    ExceptionHandle::alloc();
                } else {
                    Common::error($exception);
                }

                exit;
            });
        }

        // init class
        // 站点可在 config.inc.php 中预先定义 __TYPECHO_CLASS_ALIASES__ 覆盖默认别名表
        if (!defined('__TYPECHO_CLASS_ALIASES__')) {
            define('__TYPECHO_CLASS_ALIASES__', Common::CLASS_ALIASES);
        }

        /** 对变量赋值 */
        $options = Options::alloc();

        /** 语言包初始化 */
        if ($options->lang && $options->lang != 'zh_CN') {
            $dir = defined('__TYPECHO_LANG_DIR__') ? __TYPECHO_LANG_DIR__ : __TYPECHO_ROOT_DIR__ . '/usr/langs';
            I18n::setLang($dir . '/' . $options->lang . '.mo');
        }

        /** 备份文件目录初始化 */
        if (!defined('__TYPECHO_BACKUP_DIR__')) {
            define('__TYPECHO_BACKUP_DIR__', __TYPECHO_ROOT_DIR__ . '/usr/backups');
        }

        /** cookie初始化 */
        Cookie::setPrefix($options->rootUrl);
        if (defined('__TYPECHO_COOKIE_OPTIONS__')) {
            Cookie::setOptions(__TYPECHO_COOKIE_OPTIONS__);
        }

        /** 初始化路由器 */
        $routingTable = $options->routingTable;
        $addedRoutes = [];

        if (empty($routingTable['sitemap'])) {
            $routingTable['sitemap'] = Sitemap::route();
            $addedRoutes['sitemap'] = $routingTable['sitemap'];
        }

        if (empty($routingTable['sitemapPage'])) {
            $routingTable['sitemapPage'] = Sitemap::pageRoute();
            $addedRoutes['sitemapPage'] = $routingTable['sitemapPage'];
        }

        if (isset($routingTable[0]) && $addedRoutes) {
            $routingTable[0] += (new Parser($addedRoutes))->parse();
        }
        Router::setRoutes($routingTable);

        /** 初始化插件 */
        Plugin::init($options->plugins);

        /** 初始化回执 */
        $this->response->setCharset($options->charset);
        $this->response->setContentType($options->contentType);

        /** 初始化时区 */
        Date::setTimezoneOffset($options->timezone);

        /** 开始会话, 减小负载只针对后台打开session支持 */
        if ($options->installed && User::alloc()->hasLogin()) {
            Common::startSession();
        }
    }
}
