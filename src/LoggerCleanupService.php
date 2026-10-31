<?php
/**
 * Created by Maatify.dev
 * User: Maatify.dev
 * Date: 2025-09-04
 * Time: 12:42
 * Project: Logger
 * IDE: PhpStorm
 * https://www.Maatify.dev
 */

declare(strict_types=1);

namespace Maatify\Logger;

use DateTimeImmutable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class LoggerCleanupService
{
    private const string GUARD_FILE = 'index.php';

    private string $logBasePath;

    public function __construct(?string $logBasePath = null)
    {
        $this->logBasePath = $logBasePath ?? dirname(__DIR__, 4) . '/logs';
    }

    /**
     * يمسح كل ملفات اللوج اللي أقدم من عدد أيام معين
     */
    public function deleteOlderThanDays(int $days): void
    {
        $threshold = (new DateTimeImmutable())->modify("-{$days} days")->getTimestamp();
        $this->cleanup($threshold);
    }

    /**
     * يمسح كل ملفات اللوج اللي أقدم من عدد ساعات معين
     */
    public function deleteOlderThanHours(int $hours): void
    {
        $threshold = (new DateTimeImmutable())->modify("-{$hours} hours")->getTimestamp();
        $this->cleanup($threshold);
    }

    private function cleanup(int $threshold): void
    {
        if (!is_dir($this->logBasePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->logBasePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            // Symlinks are never followed or deleted: the target may live outside the log directory.
            if ($file->isLink()) {
                continue;
            }
            $path = $file->getPathname();
            if ($file->isFile()) {
                // The access-guard file is removed only together with its folder, never on age alone
                if ($file->getFilename() !== self::GUARD_FILE && $file->getMTime() < $threshold) {
                    @unlink($path);
                }
            } elseif ($file->isDir()) {
                $this->removeIfEmpty($path, $threshold);
            }
        }
    }

    /**
     * A folder holding nothing but an old-enough guard file counts as empty; a fresh folder that the logger
     * has just prepared is left alone.
     */
    private function removeIfEmpty(string $dir, int $threshold): void
    {
        $entries = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
        if ($entries === [self::GUARD_FILE]) {
            $guard = $dir . '/' . self::GUARD_FILE;
            if (is_link($guard)) {
                return; // symlinks are never deleted, so the folder is not empty
            }
            $mtime = @filemtime($guard);
            if ($mtime !== false && $mtime < $threshold) {
                @unlink($guard);
                $entries = [];
            }
        }
        if ($entries === []) {
            @rmdir($dir);
        }
    }
}
