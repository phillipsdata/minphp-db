<?php
namespace minphp\Db;

use PDO;
use PDOStatement;
use PDOException;
use RuntimeException;
use InvalidArgumentException;

/**
 * Establishes and maintains a connection to one or more PDO resources.
 */
class PdoConnection
{
    /**
     * @var array Default PDO attribute settings
     */
    protected $options = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_CASE => PDO::CASE_LOWER,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_STRINGIFY_FETCHES => false
    );

    /**
     * @var PDO PDO connection
     */
    private $connection;

    /**
     * @var array An array of all database connections established
     */
    private static $connections = array();

    /**
     * @var array An array of all database connection info (used to find a matching connection)
     */
    private static $dbInfos = array();

    /**
     * @var array An array of database info for this instance
     */
    private $dbInfo;

    /**
     * @var PDOStatement PDO Statement
     */
    private $statement;

    /**
     * @var mixed Fetch Mode the PDO:FETCH_* constant (int)
     */
    private $fetchMode = PDO::FETCH_OBJ;

    /**
     * @var boolean Reuse existing connection if available
     */
    private $reuseConnection = true;

    /**
     * Creates a new Model object that establishes a new PDO connection using
     * the given database info, or the default configured info set in the database
     * config file if no info is given
     *
     * @param array $dbInfo Database information for this connection
     */
    public function __construct(array $dbInfo = null) {
        $this->dbInfo = $dbInfo;
    }

    /**
     * Attemps to initialize a connection to the database if one does not already exist.
     *
     * @return PDO The PDO connection
     */
    public function connect()
    {
        $connection = $this->getConnection();
        if ($connection instanceof PDO) {
            return $connection;
        }
        return $this->makeConnection($this->dbInfo);
    }

    /**
     *
     * @param boolean $enable True to reuse an existing matching connection if available
     */
    public function reuseConnection($enable)
    {
        $this->reuseConnection = $enable;
    }

    /**
     * Sets the fetch mode to the given value, returning the old value
     *
     * @param int $fetchMode The PDO:FETCH_* constant (int) to fetch records
     */
    public function setFetchMode($fetchMode)
    {
        $cur = $this->fetchMode;
        $this->fetchMode = $fetchMode;
        return $cur;
    }

    /**
     * Get the last inserted ID
     *
     * @param string $name The name of the sequence object from which the ID should be returned
     * @return string The last ID inserted, if available
     */
    public function lastInsertId($name = null)
    {
        return $this->connect()->lastInsertId($name);
    }

    /**
     * Sets the given value to the given attribute for this connection
     *
     * @param long $attribute The attribute to set
     * @param int $value The value to assign to the attribute
     */
    public function setAttribute($attribute, $value)
    {
        $this->connect()->setAttribute($attribute, $value);
    }

    /**
     * Query the Database using the given prepared statement and argument list
     *
     * @param string $sql The SQL to execute
     * @param string $... Bound parameters [$param1, $param2, ..., $paramN]
     * @return PDOStatement The resulting PDOStatement from the execution of this query
     */
    public function query($sql)
    {
        $params = func_get_args();
        // Shift the SQL parameter off of the list
        array_shift($params);

        // If 2nd param is an array, use it as the series of params, rather than
        // the rest of the param list
        if (isset($params[0]) && is_array($params[0])) {
            $params = $params[0];
        }

        $this->connect();

        // Store this statement in our PDO object for easy use later
        $this->statement = $this->prepare($sql, $this->fetchMode);

        // Execute the query
        $this->statement->execute($params);

        // Return the statement
        return $this->statement;
    }

    /**
     * Prepares an SQL statement to be executed by the PDOStatement::execute() method.
     * Useful when executing the same query with different bound parameters.
     *
     * @param string $sql The SQL statement to prepare
     * @param int $fetchMode The PDO::FETCH_* constant
     * @return PDOStatement The resulting PDOStatement from the preparation of this query
     * @see PDOStatement::execute()
     */
    public function prepare($sql, $fetchMode = null)
    {
        if ($fetchMode === null) {
            $fetchMode = $this->fetchMode;
        }

        $this->statement = $this->connect()->prepare($sql);
        // Set the default fetch mode for this query
        $this->statement->setFetchMode($fetchMode);

        return $this->statement;
    }

    /**
     * Begin a transaction
     *
     * @return boolean True if the transaction was successfully opened, false otherwise
     */
    public function begin()
    {
        return $this->connect()->beginTransaction();
    }

    /**
     * Rolls back and closes the transaction
     *
     * @return boolean True if the transaction was successfully rolled back and closed, false otherwise
     */
    public function rollBack()
    {
        return $this->connect()->rollBack();
    }

    /**
     * Commits a transaction
     *
     * @return boolean True if the transaction was successfully commited and closed, false otherwise
     */
    public function commit()
    {
        return $this->connect()->commit();
    }

    /**
     * Returns the connection's PDO object if a connection has been established, null otherwise.
     *
     * @return PDO The PDO connection object, null if no connection exists
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Set the PDO connection to use
     *
     * @param PDO $connection
     */
    public function setConnection(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Return all registered connections available
     *
     * @return array
     */
    public function getRegisteredConnections()
    {
        return $this->connections;
    }

    /**
     * Get the number of rows affected by the last query
     *
     * @param PDOStatement $statement The statement to count affected rows on,
     * if null the last query() statement will be used.
     * @return int The number of rows affected by the previous query
     * @throws RuntimeException Thrown when not PDOStatement available
     */
    public function affectedRows(PDOStatement $statement = null)
    {
        if ($statement === null) {
            $statement = $this->statement;
        }

        if (!($statement instanceof PDOStatement)) {
            throw new RuntimeException('Can not get affectedRows without a PDOStatement.');
        }

        return $statement->rowCount();
    }

    /**
     * Build a DSN string using the given array of parameters
     *
     * @param array $db An array of parameters
     * @return string The DSN string
     * @throws InvalidArgumentException Thrown when $db contains invalid parameters
     */
    public function makeDsn(array $db)
    {
        if (!isset($db['driver']) || !isset($db['database']) || !isset($db['host'])) {
            throw new InvalidArgumentException(
                sprintf('Required %s', "array('driver'=>,'database'=>,'host'=>)")
            );
        }

        return $db['driver'] . ":dbname=" . $db['database'] . ";host=" . $db['host']
            . (
                isset($db['port'])
                ? ";port=" . $db['port']
                : ""
            );
    }

    /**
     * Establish a new PDO connection using the given array of information. If
     * a connection already exists, no new connection will be created.
     *
     * @param array $dbInfo Database information for this connection
     * @return \PDO The connection
     * @throws RuntimeException Throw when PDOException is encountered
     */
    private function makeConnection(array $dbInfo)
    {
        // Attempt to reuse an existing connection if one exists that matches this connection
        if ($this->reuseConnection
            && ($key = array_search($dbInfo, self::$dbInfos)) !== false
        ) {
            $this->setConnection(self::$connections[$key]);
            return $this->getConnection();
        }

        // Override any default settings with those provided
        $options = (array) (
                isset($dbInfo['options'])
                ? $dbInfo['options']
                : null
            )
            + $this->options;

        try {
            $this->setConnection(new PDO(
                $this->makeDsn($dbInfo),
                (
                    isset($dbInfo['user'])
                    ? $dbInfo['user']
                    : null
                ),
                (
                    isset($dbInfo['pass'])
                    ? $dbInfo['pass']
                    : null
                ),
                $options
            ));
            $connection = $this->getConnection();

            // Record the connection
            self::$connections[] = $connection;
            self::$dbInfos[] = $dbInfo;

            // Run a character set query to override the database server's default character set
            if (!empty($dbInfo['charset_query'])) {
                $this->query($dbInfo['charset_query']);
            }
            return $connection;

        } catch (PDOException $e) {
            throw new RuntimeException($e->getMessage());
        }

        throw new RuntimeException('Connection could not be established.');
    }
}
