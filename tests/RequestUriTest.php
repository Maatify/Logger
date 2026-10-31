<?php

declare(strict_types=1);

namespace Maatify\Logger\Tests;

use Maatify\Logger\Logger;
use PHPUnit\Framework\TestCase;

final class RequestUriTest extends TestCase
{
    protected function tearDown(): void
    {
        Logger::logFullRequestUri(false);
    }

    public function testQueryStringIsStripped(): void
    {
        $out = Logger::sanitizeRequestUri('/cb?sig=SECRET&token=SECRET2');
        $this->assertSame('/cb', $out);
        $this->assertStringNotContainsString('SECRET', $out);
    }

    public function testFragmentIsStripped(): void
    {
        $this->assertSame('/a/b', Logger::sanitizeRequestUri('/a/b#frag=SECRET'));
    }

    public function testQueryOnlyOrMalformed(): void
    {
        $this->assertSame('', Logger::sanitizeRequestUri('?sig=SECRET'));
        $this->assertSame('/x', Logger::sanitizeRequestUri('/x?a=%zz&b[=SECRET'));
    }

    public function testMissingOrNonString(): void
    {
        $this->assertSame('', Logger::sanitizeRequestUri(null));
        $this->assertSame('', Logger::sanitizeRequestUri(['a']));
        $this->assertSame('', Logger::sanitizeRequestUri(123));
        $this->assertSame('', Logger::sanitizeRequestUri(''));
    }

    public function testDoubleSlashPrefix(): void
    {
        $this->assertSame('/evil.com/p', Logger::sanitizeRequestUri('//evil.com/p?t=SECRET'));
    }

    public function testOptInKeepsFullUri(): void
    {
        Logger::logFullRequestUri(true);
        $this->assertSame('/cb?sig=1', Logger::sanitizeRequestUri('/cb?sig=1'));
    }
}
