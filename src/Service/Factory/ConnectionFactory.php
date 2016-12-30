<?php
namespace ADO\Service\Factory;

use Interop\Container\ContainerInterface;
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
		 
		 $init="PDO::MYSQL_ATTR_INIT_COMMAND=set names ".$config["character"];
		 
		 $dsn = $config["driver"]."://".$config["login"].":".$config["password"]."@".$config["host"]."/".$config["database"]."?".$init;
		$connection->ConnectionString=$dsn;
		$connection->open();
        return $connection;
    }
}
