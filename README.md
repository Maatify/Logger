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