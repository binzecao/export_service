<?php

namespace common;

/**
 * Redis辅助类
 */
class RedisHelper
{
    /**
     * 获取redis客户端实例
     * @return \Predis\Client
     */
    public static function getRedisClient()
    {
        $config = \Yii::$app->params['redis'];
        return new \Predis\Client($config);
    }
}