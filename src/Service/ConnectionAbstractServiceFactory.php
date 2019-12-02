<?php
/**
 * абстрактная фабрика для создания соединения с базой
 *
 */

namespace ADO\Service;

use Interop\Container\ContainerInterface;
use ADO\Service\Connection;
use Zend\ServiceManager\AbstractFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ConnectionAbstractServiceFactory implements AbstractFactoryInterface
{

    /*флаг старой версии конфигурации*/
    protected $oldConfig=false;

    /**
     * @var array
     */
    protected $config;

    /**
     * Configuration key for database objects
     *
     * @var string
     */
    protected $configKey = 'databases';

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @return boolean
     */
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        $config = $this->getConfig($container);
        if (empty($config)) {
            return false;
        }
        /*стоит проверка на старую конфигурацию сайтов*/
        if ($requestedName=="DefaultSystemDb" && $this->oldConfig ){
            /*для совместимости со старыми конфигами*/
            $requestedName="db";
        } 

        return (isset($config[$requestedName]) && is_array($config[$requestedName]));
    }

    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @param  string $name
     * @param  string $requestedName
     * @return boolean
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        return $this->canCreate($serviceLocator, $requestedName);
    }

    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string             $requestedName
     * @param  null|array         $options
     * @return object
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {

        $config = $this->getConfig($container);
        if ($this->oldConfig){
            /*для совместимости со старыми конфигами*/
            $requestedName="db";
        } 
        $config=$config[$requestedName];
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

    public function createServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        return $this($serviceLocator, $requestedName);
    }

    /**
     * Retrieve cache configuration, if any
     *
     * @param  ContainerInterface $container
     * @return array
     */
    protected function getConfig(ContainerInterface $container)
    {
        if ($this->config !== null) {
            return $this->config;
        }

        if (! $container->has('config')) {
            $this->config = [];
            return $this->config;
        }
        $config = $container->get('config');
        
        /*смотрим старые версии конфигурации, если есть, возвращаем ее для совместимости
        * и ставим флаг старой конфигурации
        */
        if (isset($config["db"]) && is_array($config["db"])) {
            $this->config = $config;
            $this->oldConfig=true;
            return $this->config;
        }

        if (! isset($config[$this->configKey])) {
            $this->config = [];
            return $this->config;
        }

        $this->config = $config[$this->configKey];
        return $this->config;
    }
}
