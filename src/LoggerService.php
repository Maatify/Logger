<?php
/**
 * Created by Maatify.dev
 * User: Mohamed Abdulalim
 * Date: 2025-09-04
 * Project: maatify/logger
 */

declare(strict_types=1);

namespace Maatify\Logger;

use Psr\Log\LogLevel;
use Throwable;

final class LoggerService
{
    private static ?Logger $logger = null;

    private function __construct() {}
    private function __clone() {}

    private static function getLogger(): Logger
    {
        if (!self::$logger) {
            self::$logger = new Logger();
        }
        return self::$logger;
    }

    /**
     * واجهة عامة (generic)
     */
    public static function log(string $level, string|array|Throwable $message, string $logFile = 'app'): void
    {
        self::getLogger()->log($level, $message, ['logFile' => $logFile]);
    }

    // --- Wrappers لكل مستوى PSR-3 ---
    public static function emergency(string|array|Throwable $message, string $logFile = 'app'): void
    {
        self::log(LogLevel::EMERGENCY, $message, $logFile);
    }

    public static function alert(string|array|Throwable $message, string $logFile = 'app'): void
    {
        self::log(LogLevel::ALERT, $message, $logFile);
    }

    public static function critical(string|array|Throwable $message, string $logFile = 'app'): void
    {
        self::log(LogLevel::CRITICAL, $message, $logFile);
    }

    public static function error(string|array|Throwable $message, string $logFile = 'app'): void
    {
        self::log(LogLevel::ERROR, $message, $logFile);
    }

    public static function warning(string|array|Throwable $message, string $logFile = 'app'): void
    {
        self::log(LogLevel::WARNING, $message, $logFile);
    }

    public static function notice(string|array|Throwable $message, string $logFile = 'app'): void
    {
        self::log(LogLevel::NOTICE, $message, $logFile);
    }

    public static function info(string|array|Throwable $message, string $logFile = 'app'): void
    {
        self::log(LogLevel::INFO, $message, $logFile);
    }

    public static function debug(string|array|Throwable $message, string $logFile = 'app'): void
    {
        self::log(LogLevel::DEBUG, $message, $logFile);
    }

    /**
     * ميثود مختصر للتعامل مع Exceptions/Throwables
     */
    /**
     * ميثود مختصر للتعامل مع Exceptions/Throwables
     * يسمح بتمرير context إضافي (مثلاً userId, requestId, إلخ)
     */
    public static function logException(
        Throwable $e,
        string $logFile = 'exceptions',
        string $level = LogLevel::ERROR,
        array $context = []
    ): void {
        $exceptionContext = [
            'exception' => [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'code'    => $e->getCode(),
                'trace'   => $e->getTraceAsString(),
            ],
        ];

        $mergedContext = array_merge($context, $exceptionContext);

        self::getLogger()->log(
            $level,
            'Exception captured',
            [
                'logFile' => $logFile,
                ...$mergedContext,
            ]
        );
    }
}
