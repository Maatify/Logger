<?php
/**
 * Created by Maatify.dev
 * User: Maatify.dev
 * Date: 2023-03-21
 * Time: 9:08 AM
 */

namespace Maatify\Store\File;

class Path
{
    private string $path;
    public function __construct(string $path = '')
    {
        $this->path = $path ? : dirname(__DIR__);
    }

    public function Get(): string
    {
        return $this->filePaths();
    }

    private function filePaths(): string
    {
        return \rtrim($this->path, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR;
    }

}