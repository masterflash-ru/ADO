<?php
/**
ADO
 */

namespace ADO;

//use Zend\Router\Http\Literal;
//use Zend\Router\Http\Segment;
//use Zend\ServiceManager\Factory\InvokableFactory; //вcтроеная фабрика вызова

return [
'service_manager' => [
        'factories' => [
            "ADO\Connection" => Service\Factory\ConnectionFactory::class,
        ],
		
    ],

];
