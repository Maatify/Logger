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
        foreach (['Logger.php', 'LoggerCleanupService.php'] as $file) {
            if (is_file($src . '/' . $file)) {
                copy($src . '/' . $file, $this->root . '/vendor/maatify/logger/src/' . $file);
            }
        }
    }

    protected function tearDown(): void
    {
        self::removeTree($this->root);
    }

    private static function removeTree(string $path): void
    {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? self::removeTree($full) : unlink($full);
        }
        rmdir($path);
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
$l->error("obj", ["details" => new class implements JsonSerializable { public function jsonSerialize(): array { return ["password" => "SECRET6"]; } }]);
$l->error("str", ["s" => new class implements Stringable { public function __toString(): string { return "token=SECRET7"; } }]);
$plain = new stdClass(); $plain->password = "SECRET8";
$l->error("plain", ["p" => $plain]);
$loop = ["a" => 1]; $loop["self"] = &$loop;
$l->error("loop", ["data" => $loop]);
');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));

        $files = glob($this->root . '/logs/*/*/*/*/app_*.log') ?: [];
        $this->assertNotEmpty($files, 'no log file written');
        $content = (string) file_get_contents((string) reset($files));

        $this->assertStringContainsString('visible', $content);
        foreach (['SECRET1', 'SECRET2', 'SECRET3', 'SECRET4', 'SECRET5', 'SECRET6', 'SECRET7', 'SECRET8'] as $secret) {
            $this->assertStringNotContainsString($secret, $content);
        }
    }

    public function testCleanupServiceDefaultTargetsTheLoggerDirectory(): void
    {
        $script = $this->root . '/cleanup.php';
        file_put_contents($script, '<?php
require ' . var_export(dirname(__DIR__) . '/vendor/autoload.php', true) . ';
require ' . var_export($this->root . '/vendor/maatify/logger/src/Logger.php', true) . ';
require ' . var_export($this->root . '/vendor/maatify/logger/src/LoggerCleanupService.php', true) . ';
(new Maatify\\Logger\\Logger())->info("old entry");
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(' . var_export($this->root . '/logs', true) . ', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) { if ($f->getExtension() === "log") { touch($f->getPathname(), time() - 2 * 86400); } }
(new Maatify\\Logger\\LoggerCleanupService())->deleteOlderThanDays(1);
');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
        $this->assertSame([], glob($this->root . '/logs/*/*/*/*/*.log') ?: [], 'old log files were not cleaned');
    }

    public function testMonologPathCreatesTheGuardFile(): void
    {
        $script = $this->root . '/guard.php';
        file_put_contents($script, '<?php
require ' . var_export(dirname(__DIR__) . '/vendor/autoload.php', true) . ';
require ' . var_export($this->root . '/vendor/maatify/logger/src/Logger.php', true) . ';
(new Maatify\\Logger\\Logger())->info("x");
');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
        $this->assertNotEmpty(glob($this->root . '/logs/*/*/*/*/index.php') ?: [], 'guard index.php missing');
    }

    public function testFallbackPathValidatesLevelAndSanitizesUri(): void
    {
        $script = $this->root . '/fallback.php';
        file_put_contents($script, '<?php
require ' . var_export(dirname(__DIR__) . '/vendor/autoload.php', true) . ';
require ' . var_export($this->root . '/vendor/maatify/logger/src/Logger.php', true) . ';
$_SERVER["REQUEST_URI"] = "https://user:pass123@example.com/callback?sig=SECRET9";
$l = new Maatify\\Logger\\Logger();
$p = new ReflectionProperty($l, "monolog");
$p->setValue($l, null);
try { $l->log("bogus-level", "x"); fwrite(STDERR, "unknown level accepted\n"); exit(3); } catch (Psr\\Log\\InvalidArgumentException) {}
$l->log("info", "fallback entry");
');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
        $files = glob($this->root . '/logs/*/*/*/*/app_*.log') ?: [];
        $this->assertNotEmpty($files, 'fallback wrote no file');
        $content = (string) file_get_contents((string) reset($files));
        $this->assertStringContainsString('/callback', $content);
        foreach (['pass123', 'SECRET9', 'example.com'] as $leak) {
            $this->assertStringNotContainsString($leak, $content);
        }
        $this->assertStringNotContainsString('bogus', $content);
    }

    public function testFallbackWorksWhenMonologIsReallyAbsent(): void
    {
        $psr = dirname(__DIR__) . '/vendor/psr/log/src/';
        $script = $this->root . '/nomonolog.php';
        file_put_contents($script, '<?php
spl_autoload_register(static function (string $c): void {
    if (str_starts_with($c, "Psr\\\\Log\\\\")) {
        require ' . var_export($psr, true) . ' . substr($c, 8) . ".php";
    }
});
require ' . var_export($this->root . '/vendor/maatify/logger/src/Logger.php', true) . ';
if (class_exists("Monolog\\\\Logger")) { fwrite(STDERR, "monolog unexpectedly loadable\n"); exit(4); }
$l = new Maatify\\Logger\\Logger();
$l->log("ERROR", "no monolog entry", ["password" => "SECRET11"]);
try { $l->log("bogus", "x"); fwrite(STDERR, "unknown level accepted\n"); exit(3); } catch (Psr\\Log\\InvalidArgumentException) {}
echo "ok";
');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
        $files = glob($this->root . '/logs/*/*/*/*/app_*.log') ?: [];
        $this->assertNotEmpty($files, 'fallback wrote no file');
        $content = (string) file_get_contents((string) reset($files));
        $this->assertStringContainsString('no monolog entry', $content);
        $this->assertStringNotContainsString('SECRET11', $content);
    }
}
