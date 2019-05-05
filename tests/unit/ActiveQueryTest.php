<?php
/**
 * @link http://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db\pgsql\tests;

/**
 * @group db
 * @group pgsql
 */
class ActiveQueryTest extends \Yiisoft\ActiveRecord\Tests\Unit\ActiveQueryTest
{
    public $driverName = 'pgsql';
}
