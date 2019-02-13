<?php

namespace models;

use common\AMQPHelper;
use common\Log;
use PhpAmqpLib\Message\AMQPMessage;
use common\RedisHelper;

class ClientRabbitMqQueueExporter
{
    private $type = 'csv'; // csv、excel

    private $logPath = '/logs/client_rabbitmq_queue.log';

    public function setType(string $type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * 创建导出任务
     * @param array $where
     * @return string
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
        $exchangeName = 'export';
        $routingKey = 'export-csv';
        $connection = AMQPHelper::getAMQPConnection();
        $channel = $connection->channel();
        $channel->exchange_declare($exchangeName, 'direct', false, false, false);
        $msg = new AMQPMessage(json_encode($data));
        // $msg = new AMQPMessage(json_encode($data), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $channel->basic_publish($msg, $exchangeName, $routingKey);

        return '';
    }

    /**
     * 检查并且获取导出文件的url
     * @param array $where
     * @return string|null
     */
    public function checkAndGetExportFile(array $where)
    {
        return $this->getFileUrlFromCache($where);
    }

    /**
     * 处理导出任务（离线程序使用），
     * 循环阻塞处理
     * @throws \Exception
     */
    public function handleTasks()
    {
        set_time_limit(0);

        $exchangeName = 'export';
        $queueName = 'export-queue';
        $routingKey = 'export-csv';
        $connection = AMQPHelper::getAMQPConnection();
        $channel = $connection->channel();
        $channel->exchange_declare($exchangeName, 'direct', false, false, false);

        // $queueArgs = ['x-max-length' => ['l', 1], "overflow" => ['S', "reject-publish"]];
        $queueArgs = [];
        $channel->queue_declare($queueName, false, false, false, false, false, $queueArgs);
        $channel->queue_bind($queueName, $exchangeName, $routingKey);
        echo "start listen queue, name:" . $queueName . PHP_EOL;

        // 消息任务处理回调
        $callback = function ($msg) {
            // 处理消息任务
            $data = json_decode($msg->body, true);
            if (empty($data)) {
                $this->writeLog('处理任务异常，任务消息：' . $msg->body);
            } else {
                $this->export($data['exportType'], $data['where'], $data['host']);
                $this->writeLog('导出任务执行成功, 任务消息：' . json_encode($data['where']));
            }
            // 因为开启了ack确认，所以这里要发送ack确认，不然一直会被当作未完成。
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        };
        // 保证一个队列消费者只同时处理一个任务
        $channel->basic_qos(null, 1, null);
        $channel->basic_consume($queueName, '', false, false, false, false, $callback);

        while (count($channel->callbacks)) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
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