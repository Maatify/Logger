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
        $this->assertSame('/p', Logger::sanitizeRequestUri('//evil.com/p?t=SECRET'));
        $this->assertSame('/p', Logger::sanitizeRequestUri('//user:pass123@evil.com/p?t=SECRET'));
    }

    public function testOptInKeepsFullUri(): void
    {
        Logger::logFullRequestUri(true);
        $this->assertSame('/cb?sig=1', Logger::sanitizeRequestUri('/cb?sig=1'));
    }

    public function testAbsoluteUrisDropSchemeAuthorityAndUserinfo(): void
    {
        $cases = [
            'https://user:pass123@example.com/callback?sig=SECRET456' => '/callback',
            'http://example.com:8080/a/b#frag'                        => '/a/b',
            'HTTPS://u:p@example.com'                                 => '/',
            'https://user:pass123@example.com?x=SECRET'               => '/',
            'ftp://u:p@h/p'                                           => '/p',
        ];
        foreach ($cases as $input => $expected) {
            $out = Logger::sanitizeRequestUri($input);
            $this->assertSame($expected, $out, $input);
            $this->assertStringNotContainsString('pass123', $out);
        }
    }

    public function testNonOriginFormTargetsAreNotLogged(): void
    {
        $this->assertSame('', Logger::sanitizeRequestUri('user:pass123@example.com:443'));
        $this->assertSame('', Logger::sanitizeRequestUri('*'));
    }
}
