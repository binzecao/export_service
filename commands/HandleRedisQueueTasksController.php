<?php

namespace app\commands;

use yii\console\Controller;
use models\ClientRedisQueueExporter;

class HandleRedisQueueTasksController extends Controller
{
    /**
     * 处理导出任务
     *
     * 命令：
     * php yii handle-redis-queue-tasks/index
     */
    public function actionIndex()
    {
        $exporter = new ClientRedisQueueExporter();
        $exporter->setType('csv')->handleTasks();
    }
}