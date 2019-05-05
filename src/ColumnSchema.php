<?php
/**
 * @link http://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Db\pgsql;

use Yiisoft\Db\ArrayExpression;
use Yiisoft\Db\ExpressionInterface;
use Yiisoft\Db\JsonExpression;

/**
 * Class ColumnSchema for PostgreSQL database.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 */
class ColumnSchema extends \Yiisoft\Db\ColumnSchema
{
    /**
     * @var int the dimension of array. Defaults to 0, means this column is not an array.
     */
    public $dimension = 0;

    /**
     * {@inheritdoc}
     */
    public function dbTypecast($value)
    {
        if ($value === null) {
            return $value;
        }

        if ($value instanceof ExpressionInterface) {
            return $value;
        }

        if ($this->dimension > 0) {
            return new ArrayExpression($value, $this->dbType, $this->dimension);
        }
        if (in_array($this->dbType, [Schema::TYPE_JSON, Schema::TYPE_JSONB], true)) {
            return new JsonExpression($value, $this->dbType);
        }

        return $this->typecast($value);
    }

    /**
     * {@inheritdoc}
     */
    public function phpTypecast($value)
    {
        if ($this->dimension > 0) {
            if (!is_array($value)) {
                $value = $this->getArrayParser()->parse($value);
            }
            if (is_array($value)) {
                array_walk_recursive($value, function (&$val, $key) {
                    $val = $this->phpTypecastValue($val);
                });
            } elseif ($value === null) {
                return;
            }

            return new ArrayExpression($value, $this->dbType, $this->dimension);
        }

        return $this->phpTypecastValue($value);
    }

    /**
     * Casts $value after retrieving from the DBMS to PHP representation.
     *
     * @param string|null $value
     *
     * @return bool|mixed|null
     */
    protected function phpTypecastValue($value)
    {
        if ($value === null) {
            return;
        }

        switch ($this->type) {
            case Schema::TYPE_BOOLEAN:
                switch (strtolower($value)) {
                    case 't':
                    case 'true':
                        return true;
                    case 'f':
                    case 'false':
                        return false;
                }

                return (bool) $value;
            case Schema::TYPE_JSON:
                return json_decode($value, true);
        }

        return parent::phpTypecast($value);
    }

    /**
     * Creates instance of ArrayParser.
     *
     * @return ArrayParser
     */
    protected function getArrayParser()
    {
        static $parser = null;

        if ($parser === null) {
            $parser = new ArrayParser();
        }

        return $parser;
    }
}
