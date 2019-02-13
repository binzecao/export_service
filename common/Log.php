<?php

namespace common;

class Log
{
    private $logFilePath = null;

    public function setLogFilePath(string $path)
    {
        $this->logFilePath = $path;
        return $this;
    }

    public function writeLog($content)
    {
        $dirPath = dirname($this->logFilePath);
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0777, true);
        }

        $file = fopen($this->logFilePath, 'a+');
        fwrite($file, $content . PHP_EOL);
        fclose($file);
    }
}