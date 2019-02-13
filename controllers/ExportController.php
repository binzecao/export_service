<?php

namespace app\controllers;

use yii\web\Controller;

class ExportController extends Controller
{
    // /index.php?r=export
    public function actionIndex()
    {
        $str = <<<EOF
            <p>
                <button onclick="export1('/?r=export-csv/export')">export csv</button>
                <button onclick="export1('/?r=export-csv/export-with-lock')">export csv with lock</button>
                <button onclick="export1('/?r=export-excel/export')">export excel</button>
                <button onclick="export1('/?r=export-excel/export-with-lock')">export excel with lock</button>
            </p>
            <p>
                <button onclick="export1('/?r=export-with-redis-queue/create-task')">export csv use redis queue</button>
                <button onclick="export1('/?r=export-with-redis-queue/check-and-get-export-file-url')">check and get export file url</button>
                <button onclick="export1('/?r=export-with-redis-queue/handle-single-task')">handle redis queue task</button>
            </p>
            <p>
                <button onclick="export1('/?r=export-with-rabbit-mq-queue/create-task')">export csv use rabbitmq queue</button>
                <button onclick="export1('/?r=export-with-rabbit-mq-queue/check-and-get-export-file-url')">check and get export file url</button>
            </p>
            <pre id="result"></pre>
            <script>
            function export1(url){
                url = url + (url.indexOf('?') > -1 ? '&' : '?') + 't=' + (new Date()).getTime();
                document.getElementById('result').innerHTML = '';
                fetch(url).then(function(response) {
                    if (response.status !== 200) {
                        alert('发生异常：状态码为：' + response.status);
                    }
                    response.text().then(function(data){
                        document.getElementById('result').innerHTML = data;
                    });
                });
            }
            </script>
EOF;
        echo $str;
    }
}