<?php
/**
* ADO
*/

namespace ADO;
use ADO\Service\Connection;

return [
    'service_manager' => [
        'abstract_factories' => [
            Service\ConnectionAbstractServiceFactory::class,
        ],
        
        /*оставлено для совместимости старых конфигураций*/
        'factories' => [
            Connection::class => Service\Factory\ConnectionFactory::class,
        ],
        'aliases' => [
            "ADOConnection" => Connection::class,
            "ADO\Connection" => Connection::class,
            "ADOdb"=> Connection::class,
        ],
    ],
];
