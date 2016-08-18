<?php
namespace Zan\Framework\Network\Connection\Factory;
use Zan\Framework\Contract\Network\ConnectionFactory;
use \swoole_mysql as SwooleMysql;

class SwooleMysqlFactory implements ConnectionFactory {
    /**
     * @var array
     */
    private $config;
    private $conn;

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function create() {
        $connection = new SwooleMysqlConnection();
        $this->conn = new SwooleMysql();
        $this->conn->connect($this->config, function ($db, $result) use (& $connection) {
            if ($result === false) {
                //TODO:
                //var_dump($db->connect_errno, $db->connect_error);
                return;
            }
            //TODO
            //$this->conn->autocommit(true);
        });
        $connection->setSocket($this->conn);
        $connection->setConfig($this->config);
        return $connection;
    }

    public function close() {
        $this->conn->close();
    }
}