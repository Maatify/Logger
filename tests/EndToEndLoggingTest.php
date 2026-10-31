<?php

declare(strict_types=1);

namespace Maatify\Logger\Tests;

use PHPUnit\Framework\TestCase;

/**
 * System-level proof through the public API: the library is laid out as vendor/maatify/logger
 * inside a throw-away consumer root, so logs land in that temp root (never in the repository).
 * LOGGER_SRC may point at another src/ directory (used to capture RED against older code).
 */
final class EndToEndLoggingTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/maatify-logger-e2e-' . bin2hex(random_bytes(4));
        $src = getenv('LOGGER_SRC') ?: dirname(__DIR__) . '/src';
        mkdir($this->root . '/vendor/maatify/logger/src', 0775, true);
        copy($src . '/Logger.php', $this->root . '/vendor/maatify/logger/src/Logger.php');
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->root);
    }

    public function testSecretsNeverReachTheLogFile(): void
    {
        $script = $this->root . '/run.php';
        file_put_contents($script, '<?php
require ' . var_export(dirname(__DIR__) . '/vendor/autoload.php', true) . ';
require ' . var_export($this->root . '/vendor/maatify/logger/src/Logger.php', true) . ';
$l = new Maatify\Logger\Logger();
$l->error("callback https://x/cb?sig=SECRET1 failed", ["password" => "SECRET2", "ok" => "visible"]);
$l->error(new RuntimeException("gateway said token=SECRET3"));
$l->error("pay failed", ["exception" => new RuntimeException("token=SECRET4")]);
$l->error("hdr Authorization: Bearer SECRET5");
$loop = ["a" => 1]; $loop["self"] = &$loop;
$l->error("loop", ["data" => $loop]);
');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));

        $files = glob($this->root . '/logs/*/*/*/*/app_*.log');
        $this->assertNotEmpty($files, 'no log file written');
        $content = (string) file_get_contents($files[0]);

        $this->assertStringContainsString('visible', $content);
        foreach (['SECRET1', 'SECRET2', 'SECRET3', 'SECRET4', 'SECRET5'] as $secret) {
            $this->assertStringNotContainsString($secret, $content);
        }
    }
}
