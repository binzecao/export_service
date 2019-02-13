<?php

namespace common;

use Yii;

class ExcelApi
{
    protected $filename;
    protected $fields;
    protected $data;
    protected $sheet;
    protected $isHaveTime = false;

    protected $special_type;

    protected $replace;
    protected $error = ''; //错误信息
    public $maxsize = 0;

    public function __construct($filename = '', $fields = [], $data = [], $sheet = 0, $isHaveTime = false)
    {
        $this->filename = $filename;
        $this->fields = $fields;
        $this->data = $data;
        $this->sheet = $sheet;
        $this->isHaveTime = $isHaveTime;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function setTitle($params)
    {
        foreach ($params as $k => $v) {
            $this->replace[$k] = $v;
        }

        return $this;
    }

    public function setSpecialFields($params)
    {
        foreach ($params as $k => $v) {
            $this->special_type[$k] = $v;
        }

        return $this;
    }

    /**
     * 转字符编码
     * @param mixed $data 输入字符数据
     * @return bool
     */
    private function _charset($data)
    {
        if (!$data) {
            return false;
        }
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->_charset($v);
            }
            return $data;
        }
        return iconv('UTF-8', 'GBK', $data);
    }

    public function setReplace($params)
    {
        foreach ($params as $k => $v) {
            $this->replace[$k] = $v;
        }

        return $this;
    }

    public function export()
    {
        $data = $this->process();

        header("Content-type:text/csv");
        header("Content-Disposition:attachment;filename=" . $this->filename);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo $data;
        exit;
    }

    public function appendToFile()
    {
        $path = Yii::$app->basePath . '/web' . (strpos($this->filename, '/') === 0 ? '' : '/') . $this->filename;
        $dir = dirname($path);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $containFieldRow = (!file_exists($path) || filesize($path) == 0);
        $data = $this->process($containFieldRow);
        $file = fopen($path, 'a');
        fwrite($file, $data);
        fclose($file);
    }

    protected function process($containFieldRow = true)
    {
        $string = '';

        if ($containFieldRow) {
            foreach (array_values($this->fields) as $field) {
                $string .= "," . iconv('UTF-8', 'GBK', $field);
            }
            $string = substr_replace($string, "", 0, 1) . "\n";
        }

        $fields = array_keys($this->fields);

        foreach ($this->data as $d) {
            $str = '';
            foreach ($fields as $field) {
                if (isset($this->special_type[$field]) && $this->special_type[$field] == 'time') {
                    $str .= "," . date('Y-m-d', $d[$field]);
                } elseif (isset($this->special_type[$field]) && $this->special_type[$field] == 'dateTime') {
                    $str .= "," . date('Y-m-d H:i:s', $d[$field]);
                } else {
                    if (isset($this->replace[$field]) && isset($this->replace[$field][$d[$field]])) {
                        $str .= "," . iconv('UTF-8', 'GBK//IGNORE', $this->replace[$field][$d[$field]]);
                    } else {
                        $str .= "," . iconv('UTF-8', 'GBK//IGNORE', $d[$field]);
                    }
                }
            }
            $string .= substr_replace($str, "", 0, 1) . "\n";
        }

        return $string;
    }

    public function excelTime($days, $time = false)
    {
        if (is_numeric($days)) {
            //based on 1900-1-1
            $jd = GregorianToJD(1, 1, 1970);
            $gregorian = JDToGregorian($jd + intval($days) - 25569);
            $myDate = explode('/', $gregorian);
            $myDateStr = str_pad($myDate[2], 4, '0', STR_PAD_LEFT)
                . "-" . str_pad($myDate[0], 2, '0', STR_PAD_LEFT)
                . "-" . str_pad($myDate[1], 2, '0', STR_PAD_LEFT)
                . ($time ? " 00:00:00" : '');
            return $myDateStr;
        }
        return $days;
    }

    public function getError()
    {
        return $this->error;
    }
}