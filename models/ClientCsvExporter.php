<?php

namespace models;

use common\ExcelApi;
use common\RedisHelper;
use common\RedisLock\RedisLock;
use common\TimeLog;

/**
 * 根据条件导出客户列表
 *
 * 实现以下内容：
 * 100秒内重新访问读取同一个文件
 * 分段读取数据
 * 导出到csv文件
 * 生成文件时加锁防并发重复生成（获取锁失败，直接返回，不做重试）
 */
class ClientCsvExporter
{
    private $timeLog = null;

    /** @var bool url来源标识，执行导出后，导出文件的url是否从缓存获取，调试用 */
    public $getUrlFromCache = false;

    public function __construct()
    {
        $this->timeLog = new TimeLog();
        $this->timeLog
            ->setLogType(TimeLog::LOG_TYPE_ECHO | TimeLog::LOG_TYPE_FILE)
            ->setLogFilePath(\Yii::$app->getRuntimePath() . '/logs/time_log/log.txt');
    }

    /**
     * 导出到csv文件
     * @param array $where
     * @param string $host
     * @return string
     */
    public function export(array $where, string $host = '')
    {
        // 设置程序超时时间
        set_time_limit(300);

        // 从缓存读取文件路径
        $redisInstance = RedisHelper::getRedisClient();
        $cacheKey = $this->getUrlCacheKey($where);
        $url = $redisInstance->get($cacheKey);
        if (!is_null($url)) {
            $this->getUrlFromCache = true;
            return $url;
        }

        // 查询条件
        $pageSize = 5000;
        $params = [
            'page' => 1,
            'limit' => $pageSize,
            'select' => '`id`,`company`,`short_name`',
            'where' => $where,
            'order' => 'id',
        ];

        // 表头
        $selectList = explode(',', $params['select']);
        $fieldList = [];
        foreach ($selectList as $v) {
            $field = trim($v, '`');
            $fieldList[$field] = $field;
        }

        // 导出的文件名
        $fileName = 'client_' . date('YmdHis') . '.csv';
        // 导出的文件的相对路径
        $fileRelativePath = 'files/' . $fileName;

        // 查询总数
        $count = Client::listCount($params);

        // 分段导出
        $excelApi = new ExcelApi($fileRelativePath, $fieldList, [], 0, true);
        $lastId = 0;
        array_unshift($params['where'], []);
        for ($i = 0; $i < $count; $i += $pageSize) {
            // 查询数据
            $params['where'][0] = ['>', 'id', $lastId];
            $list = Client::listPage($params);
            $lastId = end($list['list'])['id'];

            // 导出数据
            $excelApi->setData($list['list']);
            $excelApi->appendToFile();

            unset($list);

            // 时间点记录
            $this->timeLog->setTimeLog("第" . ($i / $pageSize + 1) . "次查询");
        }

        // 记录信息
        $this->timeLog->setTimeLog('将数据导出到csv文件，完成.');
        $this->timeLog->logReport();

        // 文件访问路径
        if (empty($host)) {
            $host = $_SERVER['HTTP_HOST'];
        }
        $url = 'http://' . $host . '/files/' . $fileName;

        // 缓存文件路径100秒
        $redisInstance->setex($cacheKey, 100, $url);

        // 设置url来源标识
        $this->getUrlFromCache = false;

        // 返回导出文件的url
        return $url;
    }

    /**
     * 导出到csv文件（加锁）
     * @param array $where
     * @return bool|mixed
     * @throws \common\RedisLock\GetLockException
     * @throws \common\RedisLock\UnlockException
     */
    public function exportWithLock(array $where)
    {
        $callback = function () use ($where) {
            return $this->export($where);
        };
        $lock = new RedisLock();
        $lock->setDebug(true);
        return $lock->doWithLock("export:csv", $callback);
    }

    private function getUrlCacheKey(array $where)
    {
        return 'export:csv:url:' . md5(json_encode($where));
    }
}