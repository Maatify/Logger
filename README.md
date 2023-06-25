# Logger

maatify.dev logger, known by our team

## Installation

    composer require maatify/logger
    

#### Usage Example:

    use Maatify\Logger\Logger;
    
    //$log = 'test';
    $log = ['name' => 'test', 'description' => 'Logger test'];
    
    $log_file = 'test.log';

    Logger::RecordLog(string|array $log, string $file_name);


make sure there is logs folder under project main folder
