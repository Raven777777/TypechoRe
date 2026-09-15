<?php

namespace Widget;

use Widget\Base\Options as BaseOptions;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 异步调用组件
 *
 * @author qining
 * @category typecho
 * @package Widget
 */
class Ajax extends BaseOptions implements ActionInterface
{
    /**
     * 针对rewrite验证的请求返回
     *
     * @access public
     * @return void
     */
    public function remoteCallback()
    {
        if ($this->options->generator == $this->request->getAgent()) {
            echo 'OK';
        }
    }

    /**
     * 自定义编辑器大小
     *
     * @throws \Typecho\Db\Exception|Exception
     */
    public function editorResize()
    {
        $this->user->pass('contributor');
        $size = $this->request->filter('int')->get('size');

        if (
            $this->db->fetchObject($this->db->select(['COUNT(*)' => 'num'])
                ->from('table.options')->where('name = ? AND user = ?', 'editorSize', $this->user->uid))->num > 0
        ) {
            parent::update(
                ['value' => $size],
                $this->db->sql()->where('name = ? AND user = ?', 'editorSize', $this->user->uid)
            );
        } else {
            parent::insert([
                'name'  => 'editorSize',
                'value' => $size,
                'user'  => $this->user->uid
            ]);
        }
    }

    /**
     * 异步请求入口
     *
     * @access public
     * @return void
     */
    public function action()
    {
        if (!$this->request->isAjax()) {
            $this->response->goBack();
        }

        $this->on($this->request->is('do=remoteCallback'))->remoteCallback();
        $this->on($this->request->is('do=editorResize'))->editorResize();
    }
}
