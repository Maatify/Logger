<?php

declare(strict_types=1);

namespace Maatify\Logger\Tests;

use Maatify\Logger\LoggerCleanupService;
use PHPUnit\Framework\TestCase;

final class CleanupTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/maatify-cleanup-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/2025/01/01/01', 0775, true);
    }

    protected function tearDown(): void
    {
        self::remove($this->dir);
    }

    private static function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                is_dir($path . '/' . $entry) ? self::remove($path . '/' . $entry) : unlink($path . '/' . $entry);
            }
        }
        rmdir($path);
    }

    private function touchOld(string $file): void
    {
        touch($file, time() - 3 * 86400);
    }

    public function testGuardFileIsKeptWhileRecentLogsRemain(): void
    {
        $leaf = $this->dir . '/2025/01/01/01';
        file_put_contents($leaf . '/index.php', '<?php http_response_code(404); exit;');
        file_put_contents($leaf . '/app_recent.log', 'recent');
        $this->touchOld($leaf . '/index.php');

        (new LoggerCleanupService($this->dir))->deleteOlderThanDays(1);

        $this->assertFileExists($leaf . '/index.php');
        $this->assertFileExists($leaf . '/app_recent.log');
    }

    public function testOldLogsAndTheirGuardAreRemovedTogether(): void
    {
        $leaf = $this->dir . '/2025/01/01/01';
        file_put_contents($leaf . '/index.php', '<?php http_response_code(404); exit;');
        file_put_contents($leaf . '/app_old.log', 'old');
        $this->touchOld($leaf . '/app_old.log');
        $this->touchOld($leaf . '/index.php');

        (new LoggerCleanupService($this->dir))->deleteOlderThanDays(1);

        $this->assertDirectoryDoesNotExist($leaf);
        $this->assertDirectoryExists($this->dir);
    }

    public function testFreshFolderWithOnlyItsGuardIsKept(): void
    {
        $leaf = $this->dir . '/2025/01/01/01';
        file_put_contents($leaf . '/index.php', '<?php http_response_code(404); exit;');

        (new LoggerCleanupService($this->dir))->deleteOlderThanDays(1);

        $this->assertFileExists($leaf . '/index.php');
    }

    public function testSymlinkedFileOutsideRootIsNeverDeleted(): void
    {
        $outside = sys_get_temp_dir() . '/maatify-outside-' . bin2hex(random_bytes(4));
        mkdir($outside);
        $target = $outside . '/external-file.txt';
        file_put_contents($target, 'keep me');
        $this->touchOld($target);
        symlink($target, $this->dir . '/external.log');

        try {
            (new LoggerCleanupService($this->dir))->deleteOlderThanDays(1);
            $this->assertFileExists($target, 'file outside the log root was deleted through a symlink');
            $this->assertSame('keep me', file_get_contents($target));
        } finally {
            @unlink($this->dir . '/external.log');
            self::remove($outside);
        }
    }

    public function testSymlinkedDirectoryOutsideRootIsNeverTouched(): void
    {
        $outside = sys_get_temp_dir() . '/maatify-outside-' . bin2hex(random_bytes(4));
        mkdir($outside);
        file_put_contents($outside . '/index.php', '<?php http_response_code(404); exit;');
        $this->touchOld($outside . '/index.php');
        symlink($outside, $this->dir . '/alias');

        try {
            (new LoggerCleanupService($this->dir))->deleteOlderThanDays(1);
            $this->assertDirectoryExists($outside);
            $this->assertFileExists($outside . '/index.php');
        } finally {
            @unlink($this->dir . '/alias');
            self::remove($outside);
        }
    }

    public function testSymlinkNamedGuardFileIsKeptWithItsFolder(): void
    {
        $outside = sys_get_temp_dir() . '/maatify-outside-' . bin2hex(random_bytes(4));
        mkdir($outside);
        $target = $outside . '/guard-reference.php';
        file_put_contents($target, '<?php // external');
        $leaf = $this->dir . '/2025/01/01/01';
        symlink($target, $leaf . '/index.php');
        $this->touchOld($target);
        // age the link itself, not its target, so the age check cannot be what protects it
        exec('touch -h -t 202501010101 ' . escapeshellarg($leaf . '/index.php'));

        try {
            (new LoggerCleanupService($this->dir))->deleteOlderThanDays(1);
            $this->assertTrue(is_link($leaf . '/index.php'), 'the symlink itself was deleted');
            $this->assertDirectoryExists($leaf);
            $this->assertFileExists($target);
        } finally {
            @unlink($leaf . '/index.php');
            self::remove($outside);
        }
    }
}
