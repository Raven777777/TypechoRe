<?php

declare(strict_types=1);

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use TypechoRe\Tests\WebAuthnTestFixture;

const WEBAUTHN_TEST_ROOT = __DIR__ . '/..';

require_once WEBAUTHN_TEST_ROOT . '/var/lbuchs/WebAuthn/WebAuthn.php';
require_once __DIR__ . '/WebAuthnTestFixture.php';

$checks = 0;

function webauthnCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function webauthnExpectFailure(callable $operation, int $expectedCode, string $label): void
{
    try {
        $operation();
    } catch (WebAuthnException $e) {
        webauthnCheck($e->getCode() === $expectedCode, "{$label}: expected WebAuthn error {$expectedCode}, got {$e->getCode()} ({$e->getMessage()})");
        return;
    }

    webauthnCheck(false, "{$label}: forged or invalid ceremony was accepted");
}

/**
 * @return array{
 *   webAuthn: WebAuthn,
 *   challenge: string,
 *   clientDataJSON: string,
 *   authenticatorData: string,
 *   signature: string
 * }
 */
function makeWebAuthnAssertionCase(
    string $rpId,
    \OpenSSLAsymmetricKey $privateKey,
    string $origin = 'https://example.test',
    int $flags = 0x05,
    int $signCount = 2,
    ?string $rpIdHash = null
): array {
    $webAuthn = new WebAuthn('TypechoRe test', $rpId, ['none'], true);
    $webAuthn->getGetArgs([], 60, false, false, false, false, false, 'required');
    $challenge = $webAuthn->getChallenge()->getBinaryString();
    $clientDataJSON = WebAuthnTestFixture::clientData('webauthn.get', $challenge, $origin);
    $authenticatorData = WebAuthnTestFixture::authenticatorData($rpId, $flags, $signCount, $rpIdHash);

    return [
        'webAuthn' => $webAuthn,
        'challenge' => $challenge,
        'clientDataJSON' => $clientDataJSON,
        'authenticatorData' => $authenticatorData,
        'signature' => WebAuthnTestFixture::signAssertion($privateKey, $clientDataJSON, $authenticatorData),
    ];
}

/**
 * @param array{
 *   webAuthn: WebAuthn,
 *   challenge: string,
 *   clientDataJSON: string,
 *   authenticatorData: string,
 *   signature: string
 * } $case
 */
function verifyWebAuthnAssertion(
    array $case,
    string $publicKey,
    ?string $expectedChallenge = null,
    ?string $signature = null,
    int $previousCounter = 1
): bool {
    return $case['webAuthn']->processGet(
        $case['clientDataJSON'],
        $case['authenticatorData'],
        $signature ?? $case['signature'],
        $publicKey,
        $expectedChallenge ?? $case['challenge'],
        $previousCounter,
        true
    );
}

$rpId = 'example.test';
$origin = 'https://example.test';
$privateKey = WebAuthnTestFixture::createP256PrivateKey();
$credentialId = random_bytes(32);
$registration = new WebAuthn('TypechoRe test', $rpId, ['none'], true);
$registration->getCreateArgs('1', 'admin', 'Admin', 60, 'required', 'required');
$registrationChallenge = $registration->getChallenge()->getBinaryString();
$registrationClientData = WebAuthnTestFixture::clientData('webauthn.create', $registrationChallenge, $origin);
$attestationObject = WebAuthnTestFixture::attestationObject($privateKey, $rpId, $credentialId);

$credential = $registration->processCreate(
    $registrationClientData,
    $attestationObject,
    $registrationChallenge,
    true,
    true,
    false
);
webauthnCheck($credential->credentialId === $credentialId, 'registration returned the wrong credential ID');
webauthnCheck(is_string($credential->credentialPublicKey) && str_contains($credential->credentialPublicKey, 'BEGIN PUBLIC KEY'), 'registration did not produce a public key');
webauthnCheck($credential->userPresent && $credential->userVerified, 'registration did not retain UP/UV flags');
webauthnCheck($credential->signatureCounter === 1, 'registration did not retain the authenticator counter');
$credentialPublicKey = $credential->credentialPublicKey;

$registrationFailure = static function (string $clientDataJSON, int $flags = 0x45, ?string $rpIdHash = null) use (
    $registration,
    $privateKey,
    $rpId,
    $credentialId,
    $registrationChallenge
): void {
    $registration->processCreate(
        $clientDataJSON,
        WebAuthnTestFixture::attestationObject($privateKey, $rpId, $credentialId, 1, $flags, $rpIdHash),
        $registrationChallenge,
        true,
        true,
        false
    );
};

$incorrectChallenge = $registrationChallenge ^ str_repeat("\x01", strlen($registrationChallenge));
webauthnExpectFailure(
    static fn() => $registrationFailure(WebAuthnTestFixture::clientData('webauthn.create', $incorrectChallenge, $origin)),
    WebAuthnException::INVALID_CHALLENGE,
    'registration challenge'
);
webauthnExpectFailure(
    static fn() => $registrationFailure(WebAuthnTestFixture::clientData('webauthn.create', $registrationChallenge, 'https://attacker.invalid')),
    WebAuthnException::INVALID_ORIGIN,
    'registration origin'
);
webauthnExpectFailure(
    static fn() => $registrationFailure(
        $registrationClientData,
        0x45,
        hash('sha256', 'attacker.invalid', true)
    ),
    WebAuthnException::INVALID_RELYING_PARTY,
    'registration RP ID hash'
);
webauthnExpectFailure(
    static fn() => $registrationFailure(
        $registrationClientData,
        0x44
    ),
    WebAuthnException::USER_PRESENT,
    'registration user presence'
);
webauthnExpectFailure(
    static fn() => $registrationFailure(
        $registrationClientData,
        0x41
    ),
    WebAuthnException::USER_VERIFICATED,
    'registration user verification'
);

$validAssertion = makeWebAuthnAssertionCase($rpId, $privateKey);
webauthnCheck(
    verifyWebAuthnAssertion($validAssertion, $credentialPublicKey),
    'valid signed assertion was rejected'
);
webauthnCheck(
    $validAssertion['webAuthn']->getSignatureCounter() === 2,
    'valid assertion did not expose the updated signature counter'
);

$incorrectAssertionChallenge = makeWebAuthnAssertionCase($rpId, $privateKey);
$wrongExpectedChallenge = $incorrectAssertionChallenge['challenge'] ^ str_repeat("\x01", strlen($incorrectAssertionChallenge['challenge']));
webauthnExpectFailure(
    static fn() => verifyWebAuthnAssertion($incorrectAssertionChallenge, $credentialPublicKey, $wrongExpectedChallenge),
    WebAuthnException::INVALID_CHALLENGE,
    'assertion challenge'
);

$incorrectAssertionOrigin = makeWebAuthnAssertionCase($rpId, $privateKey, 'https://attacker.invalid');
webauthnExpectFailure(
    static fn() => verifyWebAuthnAssertion($incorrectAssertionOrigin, $credentialPublicKey),
    WebAuthnException::INVALID_ORIGIN,
    'assertion origin'
);

$incorrectAssertionRpId = makeWebAuthnAssertionCase(
    $rpId,
    $privateKey,
    $origin,
    0x05,
    2,
    hash('sha256', 'attacker.invalid', true)
);
webauthnExpectFailure(
    static fn() => verifyWebAuthnAssertion($incorrectAssertionRpId, $credentialPublicKey),
    WebAuthnException::INVALID_RELYING_PARTY,
    'assertion RP ID hash'
);

$missingPresence = makeWebAuthnAssertionCase($rpId, $privateKey, $origin, 0x04);
webauthnExpectFailure(
    static fn() => verifyWebAuthnAssertion($missingPresence, $credentialPublicKey),
    WebAuthnException::USER_PRESENT,
    'assertion user presence'
);

$missingVerification = makeWebAuthnAssertionCase($rpId, $privateKey, $origin, 0x01);
webauthnExpectFailure(
    static fn() => verifyWebAuthnAssertion($missingVerification, $credentialPublicKey),
    WebAuthnException::USER_VERIFICATED,
    'assertion user verification'
);

$forgedSignature = makeWebAuthnAssertionCase($rpId, $privateKey);
$forgedSignatureBytes = $forgedSignature['signature'];
$forgedSignatureBytes[0] = chr(ord($forgedSignatureBytes[0]) ^ 1);
webauthnExpectFailure(
    static fn() => verifyWebAuthnAssertion($forgedSignature, $credentialPublicKey, null, $forgedSignatureBytes),
    WebAuthnException::INVALID_SIGNATURE,
    'assertion signature'
);

$replayedCounter = makeWebAuthnAssertionCase($rpId, $privateKey, $origin, 0x05, 2);
webauthnExpectFailure(
    static fn() => verifyWebAuthnAssertion($replayedCounter, $credentialPublicKey, null, null, 2),
    WebAuthnException::SIGNATURE_COUNTER,
    'assertion signature counter'
);

echo "PASS: WebAuthn registration and assertion tests ({$checks} checks)\n";
