<?php

namespace common;

/**
 * 时间点记录类，用于记录每一个时间点所消耗的时间和内存。
 *
 * 用法：
 *
 * 初始化：
 * $timeLog = new TimeLog();
 * $timeLog
 * ->setLogType(TimeLog::LOG_TYPE_ECHO | TimeLog::LOG_TYPE_FILE)
 * ->setLogFilePath('/logs/time_log/log.txt');
 *
 * 生成记录点：
 * $timeLog->setTimeLog('记录点1');
 *
 * 获取记录信息：
 * $str = $timeLog->report();
 *
 * 根据指定的记录类型，输出记录
 * $timeLog->logReport();
 */
class TimeLog
{
    const LOG_TYPE_NONE = 0;
    const LOG_TYPE_ECHO = 1;
    const LOG_TYPE_FILE = 2;

    private $timeLogs = [];
    private $autoStart = false;
    private $logType = self::LOG_TYPE_NONE;
    private $logFilePath = '';

    /**
     * TimeLog constructor.
     * @param bool $autoStart 是否是默认开始
     * @param int $logType 记录类型，默认为空
     */
    public function __construct(bool $autoStart = true, int $logType = self::LOG_TYPE_NONE)
    {
        $this->autoStart = $autoStart;
        $this->logType = $logType;
        $this->logFilePath = $_SERVER['DOCUMENT_ROOT'] . '/logs/time_log/log.txt';

        if ($this->autoStart) {
            $this->start();
        }
    }

    /**
     * 开始记录，作为第一个记录点。
     * autostart为true的话，会自动调用。
     */
    public function start()
    {
        // 开始时记录初始时间
        $this->timeLogs = [];
        $this->setTimeLog('初始');
    }

    /**
     * 设置时间记录点
     * @param string $msg
     * @return $this
     */
    public function setTimeLog(string $msg)
    {
        $this->timeLogs[] = [
            'time' => microtime(true) * 1000,
            'msg' => $msg,
            'memory' => memory_get_usage(),
        ];
        return $this;
    }

    /**
     * 设置记录类型
     * @param int $type
     * @return $this
     */
    public function setLogType(int $type)
    {
        $this->logType = $type;
        return $this;
    }

    /**
     * 设置记录保存的文件路径
     * @param string $path
     * @return $this
     */
    public function setLogFilePath(string $path)
    {
        $this->logFilePath = $path;
        return $this;
    }

    /**
     * 获取记录信息
     * @return string
     */
    public function report()
    {
        $str = '';
        $lastItem = $this->timeLogs[0];
        foreach ($this->timeLogs as $v) {
            $costTime = number_format($v['time'] - $lastItem['time'], 0, '.', ',');
            $memoryUsed = $this->formatMemory($v['memory']);
            $memoryChanged = $this->formatMemory($v['memory'] - $lastItem['memory']);
            $str .= "{$v['msg']}，耗时：{$costTime}ms，占用内存：{$memoryUsed}，内存变化：{$memoryChanged}." . PHP_EOL;
            $lastItem = $v;
        }
        $totalCostTime = $lastItem['time'] - ($this->timeLogs[0]['time']);
        $totalCostTime = number_format($totalCostTime, 0, '.', ',');
        $str .= "总耗时：{$totalCostTime}ms." . PHP_EOL;;
        return $str;
    }

    /**
     * 根据指定的记录类型，输出记录
     * @return string
     */
    public function logReport()
    {
        $report = $this->report();

        if (($this->logType & self::LOG_TYPE_ECHO) !== 0) {
            echo $report;
        }

        if (($this->logType & self::LOG_TYPE_FILE) !== 0) {
            $dirPath = dirname($this->logFilePath);
            if (!file_exists($dirPath)) {
                mkdir($dirPath, 0777, true);
            }

            $file = fopen($this->logFilePath, 'a+');
            fwrite($file, $report . PHP_EOL);
            fclose($file);
        }

        return $report;
    }

    /**
     * 格式化内存值
     * @param int $memoryUsed
     * @return string
     */
    private function formatMemory(int $memoryUsed)
    {
        $symbol = $memoryUsed < 0 ? '-' : '';
        $memoryUsed = abs($memoryUsed);
        if (($result = round($memoryUsed / 1024 / 1024 / 1024, 2)) > 1) {
            return $symbol . $result . 'GB';
        } elseif (($result = round($memoryUsed / 1024 / 1024, 2)) > 1) {
            return $symbol . $result . 'MB';
        } elseif (($result = round($memoryUsed / 1024, 2)) > 1) {
            return $symbol . $result . 'KB';
        } else {
            $result = round($memoryUsed, 2);
            return $symbol . $result . 'B';
        }
    }
}