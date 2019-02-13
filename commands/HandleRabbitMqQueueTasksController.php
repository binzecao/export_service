<?php

namespace app\commands;

use models\ClientRabbitMqQueueExporter;
use yii\console\Controller;

class HandleRabbitMqQueueTasksController extends Controller
{
    /**
     * 处理导出任务
     *
     * 命令：
     * php yii handle-rabbit-mq-queue-tasks/index
     *
     * @throws \Exception
     */
    public function actionIndex()
    {
        $exporter = new ClientRabbitMqQueueExporter();
        $exporter->setType('csv')->handleTasks();
    }
}