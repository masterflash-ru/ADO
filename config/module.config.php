<?php
/**
ADO
 */

namespace ADO;

return [
'service_manager' => [
        'factories' => [
            "ADO\Connection" => Service\Factory\ConnectionFactory::class,
        ],
		
    ],

];
