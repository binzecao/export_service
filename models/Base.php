<?php

namespace models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Base model
 *
 * @property integer $id
 */
class Base extends ActiveRecord
{
    // 最近错误信息
    protected static $error = '';

    /**
     * @param string $where
     * @return integer
     */
    public static function count($where = '')
    {
        $model = static::find();

        if ($where) {
            $model->where($where);
        }

        return $model->count();
    }

    /**
     * @param string $where
     * @return integer
     */
    public static function getSum($sumField, $where = '')
    {
        $model = static::find();

        if ($where) {
            $model->where($where);
        }

        return $model->sum($sumField);
    }

    /**
     * @param string $where
     * @param integer $page
     * @param integer $limit
     * @param string $order
     * @return array
     */
    public static function getAll($where = '', $order = '', $page = 1, $limit = 0)
    {
        $model = static::find();

        if ($where) {
            $model->where($where);
        }

        if ($limit) {
            $model->limit = $limit;
            $model->offset = ($page - 1) * $limit;
        }

        if ($order) {
            $model->orderBy($order);
        }

        return $model->all();
    }

    /**
     * @param string $where //条件
     * @param string $fields //查找的列
     * @param string $order //排序
     * @param boolean $asArray //是否数组
     * @return array
     */
    public static function getOne($where, $order = false, $fields = false, $asArray = false)
    {
        $model = static::find()->where($where);
        if ($fields) $model->select($fields);
        if ($order) $model->orderBy($order);
        if ($asArray) $model->asArray();
        return $model->one();
    }

    /**
     * 获取一条关联查询
     * @param array $params 参数集合
     * @return array
     */
    public static function getUnionOne($params = [])
    {
        $params['limit'] = 1;
        $list = self::lists($params);
        if ($list === false) {
            return false;
        }
        return isset($list[0]) ? $list[0] : [];
    }

    /**
     * 获取数据列表总数
     * @param array $params 参数集合
     * @return int
     */
    public static function listCount($params = [])
    {
        $query = static::find();
        self::paramPack($params, $query);
        $count = $query->count();

        return $count;
    }

    /**
     * @param string $where
     * @return integer
     */
    public static function listSum($params)
    {
        $query = static::find();
        self::paramPack($params, $query);

        $sum = $query->sum($params['sum']);

        return $sum;
    }

    /**
     * 获取数据列表
     * @param array $params 参数集合
     * @return array
     */
    public static function lists($params = [])
    {
        $list = [];
        $query = static::find();
        try {
            self::paramPack($params, $query);

            if (!empty($params['limit'])) {
                $query->limit($params['limit']);
            }
            if (!empty($params['offset'])) {
                $query->offset($params['offset']);
            } elseif (!empty($params['page']) && !empty($params['limit'])) {
                $query->offset(($params['page'] - 1) * $params['limit']);
            }

            $list = $query->asArray()->all();
        } catch (\Exception $exc) {
            self::$error = $exc->getMessage();
            return false;
        }

        return $list;
    }

    /**
     * 通用分页列表数据集获取方法
     *
     * 获取数据列表
     * @param array $params 参数集合
     * @param bool|int $count 总数 如果传进来，则不再执行查询总数 否则默认会查询
     * @return array
     */
    public static function listPage($params = [], $count = false)
    {
        $return = ['list' => [], 'count' => 0];
        $query = static::find();
        try {
            self::paramPack($params, $query);

            //每页条数
            if (!empty($params['limit'])) {
                $return['page_size'] = intval($params['limit']);
            }
            empty($return['page_size']) && $return['page_size'] = 20;

            //当前页数 page:当前页  limit:每页条数
            if (isset($params['page']) && intval($params['page']) > 0) {
                $return['page'] = $params['page'];
            } else {
                $return['page'] = 1;
            }
            $return['count'] = $count === false ? $query->count() : $count;
            //总页数
            $return['page_count'] = (!empty($return['count']) && $return['count'] > 0) ? ceil($return['count'] / $return['page_size']) : 1;
            //边界处理
            if ($return['page'] > $return['page_count']) $return['page'] = $return['page_count'];
            $query->limit($return['page_size']);
            $query->offset(($return['page'] - 1) * $return['page_size']);

            $return['list'] = $query->asArray()->all();
            $return['sql'] = $query->createCommand()->getRawSql();
        } catch (\Exception $exc) {
            self::$error = $exc->getMessage();
            return false;
        }

        return $return;
    }

    /**
     * 组成SQL
     */
    private static function paramPack(array $params = [], &$query, $isCount = false)
    {
        if (!empty($params['alias'])) {
            $alias = $params['alias'];
        }

        if (!empty($params['table'])) {
            $table = isset($alias) ? ($params['table'] . ' ' . $alias) : $params['table'];
        } else {
            $table = isset($alias) ? (static::tableName() . ' ' . $alias) : static::tableName();
        }
        $query->from($table);

        if (!empty($params['where'])) {
            if (is_array($params['where'])) {
                foreach ($params['where'] as $where) {
                    $query->andWhere($where);
                }
            } elseif (is_string($params['where'])) {
                $query->andWhere($params['where']);
            }
        }

        if (!empty($params['order']) && $isCount === false) {
            $query->orderBy($params['order']);
        }

        if (!empty($params['join']) && is_array($params['join'])) {
            foreach ($params['join'] as $join) {
                list($joinType, $joinTable, $joinOn) = $join;
                $query->join($joinType, $joinTable, $joinOn);
            }
        }

        if (!empty($params['group'])) {
            $query->groupBy($params['group']);
        }

        if (!empty($params['having'])) {
            $query->having($params['having']);
        }

        if (!empty($params['select']) && $isCount === false) {
            $query->select($params['select']);
        }

        if (!empty($params['index']) && $isCount === false) {
            $query->indexBy($params['index']);
        }
    }

    /**
     * 获取错误信息
     * @access public
     * @return mixed 错误信息
     */
    public static function getError()
    {
        $error = self::$error;
        if (empty($error)) {
            $error = '未知错误';
        }
        return $error;
    }

    /**
     * 批量插入多条数据
     * @param array $fieldNames
     * @param array $fieldValues
     */
    public static function batchInsert(array $fieldNames, array $fieldValues)
    {
        // table name, column names, column values
        return Yii::$app->db->createCommand()->batchInsert(self::tableName(), $fieldNames, $fieldValues)->execute();
    }
}
