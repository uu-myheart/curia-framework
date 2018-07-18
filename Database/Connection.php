<?php

namespace Curia\Framework\Database;

use PDO;

Class Connection
{
	/**
	 * 当前连接的pdo对象
	 *
	 * @var \PDO
	 */
	protected $pdo;

	/**
     * The default fetch mode of the connection.
     *
     * @var int
     */
    protected $fetchMode = PDO::FETCH_OBJ;

	public function __construct(array $config)
	{
		$this->pdo = (new MysqlConnector)->connect($config);	
	}

	public function select($query, $bindings = [])
	{
		$statement = tap($this->pdo->prepare($query))->setFetchMode($this->fetchMode);

		$this->bindValues($statement, $bindings);

		$statement->execute();

		return $statement->fetchAll();
	}

	/**
     * Run a select statement and return a single result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @param  bool  $useReadPdo
     * @return mixed
     */
    public function selectOne($query, $bindings = [])
    {
        $records = $this->select($query, $bindings);

        return array_shift($records);
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function insert($query, $bindings = [])
    {
        return $this->bindAndExecute($query, $bindings);
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function update($query, $bindings = [])
    {
        return $this->bindAndExecute($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function delete($query, $bindings = [])
    {
        return $this->bindAndExecute($query, $bindings);
    }

    protected function bindAndExecute($query, $bindings)
    {
    	$statement = $this->pdo->prepare($query);

        $this->bindValues($statement, $bindings);

        return $statement->execute();
    }

	/**
     * Bind values to their parameters in the given statement.
     *
     * @param  \PDOStatement $statement
     * @param  array  $bindings
     * @return void
     */
    public function bindValues($statement, $bindings)
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    }
}








