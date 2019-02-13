<?php

namespace models;

use common\Log;
use common\RedisHelper;

/**
 * 使用Redis列表的方式导出文件
 *
 * 发起导出请求，后端判断缓存是否包含文件url，包含的话直接返回文件url
 * 如果缓存中没有文件url，那么将导出涉及到的数据序列化成json字符串，lpush入队。入队成功返回等待信息给前端，前端不断发起请求轮询另外的接口来获取结果。
 *
 * 另外创建一个服务，作为队列消费者，用while true和brpop来取队列的信息。然后根据获出队数据做生成导出文件。
 * 在这个生成导出文件的过程中，也要判断缓存中是否包含文件url，如果包含文件url，这个过程也不重新生成文件。直接跳过，继续去拿去一个队列元素。
 * 也应该要有相应的守护进程。
 *
 * 并且，前端后端要有两个接口：任务消息入队（/create_task?condition=）, 检查任务结果（/check_and_get_export_file_url?condition=）
 */
class ClientRedisQueueExporter
{
    private $type = 'csv'; // csv、excel

    private $logPath = '/logs/client_redis_queue.log';

    public function setType(string $type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * 创建导出任务
     * @param array $where
     * @return null|string
     * @throws \Exception
     */
    public function createTask(array $where)
    {
        // 从缓存读取文件路径
        $url = $this->getFileUrlFromCache($where);
        if (!is_null($url)) {
            return $url;
        }

        // 入队
        $data = [
            'exportType' => $this->type,
            'protocol' => 'http',
            'host' => $_SERVER['HTTP_HOST'],
            'where' => $where
        ];
        $redisInstance = RedisHelper::getRedisClient();
        $result = $redisInstance->lpush($this->getQueueCacheKey(), [json_encode($data)]);
        if ($result == 0) {
            $errMsg = '导出任务入队失败.';
            $this->writeLog($errMsg);
            throw new \Exception($errMsg);
        }

        // 记录
        $this->writeLog('导出任务入队成功.');

        // 信任无返回空字符串
        return '';
    }

    /**
     * 检查并且获取导出文件的url
     * @param array $where
     * @return null|string
     */
    public function checkAndGetExportFile(array $where)
    {
        return $this->getFileUrlFromCache($where);
    }

    /**
     * 消费导出队列中单个任务
     * @throws \Exception
     */
    public function handleSingleTask()
    {
        // rpop

        set_time_limit(300);

        try {
            $redisInstance = RedisHelper::getRedisClient();
            $taskDataStr = $redisInstance->rpop($this->getQueueCacheKey());
            if (empty($taskDataStr) || !is_string($taskDataStr)) {
                throw new \Exception('队列不存在或长度为0.');
            }
            $taskData = json_decode($taskDataStr, true);
            if (isset($taskData[1])) {
                throw new \Exception('导出任务参数有误.');
            }

            // 导出文件
            $this->export($taskData['exportType'], $taskData['where'], $taskData['host']);
            $this->writeLog('导出任务执行成功, 条件：' . json_encode($taskData['where']));
        } catch (\Exception $ex) {
            $errMsg = '导出失败：' . $ex->getMessage();
            $this->writeLog($errMsg);
            throw new \Exception($errMsg);
        }
    }

    /**
     * 处理导出任务（离线程序使用），
     * 循环阻塞处理
     */
    public function handleTasks()
    {
        // brpop

        set_time_limit(0);

        $queueCacheKey = $this->getQueueCacheKey();
        $redisInstance = RedisHelper::getRedisClient();
        while (true) {
            try {
                // 阻塞出队
                $queueData = $redisInstance->brpop([$queueCacheKey], 0);

                // key为空，或不是队列，或队列没有信息等判断
                if (empty($queueData) || !is_array($queueData) || count($queueData) != 2) {
                    continue;
                }

                // 获取任务信息
                $taskData = json_decode($queueData[1], true);

                // 导出文件
                $this->export($taskData['exportType'], $taskData['where'], $taskData['host']);
                $this->writeLog('导出任务执行成功, 条件：' . json_encode($taskData['where']));
            } catch (\Exception $ex) {
                $errMsg = '导出失败：' . $ex->getMessage() . PHP_EOL . $ex->getTraceAsString();
                $this->writeLog($errMsg);
            }

            // 0.2秒间隔
            usleep(200000);
        }
    }

    /**
     * 获取url缓存key
     * @param array $where
     * @return string
     */
    private function getUrlCacheKey(array $where)
    {
        return 'export:' . $this->type . ':url:' . md5(json_encode($where));
    }

    /**
     * 获取队列的key
     * @return string
     */
    private function getQueueCacheKey()
    {
        return 'queue:export:' . $this->type;
    }

    /**
     * 从缓存中获取导出文件的url值
     * @param array $where
     * @return string|null
     */
    private function getFileUrlFromCache(array $where)
    {
        // 从缓存读取文件路径
        $redisInstance = RedisHelper::getRedisClient();
        $cacheKey = $this->getUrlCacheKey($where);
        $url = $redisInstance->get($cacheKey);
        return $url;
    }

    /**
     * 导出文件
     * @param string $exportType
     * @param array $where
     * @param string $host
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     * @throws \Exception
     */
    private function export(string $exportType, array $where, string $host = '')
    {
        switch ($exportType) {
            case 'csv' :
                (new ClientCsvExporter())->export($where, $host);
                break;
            case 'excel' :
                (new ClientExcelExporter())->export($where, $host);
                break;
            default:
                throw new \Exception('任务信息异常, 条件：' . json_encode($where));
        }
    }

    /**
     * 日志记录
     * @param string $content
     */
    private function writeLog(string $content)
    {
        (new Log())
            ->setLogFilePath(\Yii::$app->getRuntimePath() . $this->logPath)
            ->writeLog($content);
    }

}