<?php
namespace minphp\Db\Tests\Unit;

use minphp\Db\SqliteConnection;
use PHPUnit_Framework_TestCase;

/**
 * @coversDefaultClass minphp\Db\SqliteConnection
 */
class SqliteConnectionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers ::makeDsn
     * @uses \minphp\Db\PdoConnection
     */
    public function testMakeDsn()
    {
        $connection = new SqliteConnection(array());
        $this->assertEquals(
            'sqlite::memory:',
            $connection->makeDsn(array('driver' => 'sqlite', 'database' => ':memory:'))
        );
    }
}
