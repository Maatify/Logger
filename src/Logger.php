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
use Monolog\Level;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

class Logger implements LoggerInterface
{
    private static string $extension = 'log';
    private static bool $logFullRequestUri = false;

    private const string REDACTED = '***REDACTED***';
    private const int MAX_REDACTION_DEPTH = 8;

    /** PSR-3 level names accepted by log() (validated independently of Monolog). */
    private const array LEVELS = [
        LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR,
        LogLevel::WARNING, LogLevel::NOTICE, LogLevel::INFO, LogLevel::DEBUG,
    ];

    /**
     * Context keys (case-insensitive substring match) whose values are always masked.
     *
     * @var list<string>
     */
    private static array $sensitiveKeys = [
        'password', 'passwd', 'secret', 'token', 'authorization', 'api_key', 'apikey', 'sig', 'signature', 'otp', 'cookie',
    ];

    /** @var null|callable(array<array-key, mixed>): array<array-key, mixed> */
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
     *
     * @param string|array<array-key, mixed>|Throwable $message
     */
    public static function RecordLog(
        string|array|Throwable $message,
        string $level = LogLevel::INFO,
        string $logFile = 'app'
    ): void {
        (new self())->log($level, $message, ['logFile' => $logFile]);
    }

    // --- PSR-3 Methods ---
    /**
     * @param string|\Stringable|array<array-key, mixed>|Throwable $message
     * @param array<array-key, mixed> $context
     */
    public function emergency($message, array $context = []): void { $this->log(LogLevel::EMERGENCY, $message, $context); }
    /**
     * @param string|\Stringable|array<array-key, mixed>|Throwable $message
     * @param array<array-key, mixed> $context
     */
    public function alert($message, array $context = []): void     { $this->log(LogLevel::ALERT, $message, $context); }
    /**
     * @param string|\Stringable|array<array-key, mixed>|Throwable $message
     * @param array<array-key, mixed> $context
     */
    public function critical($message, array $context = []): void  { $this->log(LogLevel::CRITICAL, $message, $context); }
    /**
     * @param string|\Stringable|array<array-key, mixed>|Throwable $message
     * @param array<array-key, mixed> $context
     */
    public function error($message, array $context = []): void     { $this->log(LogLevel::ERROR, $message, $context); }
    /**
     * @param string|\Stringable|array<array-key, mixed>|Throwable $message
     * @param array<array-key, mixed> $context
     */
    public function warning($message, array $context = []): void   { $this->log(LogLevel::WARNING, $message, $context); }
    /**
     * @param string|\Stringable|array<array-key, mixed>|Throwable $message
     * @param array<array-key, mixed> $context
     */
    public function notice($message, array $context = []): void    { $this->log(LogLevel::NOTICE, $message, $context); }
    /**
     * @param string|\Stringable|array<array-key, mixed>|Throwable $message
     * @param array<array-key, mixed> $context
     */
    public function info($message, array $context = []): void      { $this->log(LogLevel::INFO, $message, $context); }
    /**
     * @param string|\Stringable|array<array-key, mixed>|Throwable $message
     * @param array<array-key, mixed> $context
     */
    public function debug($message, array $context = []): void     { $this->log(LogLevel::DEBUG, $message, $context); }

    /**
     * Core logger
     *
     * @param mixed $level a PSR-3 level name
     * @param string|\Stringable|array<array-key, mixed>|Throwable $message
     * @param array<array-key, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        if (!is_string($level)) {
            throw new \Psr\Log\InvalidArgumentException('Log level must be a string');
        }
        if (!in_array(strtolower($level), self::LEVELS, true)) {
            throw new \Psr\Log\InvalidArgumentException('Unknown log level: ' . $level);
        }

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

        $message = (string) $message;

        $context = self::redactContext($context);
        $message = self::scrubString($message);

        if ($this->monolog) {
            // ✅ Monolog backend
            // Monolog\Level is only touched here, so the file fallback works without Monolog
            $monologLevel = match (strtolower($level)) {
                LogLevel::DEBUG     => Level::Debug,
                LogLevel::INFO      => Level::Info,
                LogLevel::NOTICE    => Level::Notice,
                LogLevel::WARNING   => Level::Warning,
                LogLevel::ERROR     => Level::Error,
                LogLevel::CRITICAL  => Level::Critical,
                LogLevel::ALERT     => Level::Alert,
                default             => Level::Emergency,
            };
            $this->monolog->log($monologLevel, $message, $context);
            return;
        }

        // ❌ fallback → ملفات
        try {
            $logFile = is_string($context['logFile'] ?? null) ? $context['logFile'] : 'app';
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
     *
     * @param null|callable(array<array-key, mixed>): array<array-key, mixed> $redactor
     */
    public static function setRedactor(?callable $redactor): void
    {
        self::$redactor = $redactor;
    }

    /**
     * Replace the list of sensitive context keys (case-insensitive substring match).
     *
     * @param list<string> $keys
     */
    public static function setSensitiveKeys(array $keys): void
    {
        self::$sensitiveKeys = array_map(static fn(string $k): string => strtolower($k), $keys);
    }

    /**
     * @param array<array-key, mixed> $context
     * @return array<array-key, mixed>
     */
    public static function redactContext(array $context): array
    {
        try {
            $context = self::maskKeys($context);
            if (self::$redactor !== null) {
                // The hook's output is re-scanned so it cannot re-introduce sensitive data.
                $context = self::maskKeys((self::$redactor)($context));
            }
            return $context;
        } catch (Throwable) {
            return ['redaction' => 'failed'];
        }
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private static function maskKeys(array $data, int $depth = 0): array
    {
        if ($depth >= self::MAX_REDACTION_DEPTH) {
            return ['truncated' => 'max depth reached'];
        }

        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $out[$key] = self::REDACTED;
            } else {
                $out[$key] = self::maskValue($value, $depth + 1);
            }
        }
        return $out;
    }

    private static function maskValue(mixed $value, int $depth): mixed
    {
        if (is_array($value)) {
            return self::maskKeys($value, $depth);
        }
        if (is_string($value)) {
            return self::scrubString($value);
        }
        if ($value instanceof Throwable) {
            return self::describeThrowable($value, $depth);
        }
        if (is_object($value)) {
            return self::describeObject($value, $depth);
        }
        return $value;
    }

    /**
     * Objects are expanded into the data a formatter would serialize (JsonSerializable output, __toString,
     * public properties) and masked, so nothing sensitive reaches a backend through an object.
     * Value objects without secrets (dates, enums) are kept as they are.
     */
    private static function describeObject(object $value, int $depth): mixed
    {
        if ($value instanceof \DateTimeInterface || $value instanceof \UnitEnum) {
            return $value;
        }
        if ($depth >= self::MAX_REDACTION_DEPTH) {
            return ['truncated' => 'max depth reached'];
        }

        try {
            if ($value instanceof \JsonSerializable) {
                return self::maskValue($value->jsonSerialize(), $depth + 1);
            }
            if ($value instanceof \Stringable) {
                return self::scrubString((string) $value);
            }
            return [
                'class'      => $value::class,
                'properties' => self::maskKeys(get_object_vars($value), $depth + 1),
            ];
        } catch (Throwable) {
            return ['class' => $value::class, 'unserializable' => true];
        }
    }

    /**
     * Throwables are converted to a scrubbed array so their raw message never reaches the formatter.
     */
    /** @return array<string, mixed> */
    private static function describeThrowable(Throwable $e, int $depth): array
    {
        $info = [
            'class'   => $e::class,
            'message' => self::scrubString($e->getMessage()),
            'code'    => $e->getCode(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => self::scrubString($e->getTraceAsString()),
        ];
        if ($e->getPrevious() !== null) {
            $info['previous'] = $depth >= self::MAX_REDACTION_DEPTH
                ? ['truncated' => 'max depth reached']
                : self::describeThrowable($e->getPrevious(), $depth + 1);
        }
        return $info;
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
        // Authorization schemes: "Bearer <token>" / "Basic <credentials>"
        $text = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i', '$1 ' . self::REDACTED, $text) ?? $text;

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

        // Absolute URL ("scheme://user:pw@host/path") or network-path reference ("//host/path"):
        // drop scheme, userinfo, host and port, keep only the path.
        if (preg_match('#^(?:[A-Za-z][A-Za-z0-9+.-]*:)?//#', $path, $m) === 1) {
            $rest = substr($path, strlen($m[0]));
            if ($rest === '' || $rest[0] === '/') {
                return ''; // empty authority: not a valid URL, refuse rather than guess what is userinfo
            }
            $slash = strpos($rest, '/');
            $path = $slash === false ? '/' : substr($rest, $slash);
        }

        // Only origin-form targets ("/path") are logged; "*", "host:port" and the like yield ''
        return str_starts_with($path, '/') ? $path : '';
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
        $this->createFolder($dir);
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
