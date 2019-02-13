<?php

namespace app\controllers;

use yii\web\Controller;
use models\ClientCsvExporter;

class ExportCsvController extends Controller
{
    /**
     * 导出Csv文件
     */
    public function actionExport()
    {
        // 执行导出
        $condition = [];
        $exporter = new ClientCsvExporter();
        $url = $exporter->export($condition);

        // 输出信息
        if ($exporter->getUrlFromCache) {
            echo "get from cache, url: <a href='$url'>$url</a>" . PHP_EOL;
        } else {
            echo "create file success, url: <a href='$url'>$url</a>" . PHP_EOL;
        }

        exit();
    }

    /**
     * 导出Csv文件（加锁）
     */
    public function actionExportWithLock()
    {
        try {
            // 执行导出
            $condition = [];
            $exporter = new ClientCsvExporter();
            $url = $exporter->exportWithLock($condition);

            // 输出信息
            if ($exporter->getUrlFromCache) {
                echo "get from cache, url: <a href='$url'>$url</a>" . PHP_EOL;
            } else {
                echo "create file success, url: <a href='$url'>$url</a>" . PHP_EOL;
            }
        } catch (\common\RedisLock\GetLockException $ex) {
            echo '获取锁失败:' . $ex->getMessage();
        } catch (\common\RedisLock\UnlockException $ex) {
            echo '释放锁失败' . $ex->getMessage();
        }

        exit();
    }

}