<?php
/**
 * Created by Maatify.dev
 * User: Mohamed Abdulalim
 * Date: 2025-09-04
 * Project: maatify/logger
 */

declare(strict_types=1);

namespace Maatify\Logger;

use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

class Logger implements LoggerInterface
{
    private static string $extension = 'log';
    private static bool $logFullRequestUri = false;

    private const string REDACTED = '***REDACTED***';

    /** Context keys (case-insensitive substring match) whose values are always masked. */
    private static array $sensitiveKeys = [
        'password', 'passwd', 'secret', 'token', 'authorization', 'api_key', 'apikey', 'sig', 'signature', 'otp', 'cookie',
    ];

    /** @var null|callable(array): array */
    private static $redactor = null;
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
            $handler = new StreamHandler(
                $this->buildFilePath('app'),
                LogLevel::DEBUG // int level (100) → للـ handler
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

        $context = self::redactContext($context);
        if (is_string($message)) {
            $message = self::scrubString($message);
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
                    'REQUEST_URI' => self::sanitizeRequestUri($_SERVER['REQUEST_URI'] ?? ''),
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

    // --- Redaction ---

    /**
     * Register an extra redaction hook, run after the built-in key masking.
     * It receives the whole context (including the 'exception' entry) and must return the array to log.
     * Pass null to remove it. If the hook throws, the context is replaced by a redaction-failed marker.
     */
    public static function setRedactor(?callable $redactor): void
    {
        self::$redactor = $redactor;
    }

    /**
     * Replace the list of sensitive context keys (case-insensitive substring match).
     *
     * @param string[] $keys
     */
    public static function setSensitiveKeys(array $keys): void
    {
        self::$sensitiveKeys = array_map('strtolower', $keys);
    }

    public static function redactContext(array $context): array
    {
        try {
            $context = self::maskKeys($context);
            if (self::$redactor !== null) {
                $context = (self::$redactor)($context);
            }
            return is_array($context) ? $context : ['redaction' => 'invalid redactor result'];
        } catch (Throwable) {
            return ['redaction' => 'failed'];
        }
    }

    private static function maskKeys(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $data[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $data[$key] = self::maskKeys($value);
            } elseif (is_string($value)) {
                $data[$key] = self::scrubString($value);
            }
        }
        return $data;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::$sensitiveKeys as $needle) {
            if ($needle !== '' && str_contains($key, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Mask "key=value" / "key: value" secrets embedded in free text (exception messages, traces, URLs).
     */
    private static function scrubString(string $text): string
    {
        $names = implode('|', array_map(static fn(string $k) => preg_quote($k, '/'), self::$sensitiveKeys));
        if ($names === '') {
            return $text;
        }
        $out = preg_replace(
            '/((?:[\w-]*(?:' . $names . ')[\w-]*)\s*[=:]\s*)("[^"]*"|\'[^\']*\'|[^\s&,;"\')]+)/i',
            '$1' . self::REDACTED,
            $text
        );
        return $out ?? $text;
    }

    // --- Helpers ---

    /**
     * Reduce a request URI to its path only (no query string, no fragment) so that
     * signatures, tokens and one-time codes never reach the log files.
     * Never throws; anything unusable yields ''.
     */
    public static function sanitizeRequestUri(mixed $uri): string
    {
        if (!is_string($uri) || $uri === '') {
            return '';
        }

        if (self::$logFullRequestUri) {
            return $uri;
        }

        // Cut at the first '?' or '#' (also covers malformed URIs parse_url rejects)
        $path = substr($uri, 0, strcspn($uri, '?#'));

        // "//host/path" would be read as an authority; collapse leading slashes to one
        if (str_starts_with($path, '//')) {
            $path = '/' . ltrim($path, '/');
        }

        return $path;
    }

    /**
     * Opt-in: log the full REQUEST_URI including the query string. Off by default.
     */
    public static function logFullRequestUri(bool $enabled): void
    {
        self::$logFullRequestUri = $enabled;
    }

    private function buildFilePath(string $logFile): string
    {
        $dir = sprintf(
            "%s/logs/%s/%s/%s/%s",
            dirname(__DIR__, 4),
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
            dirname(__DIR__, 4),
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
