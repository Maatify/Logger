[![Current version](https://img.shields.io/packagist/v/maatify/logger)](https://packagist.org/packages/maatify/logger)
[![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/maatify/logger)](https://packagist.org/packages/maatify/logger)
[![Monthly Downloads](https://img.shields.io/packagist/dm/maatify/logger)](https://packagist.org/packages/maatify/logger/stats)
[![Total Downloads](https://img.shields.io/packagist/dt/maatify/logger)](https://packagist.org/packages/maatify/logger/stats)

# Logger

maatify.dev logger, known by our team
# Installation

```shell
composer require maatify/paystack
```

# Usage

#### Example :

```PHP
    use Maatify\Logger\Logger;
    
    //$log = 'test';
    $log = ['name' => 'test', 'description' => 'Logger test'];
    
    $log_file = 'test';

    Logger::RecordLog(string|array $log, string $file_name, string log_file_extinsion);
```

make sure there is logs folder under project main folder