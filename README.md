# Аналог ADONet

установка
composer require masterflash-ru/ado

Использование алиасов для соединения с базой считаются устаревшими, но для старых сайтов оставлены.
Они использует старую конфигурацию подключения к базе из конфига с ключем "db", НЕ ИСПОЛЬЗУЙТЕ ИХ!
$connection=$container->get('ADO\Connection'); - возвращает соединение с базой, экземпляр объекта Connection данного пакета.
доступны алиасы:
ADOConnection
ADOdb
ADO\Service\Connection


Практически все методы и св-ва повторяют ADO от Microsoft.

Добавлены методы для гидратации (генерации массива сущностей) по аналогии с Doctrine и ZF3, но есть разница, прежде наполнять сущности, запрос уже выполнен и RS уже заполнен внутри.

для аналога ZF3 - работает в точно так же как описано в документации ZF, только передается в метод initialize объект RecordSet (запись не поддерживается)

Документация находится в папке doc

для аналога Doctrine:

создаем RecordSet и наполняем его
```php
$rs=new RecordSet();
$rs->CursorType = adOpenKeyset;
$rs->Open("select * from admins",$this->connection);

//получаем массив заполненых сущностей
$user = $rs->FetchEntityAll(Admins::class);

//получить один элемент
$user = $rs->FetchEntity(Admins::class);

для записи данных из сущности в базу:
$rs->persist(Объект_сущности);
```
Заполненый RecordSet можно перебирать циклом foreach, RecordSet стал реализовывать интерфейс Iterator

Пакет использует соединение с базой данных из конфигурации приложения:
```php

    "databases"=>[
        //соединение с базой + имя драйвера
        'DefaultSystemDb' => [
            'driver'=>'MysqlPdo',
            /*можно сделать соединерние через юникс сокет*/
            //"unix_socket"=>"/tmp/mysql.sock",
            "host"=>"localhost",
            'login'=>"root",
            "password"=>"vfibyf",
            "database"=>"simba4",
            "locale"=>"ru_RU",
            "character"=>"utf8"
        ],
    ],
```
Для работы в фабриках вашего приложения используйте соединение:
```php
$connection=$container->get('DefaultSystemDb');
```
где DefaultSystemDb - это имя соединения с базой в конфигурационном файле



