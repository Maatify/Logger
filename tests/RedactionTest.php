<?php

declare(strict_types=1);

namespace Maatify\Logger\Tests;

use Maatify\Logger\Logger;
use PHPUnit\Framework\TestCase;

final class RedactionTest extends TestCase
{
    /** @param array<array-key, mixed> $data */
    private static function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    protected function tearDown(): void
    {
        Logger::setRedactor(null);
        Logger::setSensitiveKeys(['password', 'passwd', 'secret', 'token', 'authorization', 'api_key', 'apikey', 'sig', 'signature', 'otp', 'cookie']);
    }

    public function testSensitiveKeysAreMaskedRecursively(): void
    {
        $out = Logger::redactContext(['user' => 'a', 'Password' => 'P1', 'nested' => ['api_token' => 'T1']]);
        $this->assertSame('a', $out['user']);
        $this->assertStringNotContainsString('P1', self::json($out));
        $this->assertStringNotContainsString('T1', self::json($out));
    }

    public function testSecretsInExceptionTextAreScrubbed(): void
    {
        $out = Logger::redactContext(['exception' => [
            'message' => 'GET https://x/cb?sig=SECRET&ok=1 failed',
            'trace'   => '#0 call(token=SECRET2)',
        ]]);
        $json = self::json($out);
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
        $this->assertStringNotContainsString('EG123', self::json($out));
        $this->assertSame('visible', $out['password']);
    }

    public function testThrowableInContextIsScrubbed(): void
    {
        $e = new \RuntimeException('token=SECRET123', 5, new \LogicException('password=SECRET9'));
        $out = Logger::redactContext(['exception' => $e, 'nested' => ['deep' => $e]]);
        $json = self::json($out);
        $this->assertStringNotContainsString('SECRET', $json);
        $this->assertStringContainsString('RuntimeException', $json);
        $this->assertIsArray($out['exception']);
        $this->assertSame('RuntimeException', $out['exception']['class']);
    }

    public function testBearerTokensAreScrubbed(): void
    {
        foreach (['Authorization: Bearer eyJ-SECRET-TOKEN', 'authorization=Basic dXNlcjpTRUNSRVQ=', 'sent header Bearer abc.DEF-SECRET_1 ok'] as $text) {
            $out = Logger::redactContext(['m' => $text, 'exception' => ['message' => $text]]);
            $this->assertStringNotContainsString('SECRET', self::json($out), $text);
            $this->assertStringNotContainsString('dXNlcjpTRUNSRVQ', self::json($out), $text);
        }
    }

    public function testCircularAndDeepContextDoesNotExhaustMemory(): void
    {
        $a = ['k' => 'v'];
        $a['self'] = &$a;
        $out = Logger::redactContext(['loop' => $a]);
        $this->assertIsArray($out['loop']);
        $this->assertSame('v', $out['loop']['k']);

        $deep = ['x' => 'end'];
        for ($i = 0; $i < 500; $i++) {
            $deep = ['n' => $deep];
        }
        $this->assertNotEmpty(Logger::redactContext($deep));
    }

    public function testJsonSerializableObjectsAreMasked(): void
    {
        $obj = new class implements \JsonSerializable {
            /** @return array<string, string> */
            public function jsonSerialize(): array
            {
                return ['password' => 'SECRET123', 'note' => 'token=SECRET124', 'ok' => 'visible'];
            }
        };
        $out = Logger::redactContext(['details' => $obj, 'nested' => ['deep' => $obj]]);
        $json = self::json($out);
        $this->assertStringNotContainsString('SECRET', $json);
        $this->assertStringContainsString('visible', $json);
    }

    public function testStringableObjectsAreScrubbed(): void
    {
        $obj = new class implements \Stringable {
            public function __toString(): string
            {
                return 'failed with token=SECRET200';
            }
        };
        $out = Logger::redactContext(['s' => $obj]);
        $this->assertIsString($out['s']);
        $this->assertStringNotContainsString('SECRET', $out['s']);
    }

    public function testPlainObjectPublicPropertiesAreMasked(): void
    {
        $obj = new \stdClass();
        $obj->password = 'SECRET300';
        $obj->user = 'ali';
        $obj->child = new \stdClass();
        $obj->child->api_key = 'SECRET301';
        $json = self::json(Logger::redactContext(['o' => $obj]));
        $this->assertStringNotContainsString('SECRET', $json);
        $this->assertStringContainsString('ali', $json);
    }

    public function testCircularAndThrowingObjectsNeverBreakLogging(): void
    {
        $a = new \stdClass();
        $a->self = $a;
        $a->label = 'loop';
        $bad = new class implements \JsonSerializable {
            public function jsonSerialize(): never
            {
                throw new \RuntimeException('boom token=SECRET400');
            }
        };
        $badString = new class implements \Stringable {
            public function __toString(): string
            {
                throw new \RuntimeException('boom');
            }
        };
        $out = Logger::redactContext(['a' => $a, 'bad' => $bad, 'badString' => $badString]);
        $this->assertStringNotContainsString('SECRET', self::json($out));
    }

    public function testRedactorOutputIsRescanned(): void
    {
        Logger::setRedactor(static fn(array $c): array => $c + ['password' => 'SECRET500', 'note' => 'token=SECRET501']);
        $this->assertStringNotContainsString('SECRET', self::json(Logger::redactContext([])));
    }
}
