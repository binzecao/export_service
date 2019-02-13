<?php

namespace common;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class AMQPHelper
{
    /**
     * 获取RabbitMq客户端实例
     * @return AMQPStreamConnection
     * @throws \Exception
     */
    public static function getAMQPConnection()
    {
        $config = \Yii::$app->params['rabbitmq'];
        $connection = new AMQPStreamConnection($config['host'], $config['port'], $config['user'], $config['password']);
        if (empty($connection)) {
            throw new \Exception('cannot connect to the amqp broker');
        }
        return $connection;
    }
}