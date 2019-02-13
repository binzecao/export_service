<?php

namespace common\RedisLock;

use common\RedisHelper;

/**
 * Redis分布式锁
 *
 * 用法：
 * $lock = new RedisLock();
 * $result = $lock->doWithLock("export_csv", $callback);
 *
 * 释放锁时有个地方注意一下，如果业务代码执行时间超过锁的超时时间，会抛出异常，
 * 这时要处理这个异常的话，可以：
 * 1.增大锁超时时间
 * 2.捕获抛出一个自定义异常，让调用者自行捕获
 */
class RedisLock
{
    private $redisInstance = null; // redis实例
    private $debug = false; // 是否是调试模式，调试模式输出调试信息
    private $maxRetryTimes = 0; // 当资源被锁后，尝试重新获取锁的最大的次数，默认0
    private $retryInterval = 1; // 当资源被锁后，尝试重新获取锁的时间间隔，单位秒，默认1秒
    private $lockExpireTime = 20000; // 锁的超时时间（毫秒），默认20秒

    /**
     * 设置是否调试默认，调试模式输出调试信息
     * @param bool $val
     * @return $this
     */
    public function setDebug(bool $val)
    {
        $this->debug = $val;
        return $this;
    }

    /**
     * 设置当资源被锁后，尝试重新获取锁的最大的次数
     * @param int $val
     * @return $this
     */
    public function setMaxRetryTimes(int $val)
    {
        $this->maxRetryTimes = $val;
        return $this;
    }

    /**
     * 设置当资源被锁后，尝试重新获取锁的时间间隔，单位秒
     * @param int $val
     * @return $this
     */
    public function setRetryInterval(int $val)
    {
        $this->retryInterval = $val;
        return $this;
    }

    /**
     * 锁的超时时间，单位秒
     * @param int $val
     * @return $this
     */
    public function setLockExpireTime(int $val)
    {
        $this->lockExpireTime = $val;
        return $this;
    }

    /**
     * 加锁执行代码
     * @param string $lockKey 锁的键
     * @param \Closure $callback 要执行的代码
     * @return bool|mixed
     * @throws GetLockException
     * @throws UnlockException
     * @throws \Exception
     */
    public function doWithLock(string $lockKey, \Closure $callback)
    {
        $this->log('try to get lock.' . PHP_EOL);

        // 加锁，并且1秒重试，失败次数超过最大重试次数退出
        $lockKey = 'lock:' . $lockKey;
        $lockVal = rand(1000000, 9999999);
        $failedTimes = 0;
        while (!($result = $this->lock($lockKey, $lockVal, $this->lockExpireTime)) && $failedTimes < $this->maxRetryTimes) {
            $this->log('lock is existed, retrying...' . PHP_EOL);
            sleep($this->retryInterval);
            $failedTimes++;
        }
        if (!$result) {
            $failedTimes++;
            $this->log("fail to get lock, fail times: $failedTimes." . PHP_EOL);
            throw new GetLockException("get the lock '$lockKey' fail, fail times: $failedTimes.");
        }

        // 成功获取锁
        $this->log('get lock success.' . PHP_EOL);

        // 开始执行业务流程
        $this->log('start to handle callback...' . PHP_EOL);
        try {
            // 执行业务流程
            $result = $callback();
            $this->log('finish handling callback...' . PHP_EOL);
            return $result;
        } catch (\Exception $ex) {
            // 记录业务异常
            $this->log('occur error while handling callback...' . PHP_EOL);
            throw $ex;
        } finally {
            // 释放锁
            $result = $this->unlock($lockKey, $lockVal);
            $this->log('unlock result: ' . ($result ? 'success' : 'fail') . '.' . PHP_EOL);
            if (!$result) {
                throw new UnlockException("unlock '$lockKey' fail.");
            }
        }
    }

    /**
     * 加锁（添加一个Redis键值，作为锁）
     * @param string $lockKey 锁的键
     * @param string $lockVal 锁的值
     * @param int $expireTime 过期时间（毫秒）
     * @return bool
     */
    protected function lock(string $lockKey, string $lockVal, int $expireTime)
    {
        $redis = $this->getRedisClient();

        // SET key val NX PX 30000
        $result = $redis->set($lockKey, $lockVal, 'NX', 'PX', $expireTime);
        return $result == 'OK';
    }

    /**
     * 释放锁（删除Redis键值）
     * @param string $lockKey 锁的键
     * @param string $lockVal
     * @return bool
     */
    protected function unlock(string $lockKey, string $lockVal)
    {
        $redis = $this->getRedisClient();

        // eval "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end" 1 c1 c1
        $result = $redis->eval("if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end", 1, $lockKey, $lockVal);
        return $result == 1;
    }

    /**
     * 获取Redis客户端实例
     * @return null|\Predis\Client
     */
    private function getRedisClient()
    {
        if ($this->redisInstance == null) {
            $this->redisInstance = RedisHelper::getRedisClient();
        }
        return $this->redisInstance;
    }

    /**
     * 输出日志记录（echo），调试模式下用
     * @param string $str
     */
    private function log(string $str)
    {
        if ($this->debug) {
            echo $str;
        }
    }
}