<?php

namespace models;

use common\RedisHelper;
use common\RedisLock\RedisLock;
use common\TimeLog;

/**
 * 用PHPExcel导出到xlsx文件
 *
 * 实现以下内容：
 * 100秒内重新访问读取同一个文件
 * 分段读取数据
 * 导出到csv文件
 * 生成文件时加锁防并发重复生成（获取锁失败，直接返回，不做重试）
 *
 * linux 需要 安装zip扩展，apt-get install php7.0-zip 或 wget http://pecl.php.net/get/zip
 * php.ini 中 设置 extension=/usr/local/lib/php/extensions/zip.so
 * php.ini 中 设置 zlib.output_compression = On
 */
class ClientExcelExporter
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
     * 导出到excel文件
     * @param array $where
     * @param string $host
     * @return string
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
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
        $fieldList = explode(',', $params['select']);
        $fieldList = array_map(function ($v) {
            return trim($v, '``');
        }, $fieldList);

        // PHPExcel准备
        $phpExcel = new \PHPExcel();
        $phpExcel->getProperties()->setTitle("export list")->setDescription("none");
        $phpExcel->setActiveSheetIndex(0);
        $activeSheet = $phpExcel->getActiveSheet();
        $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
        $cacheSettings = array('cacheTime' => 300);
        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);

        // 查询总数
        $count = Client::listCount($params);
        $count = 20000; // for test
        $lastId = 0;
        $params['where'][] = [];

        // 设置excel表头
        $col = 0;
        foreach ($fieldList as $v) {
            $activeSheet->setCellValueExplicitByColumnAndRow($col++, 1, $v);
        }

        // 分段导出
        $row = 2;
        for ($i = 0; $i < $count; $i += $pageSize) {
            // 查询数据
            $params['where'][0] = ['>', 'id', $lastId];
            $list = Client::listPage($params);
            $lastId = end($list['list'])['id'];

            // 设置数据
            foreach ($list['list'] as $v) {
                $col = 0;
                foreach ($fieldList as $v2) {
                    $activeSheet->setCellValueByColumnAndRow($col++, $row, $v[$v2]);
                }
                $row++;
            }

            unset($list);

            // 记录
            $this->timeLog->setTimeLog("第" . ($i / $pageSize + 1) . "次查询");
        }

        // 记录
        $this->timeLog->setTimeLog('将数据保存到excel文件');

        // 将数据保存到excel文件
        $fileName = 'client_' . date('YmdHis') . '.xlxs';
        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/files/' . $fileName;
        $phpExcelWriter = \PHPExcel_IOFactory::createWriter($phpExcel, 'Excel2007');
        $phpExcelWriter->save($filePath);

        // 记录信息
        $this->timeLog->setTimeLog('将数据导出到excel文件，完成.');
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
     * 导出到excel文件（加锁）
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
        return $lock->doWithLock("export:excel", $callback);
    }

    private function getUrlCacheKey(array $where)
    {
        return 'export:excel:url:' . md5(json_encode($where));
    }
}