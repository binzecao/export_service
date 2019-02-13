<?php

namespace app\controllers;

use yii\web\Controller;
use models\ClientRedisQueueExporter;

class ExportWithRedisQueueController extends Controller
{
    /**
     * 创建导出任务
     * @throws \Exception
     */
    public function actionCreateTask()
    {
        $condition = [];
        $exporter = new ClientRedisQueueExporter();
        $url = $exporter->setType('csv')->createTask($condition);
        if (empty($url)) {
            echo "创建导出任务成功.";
        } else {
            echo "get from cache, url: <a href='$url'>$url</a>" . PHP_EOL;
        }

        exit();
    }

    /**
     * 检查并且获取导出文件的url
     */
    public function actionCheckAndGetExportFileUrl()
    {
        $condition = [];
        $exporter = new ClientRedisQueueExporter();
        $url = $exporter->setType('csv')->checkAndGetExportFile($condition);
        if (is_null($url)) {
            echo 'no url cache.';
        } else {
            echo "get from cache, url: <a href='$url'>$url</a>" . PHP_EOL;
        }

        exit();
    }

    /**
     * 处理导出队列中单个任务
     * @throws \Exception
     */
    public function actionHandleSingleTask()
    {
        $exporter = new ClientRedisQueueExporter();
        $exporter->setType('csv')->handleSingleTask();
        echo '处理任务完成.';

        exit();
    }
}