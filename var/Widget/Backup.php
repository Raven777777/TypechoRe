<?php

namespace Widget;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Db;
use Typecho\Exception;
use Widget\Base\Options as BaseOptions;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 备份工具
 *
 * @package Widget
 */
class Backup extends BaseOptions implements ActionInterface
{
    public const HEADER = '%TYPECHO_BACKUP_XXXX%';
    public const HEADER_VERSION = '0002';

    /** 导出时每批读取的行数 */
    private const PAGE_SIZE = 500;

    /**
     * @var array
     */
    private array $types = [
        'contents'      => 1,
        'comments'      => 2,
        'metas'         => 3,
        'relationships' => 4,
        'users'         => 5,
        'fields'        => 6
    ];

    /**
     * @var array
     */
    private array $fields = [
        'contents'      => [
            'cid', 'title', 'slug', 'created', 'modified', 'text', 'order', 'authorId',
            'template', 'type', 'status', 'password', 'commentsNum', 'allowComment', 'allowPing', 'allowFeed', 'parent'
        ],
        'comments'      => [
            'coid', 'cid', 'created', 'author', 'authorId', 'ownerId',
            'mail', 'url', 'ip', 'agent', 'text', 'type', 'status', 'parent'
        ],
        'metas'         => [
            'mid', 'name', 'slug', 'type', 'description', 'count', 'order', 'parent'
        ],
        'relationships' => ['cid', 'mid'],
        'users'         => [
            'uid', 'name', 'password', 'mail', 'url', 'screenName',
            'created', 'activated', 'logged', 'group', 'authCode'
        ],
        'fields'        => [
            'cid', 'name', 'type', 'str_value', 'int_value', 'float_value'
        ]
    ];

    private ?array $login = null;


    /**
     * 列出已有备份文件
     *
     * @return array
     */
    public function listFiles(): array
    {
        return array_map('basename', glob(__TYPECHO_BACKUP_DIR__ . '/*.dat'));
    }

    /**
     * 绑定动作
     */
    #[\Override]
    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();

        $this->on($this->request->is('do=export'))->export();
        $this->on($this->request->is('do=import'))->import();
    }

    /**
     * 导出数据
     *
     * @throws \Typecho\Db\Exception
     */
    private function export()
    {
        $backupFile = tempnam(sys_get_temp_dir(), 'backup_');
        if (false === $backupFile) {
            throw new Exception(_t('无法创建备份文件'));
        }
        register_shutdown_function(static fn() => is_file($backupFile) && @unlink($backupFile));
        $fp = @fopen($backupFile, 'wb');
        if (!$fp) {
            throw new Exception(_t('无法创建备份文件'));
        }

        try {
            $header = str_replace('XXXX', self::HEADER_VERSION, self::HEADER);
            $this->writeBuffer($fp, $header);
            $this->exportData($fp);
            self::pluginHandle()->call('export', $fp);
            $this->writeBuffer($fp, $header);
        } finally {
            fclose($fp);
        }

        $host = parse_url($this->options->siteUrl, PHP_URL_HOST);
        $this->response->setContentType('application/octet-stream');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="'
            . date('Ymd') . '_' . $host . '_' . uniqid() . '.dat"');
        $this->response->throwFile($backupFile, 'application/octet-stream');
    }

    /** @param resource $fp */
    private function exportData($fp): void
    {
        $db = $this->db;
        // A deferred read transaction gives all pages one snapshot without reserving the writer lock.
        $db->query('BEGIN', Db::READ, '');
        try {
            foreach ($this->types as $type => $val) {
                // 按 rowid 增量翻页: OFFSET 在十万行级别是二次复杂度, 也会让每个进程各扫一遍.
                // rowid 取别名 rid: SQLite 会把它报告成 INTEGER PRIMARY KEY 列的名字, 别名才稳定.
                $last = 0;
                do {
                    $rows = $db->fetchAll(
                        $db->select(['rowid' => 'rid'], ...$this->fields[$type])
                            ->from('table.' . $type)
                            ->where('rowid > ?', $last)
                            ->order('rowid', Db::SORT_ASC)
                            ->limit(self::PAGE_SIZE)
                    );

                    foreach ($rows as $row) {
                        $last = (int) $row['rid'];
                        $this->writeBuffer($fp, $this->buildBuffer($val, $this->applyFields($type, $row)));
                    }
                } while (count($rows) === self::PAGE_SIZE);
            }
            $db->query('COMMIT', Db::READ, '');
        } catch (\Throwable $e) {
            $db->query('ROLLBACK', Db::READ, '');
            throw $e;
        }
    }

    /** @param resource $fp */
    private function writeBuffer($fp, string $buffer): void
    {
        if (fwrite($fp, $buffer) !== strlen($buffer)) {
            throw new Exception(_t('备份文件写入失败'));
        }
    }

    /**
     * @param $type
     * @param $data
     * @return string
     */
    private function buildBuffer($type, $data): string
    {
        $body = '';
        $schema = [];

        foreach ($data as $key => $val) {
            $schema[$key] = null === $val ? null : strlen($val);
            $body .= $val;
        }

        $header = json_encode($schema);
        return Common::buildBackupBuffer($type, $header, $body);
    }

    /**
     * 过滤字段
     *
     * @param $table
     * @param $data
     * @return array
     */
    private function applyFields($table, $data): array
    {
        $result = [];

        foreach ($data as $key => $val) {
            $index = array_search($key, $this->fields[$table]);

            if ($index !== false) {
                $result[$key] = $val;
            }
        }

        return $result;
    }

    /**
     * 导入数据
     */
    private function import()
    {
        $path = null;

        if (!empty($_FILES)) {
            $file = array_pop($_FILES);

            if(UPLOAD_ERR_NO_FILE == $file['error']) {
                Notice::alloc()->set(_t('没有选择任何备份文件'), 'error');
                $this->response->goBack();
            }

            if (UPLOAD_ERR_OK == $file['error'] && is_uploaded_file($file['tmp_name'])) {
                $path = $file['tmp_name'];
            } else {
                Notice::alloc()->set(_t('备份文件上传失败'), 'error');
                $this->response->goBack();
            }
        } else {
            if (!$this->request->is('file')) {
                Notice::alloc()->set(_t('没有选择任何备份文件'), 'error');
                $this->response->goBack();
            }

            $path = __TYPECHO_BACKUP_DIR__ . '/' . basename($this->request->get('file'));

            if (!file_exists($path)) {
                Notice::alloc()->set(_t('备份文件不存在'), 'error');
                $this->response->goBack();
            }
        }

        $this->extractData($path);
    }

    /**
     * 解析数据
     *
     * @param $file
     * @throws \Typecho\Db\Exception
     */
    private function extractData($file)
    {
        try {
            $this->restoreData($file);
            Notice::alloc()->set(_t('数据恢复完成'), 'success');
        } catch (\Throwable $e) {
            Notice::alloc()->set(_t('恢复过程中遇到如下错误: %s', $e->getMessage()), 'error');
        }
        $this->response->goBack();
    }

    private function restoreData(string $file): void
    {
        $fp = @fopen($file, 'rb');
        if (!$fp) {
            throw new Exception(_t('无法读取备份文件'));
        }
        $this->login = null;
        try {
            $fileSize = fstat($fp)['size'];
            $headerSize = strlen(self::HEADER);
            if ($fileSize < 2 * $headerSize) {
                throw new Exception(_t('备份文件格式错误'));
            }
            $fileHeader = fread($fp, $headerSize);
            if (!$this->parseHeader($fileHeader, $version)) {
                throw new Exception(_t('备份文件格式错误'));
            }
            fseek($fp, $fileSize - $headerSize);
            if (fread($fp, $headerSize) !== $fileHeader) {
                throw new Exception(_t('备份文件格式错误'));
            }
            fseek($fp, $headerSize);
            $offset = $headerSize;
            $end = $fileSize - $headerSize;
            $hadUsers = $this->countRows('users') > 0;
            $login = $this->db->transaction(function () use ($fp, &$offset, $end, $version, $hadUsers): ?array {
                foreach (array_keys($this->types) as $table) {
                    $this->db->truncate('table.' . $table);
                }
                // Credentials are tied to the old users; restored users must enroll again.
                if ($this->db->fetchRow($this->db->select('name')->from('sqlite_master')
                    ->where('type = ? AND name = ?', 'table', $this->db->getPrefix() . 'passkeys'))) {
                    $this->db->truncate('table.passkeys');
                }
                while ($offset < $end) {
                    $data = Common::extractBackupBuffer($fp, $offset, $version, $end);
                    if (false === $data) {
                        throw new Exception(_t('恢复数据出现错误'));
                    }
                    $this->processData(...$data);
                }
                if ($offset !== $end) {
                    throw new Exception(_t('备份文件格式错误'));
                }
                // 表已经被清空: 备份里明明有用户却没有管理员, 恢复后谁也进不了后台,
                // 当前站点本来有用户时, 空用户备份同样会把人锁在门外, 两者都整体回滚
                if ($hadUsers && 0 === $this->countRows('users')) {
                    throw new Exception(_t('备份文件不包含用户, 已取消恢复'));
                }
                if (null === $this->login && $this->countRows('users') > 0) {
                    throw new Exception(_t('备份文件里的用户都不是管理员, 已取消恢复'));
                }
                return $this->login;
            });
        } catch (\Throwable $e) {
            $this->login = null;
            throw $e;
        } finally {
            fclose($fp);
        }
        if (null !== $login) {
            Cookie::set('__typecho_uid', $login[0]);
            Cookie::set('__typecho_authCode', $login[1]);
        } else {
            Cookie::delete('__typecho_uid');
            Cookie::delete('__typecho_authCode');
        }
    }

    /**
     * @param $str
     * @param $version
     * @return bool
     */
    /**
     * @param string $table
     * @return int
     * @throws Exception
     */
    private function countRows(string $table): int
    {
        return (int) $this->db->fetchObject(
            $this->db->select(['COUNT(*)' => 'num'])->from('table.' . $table)
        )->num;
    }

    /**
     * @param $str
     * @param $version
     * @return bool
     */
    private function parseHeader($str, &$version): bool
    {
        if (!$str || strlen($str) != strlen(self::HEADER)) {
            return false;
        }

        if (!preg_match('/^%TYPECHO_BACKUP_(FILE|0001|0002)%$/D', $str)) {
            return false;
        }

        $version = substr($str, 16, - 1);
        return true;
    }

    /**
     * @param $type
     * @param $header
     * @param $body
     */
    private function processData($type, $header, $body)
    {
        $table = array_search($type, $this->types);

        if (!empty($table)) {
            $schema = json_decode($header, true);
            if (!is_array($schema) || !$schema) {
                throw new Exception(_t('备份文件格式错误'));
            }
            $data = [];
            $offset = 0;
            foreach ($schema as $key => $val) {
                if (null !== $val && (!is_int($val) || $val < 0 || $val > strlen($body) - $offset)) {
                    throw new Exception(_t('备份文件格式错误'));
                }
                $data[$key] = null === $val ? null : substr($body, $offset, $val);
                $offset += $val ?? 0;
            }
            if ($offset !== strlen($body)) {
                throw new Exception(_t('备份文件格式错误'));
            }
            $this->importData($table, $data);
        } else {
            self::pluginHandle()->call('import', $type, $header, $body);
        }
    }

    /**
     * 导入单条数据
     *
     * @param $table
     * @param $data
     */
    private function importData($table, $data)
    {
        if (null === $this->login && 'users' === $table && ($data['group'] ?? null) === 'administrator') {
            $this->reLogin($data);
        }
        $this->db->query($this->db->insert('table.' . $table)->rows($this->applyFields($table, $data)));
    }

    /**
     * 备份过程会重写用户数据
     * 所以需要重新登录当前用户
     *
     * @param $user
     */
    private function reLogin(&$user)
    {
        // 备份文件里的 authCode 只是摘要, 无法还原明文,
        // 因此统一签发一套新的会话凭证: 明文进 cookie, 摘要入库
        $authCode = Common::generateAuthCode();
        $user['authCode'] = Common::hashAuthCode($authCode);

        $user['activated'] = $this->options->time;
        $user['logged'] = $user['activated'];

        $this->login = [$user['uid'], $authCode];
    }
}
