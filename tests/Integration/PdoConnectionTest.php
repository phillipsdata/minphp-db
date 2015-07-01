<?php
namespace minphp\Db\Tests\Integration;

use minphp\Db\PdoConnection;
use minphp\Db\SqliteConnection;
use PHPUnit_Framework_TestCase;

/**
 * @coversDefaultClass minphp\Db\PdoConnection
 */
class PdoConnectionTest extends PHPUnit_Framework_TestCase
{
    private function getDbInfo()
    {
        return array(
            'database' => ':memory:'
        );
    }

    /**
     * @covers ::connect
     * @covers ::__construct
     * @covers ::setConnection
     * @covers ::getConnection
     * @covers \minphp\Db\SqliteConnection::makeDsn
     * @covers ::makeConnection
     * @covers ::query
     * @covers ::prepare
     */
    public function testConnect()
    {
        $connection = new SqliteConnection($this->getDbInfo());
        $this->assertInstanceOf('\PDO', $connection->connect());
    }

    /**
     * @covers ::__construct
     * @covers ::reuseConnection
     * @covers ::getConnection
     * @covers ::setConnection
     * @covers ::connect
     * @covers \minphp\Db\SqliteConnection::makeDsn
     * @covers ::makeConnection
     */
    public function testReuseConnection()
    {
        $dbInfo = $this->getDbInfo();

        $connectionA = new SqliteConnection($dbInfo);
        $pdoA = $connectionA->connect();

        $connectionB = new SqliteConnection($dbInfo);
        $pdoB = $connectionB->reuseConnection(true)->connect();

        $connectionC = new SqliteConnection($dbInfo);
        $pdoC = $connectionC->reuseConnection(false)->connect();

        $this->assertSame($pdoA, $pdoB);
        $this->assertNotSame($pdoA, $pdoC);
    }

    /**
     * @covers ::__construct
     * @covers ::connect
     * @covers ::getConnection
     * @covers ::makeDsn
     * @covers ::makeConnection
     * @expectedException \RuntimeException
     */
    public function testConnectException()
    {
        $dbInfo = array(
            'driver' => 'invalid',
            'host' => 'invalid',
            'database' => 'invalid',
            'charset_query' => "SET NAMES 'utf8'"
        );

        $connection = new PdoConnection($dbInfo);
        $connection->connect();
    }
}
