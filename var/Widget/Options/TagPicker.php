<?php

namespace Widget\Options;

use Typecho\Widget\Exception as WidgetException;
use Widget\ActionInterface;
use Widget\Base\Options;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 标签速选面板设置
 *
 * 撰写文章页的标签速选面板 (write-post.php) 的行为配置,
 * 以 JSON 存储在 options 表的 tagPicker 行 (user = 0)。
 *
 * @package TagPicker
 */
class TagPicker extends Options implements ActionInterface
{
    use EditTrait;

    /**
     * 默认配置
     */
    public const DEFAULTS = [
        'sort'            => 'count',
        'limit'           => 100,
        'ignoreZeroCount' => 0,
        'showCount'       => 1
    ];

    /**
     * 读取配置, 未配置或字段非法时回退默认值
     *
     * @return array
     */
    public static function config(): array
    {
        $conf = self::DEFAULTS;
        $stored = json_decode((string) Options::alloc()->tagPicker, true);

        if (is_array($stored)) {
            if (isset($stored['sort']) && in_array($stored['sort'], ['count', 'name'], true)) {
                $conf['sort'] = $stored['sort'];
            }

            if (isset($stored['limit'])) {
                $conf['limit'] = max(0, intval($stored['limit']));
            }

            $conf['ignoreZeroCount'] = empty($stored['ignoreZeroCount']) ? 0 : 1;
            $conf['showCount'] = empty($stored['showCount']) ? 0 : 1;
        }

        return $conf;
    }

    /**
     * 保存设置
     *
     * @return void
     * @throws WidgetException
     */
    public function action()
    {
        $this->user->pass('editor');
        $this->security->protect();

        $sort = strval($this->request->get('sort', 'count'));
        if (!in_array($sort, ['count', 'name'], true)) {
            $sort = 'count';
        }

        $limit = strval($this->request->get('limit', '100'));
        $limit = is_numeric($limit) ? max(0, min(1000, intval($limit))) : 100;

        $config = [
            'sort'            => $sort,
            'limit'           => $limit,
            'ignoreZeroCount' => $this->isEnableByCheckbox($this->request->getArray('ignoreZeroCount'), '1'),
            'showCount'       => $this->isEnableByCheckbox($this->request->getArray('showCount'), '1')
        ];

        $json = json_encode($config);
        $db = $this->db;

        $existing = $db->fetchRow($db->select('name')->from('table.options')
            ->where('name = ? AND user = 0', 'tagPicker')->limit(1));

        if ($existing) {
            $db->query($db->update('table.options')->rows(['value' => $json])
                ->where('name = ? AND user = 0', 'tagPicker'));
        } else {
            $db->query($db->insert('table.options')->rows([
                'name'  => 'tagPicker',
                'user'  => 0,
                'value' => $json
            ]));
        }

        Notice::alloc()->set(_t('标签速选设置已经保存'), 'success');
        $this->response->goBack();
    }
}
