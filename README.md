# Аналог ADONet

установка
composer require masterflash-ru/ado

Документация находится в папке doc

Использование алиасов для соединения с базой считаются устаревшими, но для старых сайтов оставлены.
Они использует старую конфигурацию подключения к базе из конфига с ключем "db", НЕ ИСПОЛЬЗУЙТЕ ИХ!
$connection=$container->get('ADO\Connection'); - возвращает соединение с базой, экземпляр объекта Connection данного пакета.
доступны алиасы:
ADOConnection
ADOdb
ADO\Service\Connection

Пакет использует соединение с базой данных из конфигурации приложения (используется абстрактная фабрика):
```php
    //абстрактная фабрика ищет в конфиге ключ "databases"
    "databases"=>[
        //соединение с базой + имя драйвера
        'DefaultSystemDb' => [
            'driver'=>'MysqlPdo',
            /*можно сделать соединерние через юникс сокет*/
            //"unix_socket"=>"/tmp/mysql.sock",
            "host"=>"localhost",
            'login'=>"root",
            "password"=>"123456",
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

Практически все методы и св-ва повторяют ADO от Microsoft. Аннотации не используются! Абстракции SQL не используются! Можно получать только некое подобие для более комфортной работы.

Имеются методы для гидратации (генерации массива сущностей) по аналогии с Doctrine и ZF3, но есть разница, прежде наполнять сущности, запрос уже выполнен и RS уже заполнен внутри.

для аналога ZF3 - работает в точно так же как описано в документации ZF, только передается в метод initialize объект RecordSet (запись не поддерживается)

для подобия Doctrine, возвращает сущность/сушности, но аннотации не используются:

создаем RecordSet и наполняем его
```php
$rs=new RecordSet();
$rs->CursorType = adOpenKeyset;
$rs->Open("select * from admins",$this->connection);

//получаем массив заполненых сущностей, если не указывать объект, то будет возвращет внутренний универсальный
$user = $rs->FetchEntityAll(Admins::class);

//аналогично, используется внутренний объект-сущность Universal
$user = $rs->FetchEntityAll();

//получить один элемент
$user = $rs->FetchEntity(Admins::class);

для записи данных из сущности в базу:
$rs->persist(Объект_сущности);
```
Заполненый RecordSet можно перебирать циклом foreach, RecordSet стал реализовывать интерфейс Iterator

Вы можете работать в стиле Laminas-Db

Создавать новое подключение не требуется, ADO вернет уже инициализированный объект Adapter:
```php
$connection=$container->get('DefaultSystemDb');
$adapter=$connection->getZfAdapter();
```
все возможности работы штатного Laminas-Db читайте в документации к нему.

пример работы с абстракциями в стиле Laminas-Db:
```php
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Select;

//$connection - экземпляр Connection пакета ADO, полученный например, в фабрике
$adapter=$connection->getZfAdapter();
$sql    = new Sql($adapter);
$select = $sql->select();      //вроде фабрики
$select->from('admin_menu');
$select->where(['id' => 1]); //выбираем запись для id=1

/*можно сразу создать объект Select из ZF3*/
$select = new select();      //аналогично update, delete, insert
$select->from('admin_menu');
$select->where(['id' => 1]); //выбираем запись для id=1

//можно дальше как принято в ZF3, можно передать объект в RecordSet пакета ADO, или вызвать Execute, который вернет RecordSet
$rs=$connection->Execute($select);
var_dump($rs->Fields);
```
Для перехода от Laminas-db к ADO (новое соединение не создается), пока поддерживается только PDO MySql:
```php
//$adapter - инициализированный адаптер в ZF3
$connection=new Connection($adapter);
//или
$connection=new Connection();
$connection->setZfAdapter($adapter);
//объект Connection автоматически переходит в состояние Open, т.е. готов к использованию
//далее стандартная работа в ADO, например,
$rs=$connection->Execute("select * from admin_menu");
var_dump($rs->Fields);
```
