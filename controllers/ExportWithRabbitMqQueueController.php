<?php

namespace app\controllers;

use models\ClientRabbitMqQueueExporter;
use yii\web\Controller;

class ExportWithRabbitMqQueueController extends Controller
{
    /**
     * 创建导出任务
     * @throws \Exception
     */
    public function actionCreateTask()
    {
        $condition = [];
        $exporter = new ClientRabbitMqQueueExporter();
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
        $exporter = new ClientRabbitMqQueueExporter();
        $url = $exporter->setType('csv')->checkAndGetExportFile($condition);
        if (is_null($url)) {
            echo 'no url cache.';
        } else {
            echo "get from cache, url: <a href='$url'>$url</a>" . PHP_EOL;
        }

        exit();
    }
}