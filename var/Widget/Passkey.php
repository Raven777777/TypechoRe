<?php

namespace Widget;

use Typecho\Common;
use Typecho\Db\Exception as DbException;
use Typecho\Widget\Exception as WidgetException;
use Widget\Base\Options as BaseOptions;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Admin Passkey/WebAuthn actions.
 *
 * The implementation deliberately accepts only privacy-preserving "none"
 * attestation.  The browser/authenticator still performs the real public-key
 * ceremony; the server stores only the credential ID, public key and counter.
 */
class Passkey extends BaseOptions implements ActionInterface
{
    private const SESSION_KEY = '__typecho_passkey';
    private static bool $tableReady = false;

    public function action()
    {
        Common::startSession();
        $this->ensureTable();
        $method = (string) $this->request->get('do', 'get-options');

        try {
            switch ($method) {
                case 'create-options':
                    $this->requireAdmin();
                    $this->security->protect();
                    $this->createOptions();
                    return;
                case 'process-create':
                    $this->requireAdmin();
                    $this->security->protect();
                    $this->processCreate();
                    return;
                case 'process-get':
                    $this->processGet();
                    return;
                case 'delete':
                    $this->requireAdmin();
                    $this->security->protect();
                    $this->deletePasskey();
                    return;
                case 'list':
                    $this->requireAdmin();
                    $this->listPasskeys();
                    return;
                case 'get-options':
                    $this->getOptions();
                    return;
                default:
                    throw new WidgetException(_t('Passkey 请求不存在'), 404);
            }
        } catch (\Throwable $e) {
            $this->response->throwJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function createOptions(): void
    {
        $user = $this->user;
        $rp = $this->webAuthn();
        $args = $rp->getCreateArgs(
            (string) $user->uid,
            (string) $user->name,
            (string) ($user->screenName ?: $user->name),
            300,
            'required',
            'required'
        );

        $_SESSION[self::SESSION_KEY] = [
            'mode' => 'create',
            'uid' => (int) $user->uid,
            'challenge' => $rp->getChallenge()->getBinaryString(),
            'expires' => time() + 300
        ];

        $this->json(['success' => true, 'options' => $args]);
    }

    private function processCreate(): void
    {
        $state = $this->getState('create');
        $data = $this->input();
        $rp = $this->webAuthn();

        $registration = $rp->processCreate(
            $this->decode($data['clientDataJSON'] ?? ''),
            $this->decode($data['attestationObject'] ?? ''),
            $state['challenge'],
            true,
            true,
            false
        );

        $name = trim((string) ($data['name'] ?? 'Passkey'));
        $name = '' === $name ? 'Passkey' : mb_substr($name, 0, 100, 'UTF-8');

        $this->db->query($this->db->insert('table.passkeys')->rows([
            'uid' => $state['uid'],
            'credential_id' => $this->encode($this->binaryValue($registration->credentialId)),
            'public_key' => $registration->credentialPublicKey,
            'sign_count' => (int) ($registration->signatureCounter ?? 0),
            'transports' => '',
            'name' => $name,
            'created' => time(),
            'last_used' => 0
        ]));

        unset($_SESSION[self::SESSION_KEY]);
        $this->json(['success' => true]);
    }

    private function getOptions(): void
    {
        $rp = $this->webAuthn();
        $args = $rp->getGetArgs([], 300, false, false, false, true, true, 'required');

        $_SESSION[self::SESSION_KEY] = [
            'mode' => 'get',
            'challenge' => $rp->getChallenge()->getBinaryString(),
            'expires' => time() + 300
        ];

        $this->json(['success' => true, 'options' => $args]);
    }

    private function processGet(): void
    {
        $state = $this->getState('get');
        $data = $this->input();
        $credentialId = $this->decode($data['id'] ?? '');
        $encodedId = $this->encode($credentialId);
        $row = $this->db->fetchRow($this->db->select()->from('table.passkeys')
            ->where('credential_id = ?', $encodedId)->limit(1));

        if (!$row) {
            throw new WidgetException(_t('Passkey 不存在'), 401);
        }

        $userHandle = $this->decode($data['userHandle'] ?? '');
        if ('' === $userHandle || !hash_equals((string) $row['uid'], $userHandle)) {
            throw new WidgetException(_t('Passkey 用户标识无效'), 401);
        }

        $rp = $this->webAuthn();
        $rp->processGet(
            $this->decode($data['clientDataJSON'] ?? ''),
            $this->decode($data['authenticatorData'] ?? ''),
            $this->decode($data['signature'] ?? ''),
            $row['public_key'],
            $state['challenge'],
            (int) $row['sign_count'],
            true
        );

        $this->db->query($this->db->update('table.passkeys')->rows([
            'sign_count' => (int) ($rp->getSignatureCounter() ?? $row['sign_count']),
            'last_used' => time()
        ])->where('id = ?', $row['id']));

        unset($_SESSION[self::SESSION_KEY]);

        // 与密码登录行为对齐: 勾选「下次自动登录」时写入持久化 Cookie (30 天),
        // 否则 expire=0 使用会话 Cookie, 重启浏览器后需重新登录
        $expire = !empty($data['remember']) ? 30 * 24 * 3600 : 0;
        $this->user->simpleLogin((int) $row['uid'], false, $expire);
        $this->json(['success' => true, 'redirect' => $this->options->adminUrl]);
    }

    private function listPasskeys(): void
    {
        $rows = $this->db->fetchAll($this->db->select('id', 'name', 'created', 'last_used')
            ->from('table.passkeys')->where('uid = ?', (int) $this->user->uid)->order('id', \Typecho\Db::SORT_DESC));
        $this->json(['success' => true, 'items' => $rows]);
    }

    private function deletePasskey(): void
    {
        $id = (int) $this->request->get('id', 0);
        $this->db->query($this->db->delete('table.passkeys')
            ->where('id = ? AND uid = ?', $id, (int) $this->user->uid));
        $this->json(['success' => true]);
    }

    private function requireAdmin(): void
    {
        if (!$this->user->pass('administrator', true)) {
            throw new WidgetException(_t('禁止访问'), 403);
        }
    }

    private function webAuthn(): WebAuthn
    {
        $host = parse_url((string) $this->options->siteUrl, PHP_URL_HOST);
        $host = is_string($host) && '' !== $host ? strtolower($host) : 'localhost';
        return new WebAuthn((string) $this->options->software, $host, ['none'], true);
    }

    private function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }

        $prefix = $this->db->getPrefix();
        $driver = strtolower((string) $this->db->getAdapterName());
        if (false !== strpos($driver, 'sqlite')) {
            $sql = "CREATE TABLE IF NOT EXISTS {$prefix}passkeys (id INTEGER PRIMARY KEY AUTOINCREMENT, uid INTEGER NOT NULL, credential_id TEXT NOT NULL UNIQUE, public_key TEXT NOT NULL, sign_count INTEGER NOT NULL DEFAULT 0, transports TEXT NOT NULL DEFAULT '', name TEXT NOT NULL, created INTEGER NOT NULL, last_used INTEGER NOT NULL DEFAULT 0)";
        } elseif (false !== strpos($driver, 'pgsql')) {
            $sql = "CREATE TABLE IF NOT EXISTS {$prefix}passkeys (id SERIAL PRIMARY KEY, uid INTEGER NOT NULL, credential_id VARCHAR(512) NOT NULL UNIQUE, public_key TEXT NOT NULL, sign_count INTEGER NOT NULL DEFAULT 0, transports VARCHAR(255) NOT NULL DEFAULT '', name VARCHAR(100) NOT NULL, created INTEGER NOT NULL, last_used INTEGER NOT NULL DEFAULT 0)";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$prefix}passkeys (id INTEGER PRIMARY KEY AUTO_INCREMENT, uid INTEGER NOT NULL, credential_id VARCHAR(512) NOT NULL UNIQUE, public_key TEXT NOT NULL, sign_count INTEGER NOT NULL DEFAULT 0, transports VARCHAR(255) NOT NULL DEFAULT '', name VARCHAR(100) NOT NULL, created INTEGER NOT NULL, last_used INTEGER NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }
        $this->db->query($sql);
        self::$tableReady = true;
    }

    private function getState(string $mode): array
    {
        $state = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($state) || ($state['mode'] ?? '') !== $mode || empty($state['challenge']) || time() > (int) ($state['expires'] ?? 0)) {
            throw new WidgetException(_t('Passkey 验证已过期, 请重新开始'), 400);
        }
        return $state;
    }

    private function input(): array
    {
        $body = file_get_contents('php://input');
        $data = json_decode(false === $body ? '' : $body, true);
        if (!is_array($data)) {
            throw new WidgetException(_t('Passkey 请求格式错误'), 400);
        }
        return $data;
    }

    private function decode(string $value): string
    {
        $buffer = ByteBuffer::fromBase64Url($value);
        return $buffer->getBinaryString();
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** @param mixed $value */
    private function binaryValue($value): string
    {
        if ($value instanceof ByteBuffer) {
            return $value->getBinaryString();
        }

        return (string) $value;
    }

    private function json(array $data): void
    {
        $this->response->throwJson($data);
    }
}
