<?php

/**
 * @copyright   ©2023 Maatify.dev
 * @Liberary    Logger
 * @Project     Logger
 * @author      Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @since       2023-03-20 5:49 PM
 * @see         https://www.maatify.dev Maatify.com
 * @link        https://github.com/Maatify/Logger  view project on GitHub
 * @copyright   ©2023 Maatify.dev
 * @note        This Project using for Log into file.
 *
 * @note        This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 *
 */


namespace Maatify\Logger;

use Maatify\Store\File\Path;

class Logger
{
    private static string $extension = 'log';
    public static function RecordLog($message, string $logFile = 'admin_logs', string $extension = ''): void
    {
        if(!empty($extension)){
            self::$extension = $extension;
        }
        if (is_array($message)) {
            $message = json_encode(
                $message,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );
        }
        $target_dir = self::creatFolderByDate();
        if ($logFile) {
            $folders = explode("/", $logFile);
            if (sizeof($folders) > 1) {
                for ($i = 0; $i < sizeof($folders) - 1; $i++) {
                    $target_dir .= "/" . $folders[$i];
                    self::creatFolder($target_dir);
                }
                $logFile = $folders[sizeof($folders) - 1];
            }
        }

        $f = @fopen(
            $target_dir . '/' . $logFile . '_' . date("Y-m-d-A", time()) . '.' . self::$extension,
            'a+'
        );
        if ($f) {
            @fputs(
                $f,
                date("Y-m-d H:i:s") . "  "
                . $_SERVER['REMOTE_ADDR']
                . (! empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? "  "
                                                               . $_SERVER['HTTP_X_FORWARDED_FOR'] : '') . "  "
                . $_SERVER['HTTP_HOST'] . "  " . ($_SERVER["REQUEST_URI"] ?? '')
                . "  "
                . debug_backtrace()[0]['line']
                . "  "
                . $message
                . PHP_EOL
            );
            @fclose($f);
        }
    }

    private static function creatFolderByDate(): string
    {
        if(file_exists('logs')){
            $target_dir = 'logs/' . date('y');
        }else{
            $path = (new Path())->Get() . '/logs';
            if(!file_exists($path)){
                self::creatFolder($path);
            }
            $target_dir = $path . '/' . date('y');
        }
        self::creatFolder($target_dir);
        $target_dir = $target_dir . '/' . date('m');
        self::creatFolder($target_dir);
        $target_dir = $target_dir . '/' . date('d');
        self::creatFolder($target_dir);

        return $target_dir;
    }

    private static function CreatFolder($target_dir): void
    {
        if (! file_exists($target_dir)) {
            mkdir($target_dir);
            $f = @fopen($target_dir . '/index.php', 'a+');
            if ($f) {
                @fputs(
                    $f,
                    '<?php' . PHP_EOL
                    . 'header("Location: https://" . $_SERVER[\'HTTP_HOST\'] . "/404.php");'
                    . PHP_EOL
                );
                @fclose($f);
            }
        }
    }

    public static function ReadFile(string $action): string
    {
        if(file_exists('logs')){
            $target_dir = 'logs/' . date('y');
        }else{
            $target_dir = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']) . '/../logs/' . date('y');
        }
        $target_dir = $target_dir . '/' . date('m');

        return $target_dir . '/' . date('d') . '/post/' . $action . '_response_' . date("Y-m-d-A", time()) . '.' . self::$extension;
    }
}