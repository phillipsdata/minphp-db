<?php
namespace minphp\Db;

class SqliteConnection extends PdoConnection
{
    /**
     * {@inheritdoc}
     */
    public function makeDsn(array $db)
    {
        return 'sqlite:' . $db['database'];
    }
}
