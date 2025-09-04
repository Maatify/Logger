[![Current version](https://img.shields.io/packagist/v/maatify/logger)](https://packagist.org/packages/maatify/logger)
[![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/maatify/logger)](https://packagist.org/packages/maatify/logger)
[![Monthly Downloads](https://img.shields.io/packagist/dm/maatify/logger)](https://packagist.org/packages/maatify/logger/stats)
[![Total Downloads](https://img.shields.io/packagist/dt/maatify/logger)](https://packagist.org/packages/maatify/logger/stats)

# 📜 Maatify Logger

A lightweight PSR-3 compatible Logger with **Monolog integration** (if available) and **file fallback** (date/hour folders).  
Supports **legacy logging**, **modern PSR-3 methods**, and a **facade service** for easy usage.

---

## ✨ Features

- ✅ **PSR-3 compliant** (`LoggerInterface`, `LogLevel`).  
- ✅ **Monolog integration** (if installed) – automatic backend.  
- ✅ **File fallback** if Monolog is not available (organized by `year/month/day/hour`).  
- ✅ **Structured JSON logs** (server info + message + context).  
- ✅ **Facade service** (`LoggerService`) with static methods for quick usage.  
- ✅ **Exception logging** via `logException()`.  
- ✅ **Legacy support** (`RecordLog`) for old codebases.  

---

## 📦 Installation

```bash
composer require maatify/logger
````

If you also want Monolog support:

```bash
composer require monolog/monolog
```

---

## 🔧 Usage

### 1. Using the Facade (`LoggerService`)

```php
use Maatify\Logger\LoggerService;

// Info log
LoggerService::info("User login successful", "auth/logs");

// Error log with Exception
try {
    throw new RuntimeException("DB connection failed");
} catch (Throwable $e) {
    LoggerService::logException($e, "system/errors");
}

// Debug log with context
LoggerService::debug("Payment request", "payments/debug", [
    'userId' => 123,
    'amount' => 99.99,
]);
```

---

### 2. Generic Logger Method

```php
use Maatify\Logger\LoggerService;
use Psr\Log\LogLevel;

LoggerService::log(LogLevel::CRITICAL, "Out of memory!", "system/critical");
```

---

### 3. Exception Logging with Extra Context

```php
try {
    throw new RuntimeException("Unauthorized access");
} catch (Throwable $e) {
    LoggerService::logException(
        $e,
        "security/errors",
        LogLevel::CRITICAL,
        [
            'userId'    => 42,
            'requestId' => 'abc123',
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]
    );
}
```

---

### 4. Legacy Support

For old codebases still using `RecordLog`:

```php
use Maatify\Logger\Logger;

Logger::RecordLog("Old style log", Logger::LEVEL_INFO, "legacy/system");
```

---

## 📂 Log Storage Structure

When using fallback file logging (without Monolog), logs are stored under:

```
logs/YYYY/MM/DD/HH/<logFile>_YYYY-MM-DD-HH.log
```

Example:

```
logs/2025/09/04/16/system_errors_2025-09-04-16.log
```

Each log entry is JSON formatted:

```json
{
    "level": "ERROR",
    "time": "2025-09-04 16:15:00",
    "server": {
        "REMOTE_ADDR": "127.0.0.1",
        "HTTP_HOST": "example.com",
        "REQUEST_URI": "/login",
        "USER_AGENT": "Mozilla/5.0"
    },
    "message": "Exception captured",
    "context": {
        "userId": 42,
        "exception": {
            "message": "Unauthorized access",
            "file": "/var/www/app/login.php",
            "line": 88,
            "code": 0,
            "trace": "stack trace..."
        }
    }
}
```

---

## ⚙️ Configuration

* Default log path: `./logs`
* Default file extension: `.log`
* Default fallback log level: `info`
* Uses Monolog automatically if installed.

---

## 🛡️ License

This project is proprietary ©2025 [Maatify.dev](https://maatify.dev)

---

## ✍️ Author

**Mohamed Abdulalim**
[maatify.dev](https://maatify.dev) – [GitHub](https://github.com/Maatify)


