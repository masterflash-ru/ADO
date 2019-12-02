<?php
/*
* драйвер для соединения с MySql посредством PDO
*/
namespace ADO\Drivers;

use ADO\Exception\ADOException;
use PDO;

class MysqlPdo extends AbstractPdo
{

public function __construct()
    {
        //проверим наличие драйвера PDO в системе
        if (!in_array("pdo_mysql",get_loaded_extensions ())){
            throw new ADOException(null,18,'driver',array('PDO_MYSQL'));
        }
        $this->attributes = [
            "AUTOCOMMIT",
            "ERRMODE",
            "CASE",
            "CLIENT_VERSION",
            "CONNECTION_STATUS",
            "PERSISTENT",
            "SERVER_INFO",
            "SERVER_VERSION",
            "DRIVER_NAME",
            ];
        parent::__construct();
    }
}