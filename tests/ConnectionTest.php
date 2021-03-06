<?php

declare(strict_types=1);

namespace Yiisoft\Db\Pgsql\Tests;

use PDO;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Pgsql\Connection;
use Yiisoft\Db\Transaction\Transaction;
use Yiisoft\Db\TestUtility\TestConnectionTrait;

/**
 * @group pgsql
 */
final class ConnectionTest extends TestCase
{
    use TestConnectionTrait;

    public function testConnection(): void
    {
        $this->assertIsObject($this->getConnection(true));
    }

    public function testConstruct(): void
    {
        $db = $this->getConnection();

        $this->assertEquals($this->cache, $db->getSchemaCache());
        $this->assertEquals($this->logger, $db->getLogger());
        $this->assertEquals($this->profiler, $db->getProfiler());
        $this->assertEquals($this->dsn->getDsn(), $db->getDsn());
    }

    public function testGetDriverName(): void
    {
        $db = $this->getConnection();

        $this->assertEquals($this->dsn->getDriver(), $db->getDriverName());
    }

    public function testOpenClose(): void
    {
        $db = $this->getConnection();

        $this->assertFalse($db->isActive());
        $this->assertNull($db->getPDO());

        $db->open();

        $this->assertTrue($db->isActive());
        $this->assertInstanceOf(PDO::class, $db->getPDO());

        $db->close();

        $this->assertFalse($db->isActive());
        $this->assertNull($db->getPDO());

        $db = new Connection($this->cache, $this->logger, $this->profiler, 'unknown::memory:');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('could not find driver');

        $db->open();
    }

    public function testQuoteValue(): void
    {
        $db = $this->getConnection();

        $this->assertEquals(123, $db->quoteValue(123));
        $this->assertEquals("'string'", $db->quoteValue('string'));
        $this->assertEquals("'It''s interesting'", $db->quoteValue("It's interesting"));
    }

    public function testQuoteTableName(): void
    {
        $db = $this->getConnection();

        $this->assertEquals('"table"', $db->quoteTableName('table'));
        $this->assertEquals('"table"', $db->quoteTableName('"table"'));
        $this->assertEquals('"schema"."table"', $db->quoteTableName('schema.table'));
        $this->assertEquals('"schema"."table"', $db->quoteTableName('schema."table"'));
        $this->assertEquals('"schema"."table"', $db->quoteTableName('"schema"."table"'));
        $this->assertEquals('{{table}}', $db->quoteTableName('{{table}}'));
        $this->assertEquals('(table)', $db->quoteTableName('(table)'));
    }

    public function testQuoteColumnName(): void
    {
        $db = $this->getConnection();

        $this->assertEquals('"column"', $db->quoteColumnName('column'));
        $this->assertEquals('"column"', $db->quoteColumnName('"column"'));
        $this->assertEquals('[[column]]', $db->quoteColumnName('[[column]]'));
        $this->assertEquals('{{column}}', $db->quoteColumnName('{{column}}'));
        $this->assertEquals('(column)', $db->quoteColumnName('(column)'));

        $this->assertEquals('"column"', $db->quoteSql('[[column]]'));
        $this->assertEquals('"column"', $db->quoteSql('{{column}}'));
    }

    public function testQuoteFullColumnName(): void
    {
        $db = $this->getConnection();

        $this->assertEquals('"table"."column"', $db->quoteColumnName('table.column'));
        $this->assertEquals('"table"."column"', $db->quoteColumnName('table."column"'));
        $this->assertEquals('"table"."column"', $db->quoteColumnName('"table".column'));
        $this->assertEquals('"table"."column"', $db->quoteColumnName('"table"."column"'));

        $this->assertEquals('[[table.column]]', $db->quoteColumnName('[[table.column]]'));
        $this->assertEquals('{{table}}."column"', $db->quoteColumnName('{{table}}.column'));
        $this->assertEquals('{{table}}."column"', $db->quoteColumnName('{{table}}."column"'));
        $this->assertEquals('{{table}}.[[column]]', $db->quoteColumnName('{{table}}.[[column]]'));
        $this->assertEquals('{{%table}}."column"', $db->quoteColumnName('{{%table}}.column'));
        $this->assertEquals('{{%table}}."column"', $db->quoteColumnName('{{%table}}."column"'));

        $this->assertEquals('"table"."column"', $db->quoteSql('[[table.column]]'));
        $this->assertEquals('"table"."column"', $db->quoteSql('{{table}}.[[column]]'));
        $this->assertEquals('"table"."column"', $db->quoteSql('{{table}}."column"'));
        $this->assertEquals('"table"."column"', $db->quoteSql('{{%table}}.[[column]]'));
        $this->assertEquals('"table"."column"', $db->quoteSql('{{%table}}."column"'));
    }

    public function testTransactionIsolation(): void
    {
        $db = $this->getConnection(true);

        $transaction = $db->beginTransaction();

        $transaction->setIsolationLevel(Transaction::READ_UNCOMMITTED);

        $transaction->commit();

        $transaction = $db->beginTransaction();

        $transaction->setIsolationLevel(Transaction::READ_COMMITTED);

        $transaction->commit();

        $transaction = $db->beginTransaction();

        $transaction->setIsolationLevel(Transaction::REPEATABLE_READ);

        $transaction->commit();

        $transaction = $db->beginTransaction();

        $transaction->setIsolationLevel(Transaction::SERIALIZABLE);

        $transaction->commit();

        $transaction = $db->beginTransaction();

        $transaction->setIsolationLevel(Transaction::SERIALIZABLE . ' READ ONLY DEFERRABLE');

        $transaction->commit();

        /* should not be any exception so far */
        $this->assertTrue(true);
    }

    /**
     * Test whether slave connection is recovered when call `getSlavePdo()` after `close()`.
     *
     * {@see https://github.com/yiisoft/yii2/issues/14165}
     */
    public function testGetPdoAfterClose(): void
    {
        $db = $this->getConnection();

        $db->setSlaves(
            '1',
            [
                '__class' => Connection::class,
                '__construct()' => [
                    $this->cache,
                    $this->logger,
                    $this->profiler,
                    $this->dsn->getDsn()
                ],
                'setUsername()' => [$db->getUsername()],
                'setPassword()' => [$db->getPassword()]
            ]
        );

        $this->assertNotNull($db->getSlavePdo(false));

        $db->close();

        $masterPdo = $db->getMasterPdo();

        $this->assertNotFalse($masterPdo);
        $this->assertNotNull($masterPdo);

        $slavePdo = $db->getSlavePdo(false);

        $this->assertNotFalse($slavePdo);
        $this->assertNotNull($slavePdo);
        $this->assertNotSame($masterPdo, $slavePdo);
    }

    public function testServerStatusCacheWorks(): void
    {
        $db = $this->getConnection();

        $db->setMasters(
            '1',
            [
                '__class' => Connection::class,
                '__construct()' => [
                    $this->cache,
                    $this->logger,
                    $this->profiler,
                    $this->dsn->getDsn()
                ],
                'setUsername()' => [$db->getUsername()],
                'setPassword()' => [$db->getPassword()]
            ]
        );

        $db->setShuffleMasters(false);

        $cacheKey = ['Yiisoft\Db\Connection\Connection::openFromPoolSequentially', $db->getDsn()];

        $this->assertFalse($this->cache->has($cacheKey));

        $db->open();

        $this->assertFalse(
            $this->cache->has($cacheKey),
            'Connection was successful – cache must not contain information about this DSN'
        );

        $db->close();

        $db = $this->getConnection();

        $cacheKey = ['Yiisoft\Db\Connection\Connection::openFromPoolSequentially', 'host:invalid'];

        $db->setMasters(
            '1',
            [
                '__class' => Connection::class,
                '__construct()' => [
                    $this->cache,
                    $this->logger,
                    $this->profiler,
                    'host:invalid'
                ],
                'setUsername()' => [$db->getUsername()],
                'setPassword()' => [$db->getPassword()]
            ]
        );

        $db->setShuffleMasters(true);

        try {
            $db->open();
        } catch (InvalidConfigException $e) {
        }

        $this->assertTrue(
            $this->cache->has($cacheKey),
            'Connection was not successful – cache must contain information about this DSN'
        );

        $db->close();
    }

    public function testServerStatusCacheCanBeDisabled(): void
    {
        $this->cache->clear();

        $db = $this->getConnection();

        $db->setMasters(
            '1',
            [
                '__class' => Connection::class,
                '__construct()' => [
                    $this->cache,
                    $this->logger,
                    $this->profiler,
                    $this->dsn->getDsn()
                ],
                'setUsername()' => [$db->getUsername()],
                'setPassword()' => [$db->getPassword()]
            ]
        );

        $db->setSchemaCache(null);

        $db->setShuffleMasters(false);

        $cacheKey = ['Yiisoft\Db\Connection\Connection::openFromPoolSequentially', $db->getDsn()];

        $this->assertFalse($this->cache->has($cacheKey));

        $db->open();

        $this->assertFalse($this->cache->has($cacheKey), 'Caching is disabled');

        $db->close();

        $cacheKey = ['Yiisoft\Db\Connection\Connection::openFromPoolSequentially', 'host:invalid'];

        $db->setMasters(
            '1',
            [
                '__class' => Connection::class,
                '__construct()' => [
                    $this->cache,
                    $this->logger,
                    $this->profiler,
                    'host:invalid'
                ],
                'setUsername()' => [$db->getUsername()],
                'setPassword()' => [$db->getPassword()]
            ]
        );

        try {
            $db->open();
        } catch (InvalidConfigException $e) {
        }

        $this->assertFalse($this->cache->has($cacheKey), 'Caching is disabled');

        $db->close();
    }
}
