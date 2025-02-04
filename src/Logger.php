<?php
/**
 * Created by Maatify.dev
 * User: Mohamed Abdulalim
 * Date: 2025-09-04
 * Project: maatify/logger
 */

declare(strict_types=1);

namespace Maatify\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

class Logger implements LoggerInterface
{
    private static string $extension = 'log';
    private const string INTERNAL_ERROR_FILE = __DIR__ . '/../../error_logger.log';

    private ?\Monolog\Logger $monolog = null;

    // --- Legacy Support (ممكن تسيبهم لو لسه عندك كود قديم)
    public const string LEVEL_INFO  = LogLevel::INFO;
    public const string LEVEL_ERROR = LogLevel::ERROR;
    public const string LEVEL_DEBUG = LogLevel::DEBUG;

    public function __construct()
    {
        if (class_exists(\Monolog\Logger::class)) {
            $this->monolog = new \Monolog\Logger('maatify');
            $handler = new \Monolog\Handler\StreamHandler(
                $this->buildFilePath('app'),
                \Monolog\Logger::DEBUG // int level (100) → للـ handler
            );
            $this->monolog->pushHandler($handler);
        }
    }

    /**
     * Legacy method
     */
    public static function RecordLog(
        string|array|Throwable $message,
        string $level = LogLevel::INFO,
        string $logFile = 'app'
    ): void {
        (new self())->log($level, $message, ['logFile' => $logFile]);
    }

    // --- PSR-3 Methods ---
    public function emergency($message, array $context = []): void { $this->log(LogLevel::EMERGENCY, $message, $context); }
    public function alert($message, array $context = []): void     { $this->log(LogLevel::ALERT, $message, $context); }
    public function critical($message, array $context = []): void  { $this->log(LogLevel::CRITICAL, $message, $context); }
    public function error($message, array $context = []): void     { $this->log(LogLevel::ERROR, $message, $context); }
    public function warning($message, array $context = []): void   { $this->log(LogLevel::WARNING, $message, $context); }
    public function notice($message, array $context = []): void    { $this->log(LogLevel::NOTICE, $message, $context); }
    public function info($message, array $context = []): void      { $this->log(LogLevel::INFO, $message, $context); }
    public function debug($message, array $context = []): void     { $this->log(LogLevel::DEBUG, $message, $context); }

    /**
     * Core logger
     */
    public function log($level, $message, array $context = []): void
    {
        // Wrap behavior → message لازم يكون string
        if (is_array($message)) {
            $context['data'] = $message;
            $message = 'Array log entry';
        } elseif ($message instanceof Throwable) {
            $context['exception'] = [
                'message' => $message->getMessage(),
                'file'    => $message->getFile(),
                'line'    => $message->getLine(),
                'code'    => $message->getCode(),
                'trace'   => $message->getTraceAsString(),
            ];
            $message = 'Exception log entry';
        }

        if ($this->monolog) {
            // ✅ Monolog backend
            $this->monolog->log($level, $message, $context);
            return;
        }

        // ❌ fallback → ملفات
        try {
            $logFile = $context['logFile'] ?? 'app';
            unset($context['logFile']);

            $payload = [
                'level'   => strtoupper($level),
                'time'    => date("Y-m-d H:i:s"),
                'server'  => [
                    'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                    'HTTP_HOST'   => $_SERVER['HTTP_HOST'] ?? '',
                    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
                    'USER_AGENT'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ],
                'message' => $message,
                'context' => $context,
            ];

            $logLine = json_encode(
                           $payload,
                           JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                       ) . PHP_EOL;

            $targetDir = $this->createFolderByDate();

            if (str_contains($logFile, '/')) {
                $folders = explode('/', $logFile);
                $logFile = array_pop($folders);
                foreach ($folders as $folder) {
                    $targetDir .= DIRECTORY_SEPARATOR . $folder;
                    if (!$this->createFolder($targetDir)) {
                        $this->logInternalError("Failed to create folder: {$targetDir}");
                        return;
                    }
                }
            }

            $filePath = sprintf(
                '%s/%s_%s.%s',
                $targetDir,
                $logFile,
                date('Y-m-d-H'),
                self::$extension
            );

            if (@file_put_contents($filePath, $logLine, FILE_APPEND | LOCK_EX) === false) {
                $this->logInternalError("Failed to write log file: {$filePath}");
            }
        } catch (Throwable $e) {
            $this->logInternalError("Logger Error: " . $e->getMessage());
        }
    }

    // --- Helpers ---
    private function buildFilePath(string $logFile): string
    {
        $dir = sprintf(
            "%s/logs/%s/%s/%s/%s",
            dirname(__DIR__, 2),
            date('Y'), date('m'), date('d'), date('H')
        );
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        return $dir . '/' . $logFile . '_' . date('Y-m-d-H') . '.' . self::$extension;
    }

    private function logInternalError(string $errorMsg): void
    {
        $logEntry = sprintf("[%s] %s%s", date('Y-m-d H:i:s'), $errorMsg, PHP_EOL);
        @file_put_contents(self::INTERNAL_ERROR_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function createFolderByDate(): string
    {
        $targetDir = sprintf(
            '%s/logs/%s/%s/%s/%s',
            dirname(__DIR__, 2),
            date('Y'),
            date('m'),
            date('d'),
            date('H')
        );

        if (!$this->createFolder($targetDir)) {
            $this->logInternalError("Failed to create date folder: {$targetDir}");
        }

        return $targetDir;
    }

    private function createFolder(string $targetDir): bool
    {
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                return false;
            }
            @file_put_contents(
                $targetDir . '/index.php',
                "<?php http_response_code(404); exit;"
            );
        }
        return true;
    }
}
