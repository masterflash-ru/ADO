<?php
/**
ADO
 */

namespace ADO;
use ADO\Service\Connection;

return [
'service_manager' => [
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
