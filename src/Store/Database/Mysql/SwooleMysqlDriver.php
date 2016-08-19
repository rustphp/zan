<?php
namespace Zan\Framework\Store\Database\Mysql;
use Zan\Framework\Contract\Network\Connection;
use Zan\Framework\Contract\Store\Database\DriverInterface;
use Zan\Framework\Contract\Store\Database\DbResultInterface;
use Zan\Framework\Network\Server\Timer\Timer;
use Zan\Framework\Store\Database\Mysql\Exception\MysqliConnectionLostException;
use Zan\Framework\Store\Database\Mysql\Exception\MysqliQueryException;
use Zan\Framework\Store\Database\Mysql\Exception\MysqliQueryTimeoutException;
use Zan\Framework\Store\Database\Mysql\Exception\MysqliSqlSyntaxException;
use Zan\Framework\Store\Database\Mysql\Exception\MysqliTransactionException;


class SwooleMysqlDriver implements DriverInterface {
    /**
     * @var SwooleMysqlConnection
     */
    private $connection;
    private $sql;
    /**
     * @var callable
     */
    private $callback;
    private $result;
    const DEFAULT_QUERY_TIMEOUT = 3000;

    public function __construct(Connection $connection) {
        $this->setConnection($connection);
    }

    private function setConnection(Connection $connection) {
        $this->connection = $connection;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function execute(callable $callback) {
        $this->callback = $callback;
    }

    /**
     * @param $sql
     * @return DbResultInterface
     */
    public function query($sql) {
        $config = $this->connection->getConfig();
        $timeout = isset($config['timeout']) ? $config['timeout'] : self::DEFAULT_QUERY_TIMEOUT;
        var_dump($sql);
        $this->sql = $sql;
        $swoole_mysql = $this->connection->getSocket();
        if (isset($swoole_mysql->connect_error) && $swoole_mysql->connect_error) {
            $this->connection->close();
            yield $this;
            return;
        }
        $swoole_mysql->query($this->sql, [$this, 'onSqlReady']);
        Timer::after($timeout, [$this, 'onQueryTimeout'], spl_object_hash($this));
        yield $this;
    }

    /**
     * @param $link
     * @param $result
     * @return DbResultInterface
     */
    public function onSqlReady($link, $result) {
        Timer::clearAfterJob(spl_object_hash($this));
        $exception = null;
        if ($result === false) {
            if (in_array($link->_errno, [2013, 2006])) {
                $this->connection->close();
                $exception = new MysqliConnectionLostException();
            } elseif ($link->_errno == 1064) {
                $error = $link->_error;
                $this->connection->release();
                $exception = new MysqliSqlSyntaxException($error);
            } else {
                $error = $link->_error;
                $this->connection->release();
                $exception = new MysqliQueryException($error);
            }
        }
        $this->result = $result;
        call_user_func_array($this->callback, [new MysqliResult($this), $exception]);
    }

    public function onQueryTimeout() {
        $this->connection->close();
        //TODO: sql记入日志
        call_user_func_array($this->callback, [null, new MysqliQueryTimeoutException()]);
    }

    public function getResult() {
        return $this->result;
    }

    //TODO:
    public function beginTransaction() {
        $beginTransaction = (yield $this->connection->getSocket()
            ->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT));
        if (!$beginTransaction) {
            throw new MysqliTransactionException('mysqli begin transaction error');
        }
        yield $beginTransaction;
    }

    //TODO:
    public function commit() {
        $commit = (yield $this->connection->getSocket()->commit());
        if (!$commit) {
            throw new MysqliTransactionException('mysqli commit error');
        }
        $this->connection->release();
        yield $commit;
    }

    //TODO:
    public function rollback() {
        $rollback = (yield $this->connection->getSocket()->rollback());
        if (!$rollback) {
            throw new MysqliTransactionException('mysqli rollback error');
        }
        $this->connection->release();
        yield $rollback;
    }

    //TODO:
    public function releaseConnection() {
        $beginTransaction = (yield getContext('begin_transaction', false));
        if ($beginTransaction === false) {
            $this->connection->release();
        }
        yield true;
    }
}
