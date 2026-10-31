<?php

declare(strict_types=1);

namespace Maatify\Logger\Tests;

use Maatify\Logger\Logger;
use PHPUnit\Framework\TestCase;

final class RedactionTest extends TestCase
{
    protected function tearDown(): void
    {
        Logger::setRedactor(null);
        Logger::setSensitiveKeys(['password', 'passwd', 'secret', 'token', 'authorization', 'api_key', 'apikey', 'sig', 'signature', 'otp', 'cookie']);
    }

    public function testSensitiveKeysAreMaskedRecursively(): void
    {
        $out = Logger::redactContext(['user' => 'a', 'Password' => 'P1', 'nested' => ['api_token' => 'T1']]);
        $this->assertSame('a', $out['user']);
        $this->assertStringNotContainsString('P1', json_encode($out));
        $this->assertStringNotContainsString('T1', json_encode($out));
    }

    public function testSecretsInExceptionTextAreScrubbed(): void
    {
        $out = Logger::redactContext(['exception' => [
            'message' => 'GET https://x/cb?sig=SECRET&ok=1 failed',
            'trace'   => '#0 call(token=SECRET2)',
        ]]);
        $json = json_encode($out);
        $this->assertStringNotContainsString('SECRET', $json);
        $this->assertStringContainsString('ok=1', $json);
    }

    public function testCustomRedactorRuns(): void
    {
        Logger::setRedactor(static fn(array $c) => $c + ['extra' => 'x'] + ['order' => 'masked']);
        $this->assertSame('x', Logger::redactContext([])['extra']);
    }

    public function testThrowingRedactorNeverLeaksOrThrows(): void
    {
        Logger::setRedactor(static function (array $c) { throw new \RuntimeException('boom'); });
        $out = Logger::redactContext(['note' => 'hello']);
        $this->assertSame(['redaction' => 'failed'], $out);
    }

    public function testCustomKeyList(): void
    {
        Logger::setSensitiveKeys(['iban']);
        $out = Logger::redactContext(['my_iban' => 'EG123', 'password' => 'visible']);
        $this->assertStringNotContainsString('EG123', json_encode($out));
        $this->assertSame('visible', $out['password']);
    }
}
