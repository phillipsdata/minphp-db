<?php
namespace minphp\Db\Tests;

use PDO;

class MockablePdo extends PDO
{
    public function __construct()
    {
    }
}
