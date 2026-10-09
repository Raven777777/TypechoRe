<?php

declare(strict_types=1);

namespace TypechoRe\Tests;

/**
 * Builds synthetic WebAuthn payloads for server-side ceremony tests.
 * The generated private key is ephemeral and must never be used outside tests.
 */
final class WebAuthnTestFixture
{
    public static function createP256PrivateKey(): \OpenSSLAsymmetricKey
    {
        $configPath = tempnam(sys_get_temp_dir(), 'typechore-openssl-');
        if (false === $configPath) {
            throw new \RuntimeException('Could not create a temporary OpenSSL config');
        }

        $config = "[ req ]\ndistinguished_name = req_distinguished_name\n[ req_distinguished_name ]\n";
        if (false === file_put_contents($configPath, $config)) {
            @unlink($configPath);
            throw new \RuntimeException('Could not write a temporary OpenSSL config');
        }

        register_shutdown_function(static function () use ($configPath): void {
            @unlink($configPath);
        });

        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
            'config' => $configPath,
        ]);

        if (!$key instanceof \OpenSSLAsymmetricKey) {
            throw new \RuntimeException('Could not generate the test P-256 key');
        }

        return $key;
    }

    public static function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $encoded): string
    {
        $encoded = strtr($encoded, '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $decoded = base64_decode($encoded, true);
        if (false === $decoded) {
            throw new \InvalidArgumentException('Invalid base64url fixture value');
        }

        return $decoded;
    }

    public static function clientData(string $type, string $challenge, string $origin): string
    {
        return json_encode([
            'type' => $type,
            'challenge' => self::base64UrlEncode($challenge),
            'origin' => $origin,
            'crossOrigin' => false,
        ], JSON_THROW_ON_ERROR);
    }

    public static function attestationObject(
        \OpenSSLAsymmetricKey $privateKey,
        string $rpId,
        string $credentialId,
        int $signCount = 1,
        int $flags = 0x45,
        ?string $rpIdHash = null
    ): string {
        if (strlen($credentialId) > 0xffff) {
            throw new \InvalidArgumentException('Test credential ID is too long');
        }

        $details = openssl_pkey_get_details($privateKey);
        $ec = is_array($details) ? ($details['ec'] ?? null) : null;
        if (!is_array($ec) || !is_string($ec['x'] ?? null) || !is_string($ec['y'] ?? null)) {
            throw new \RuntimeException('Could not read test P-256 public coordinates');
        }

        $coseKey = self::cborMap([
            [self::cborInteger(1), self::cborInteger(2)],
            [self::cborInteger(3), self::cborInteger(-7)],
            [self::cborInteger(-1), self::cborInteger(1)],
            [self::cborInteger(-2), self::cborBytes($ec['x'])],
            [self::cborInteger(-3), self::cborBytes($ec['y'])],
        ]);

        $rpIdHash ??= hash('sha256', $rpId, true);
        if (32 !== strlen($rpIdHash)) {
            throw new \InvalidArgumentException('RP ID hash must be 32 bytes');
        }

        $authenticatorData = $rpIdHash
            . chr($flags)
            . pack('N', $signCount)
            . str_repeat("\0", 16)
            . pack('n', strlen($credentialId))
            . $credentialId
            . $coseKey;

        return self::cborMap([
            [self::cborText('fmt'), self::cborText('none')],
            [self::cborText('attStmt'), "\xa0"],
            [self::cborText('authData'), self::cborBytes($authenticatorData)],
        ]);
    }

    public static function authenticatorData(
        string $rpId,
        int $flags = 0x05,
        int $signCount = 2,
        ?string $rpIdHash = null
    ): string {
        $rpIdHash ??= hash('sha256', $rpId, true);
        if (32 !== strlen($rpIdHash)) {
            throw new \InvalidArgumentException('RP ID hash must be 32 bytes');
        }

        return $rpIdHash . chr($flags) . pack('N', $signCount);
    }

    public static function signAssertion(
        \OpenSSLAsymmetricKey $privateKey,
        string $clientDataJSON,
        string $authenticatorData
    ): string {
        $signature = '';
        $signedData = $authenticatorData . hash('sha256', $clientDataJSON, true);
        if (!openssl_sign($signedData, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            $errors = [];
            while ($error = openssl_error_string()) {
                $errors[] = $error;
            }
            throw new \RuntimeException('Could not sign the test assertion: ' . implode('; ', $errors));
        }

        return $signature;
    }

    /**
     * @param list<array{string, string}> $entries
     */
    private static function cborMap(array $entries): string
    {
        $data = self::cborHead(5, count($entries));
        foreach ($entries as [$key, $value]) {
            $data .= $key . $value;
        }

        return $data;
    }

    private static function cborText(string $value): string
    {
        return self::cborHead(3, strlen($value)) . $value;
    }

    private static function cborBytes(string $value): string
    {
        return self::cborHead(2, strlen($value)) . $value;
    }

    private static function cborInteger(int $value): string
    {
        return $value >= 0
            ? self::cborHead(0, $value)
            : self::cborHead(1, -1 - $value);
    }

    private static function cborHead(int $majorType, int $value): string
    {
        if ($value < 24) {
            return chr(($majorType << 5) | $value);
        }

        if ($value <= 0xff) {
            return chr(($majorType << 5) | 24) . chr($value);
        }

        if ($value <= 0xffff) {
            return chr(($majorType << 5) | 25) . pack('n', $value);
        }

        return chr(($majorType << 5) | 26) . pack('N', $value);
    }
}
