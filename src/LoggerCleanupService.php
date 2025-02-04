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
    private string $logBasePath;

    public function __construct(?string $logBasePath = null)
    {
        $this->logBasePath = $logBasePath ?? dirname(__DIR__, 2) . '/logs';
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
            if ($file->isFile()) {
                if ($file->getMTime() < $threshold) {
                    @unlink($file->getRealPath());
                }
            } elseif ($file->isDir()) {
                // نحذف الفولدر لو فاضي
                @rmdir($file->getRealPath());
            }
        }
    }
}
