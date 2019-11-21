<?php
namespace ADO\Service\Factory;

use Psr\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use ADO\Service\Connection;

/**
фабрика генерации объекта соединения с базой  даннйх
 */
class ConnectionFactory implements FactoryInterface
{

public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
    //получить конфигурацию
    $config=$container->get('config');
    $config=$config["db"];
    
    $connection=new Connection();
    $unix_socket="";
    $init=[];
    if (!empty($config["character"])){
        $init[]="charset=".$config["character"];
    }
    if (!empty($config["charset"])){
        $init[]="charset=".$config["charset"];
    }

    $dsn = $config["driver"]."://".$config["login"].":".$config["password"];
    if (!empty($config["host"])){
        $dsn.="@".$config["host"];
    } else {
        if (!empty($config["unix_socket"])) {
            $dsn.="@unix_socket";
            $unix_socket="#".$config["unix_socket"];
        } else {
            $dsn.="@";
        }
    }
    $dsn.="/".$config["database"]."?".implode("&",$init).$unix_socket;
    $connection->ConnectionString=$dsn;
    $connection->open();
    return $connection;
}
}
