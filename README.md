Аналог ADONet

Практически все методы и св-ва повторяют ADO от Microsoft.
Добавлены методы для гидратации (генерации массива сущностей) по аналогии с Doctrine и ZF3, но есть разница, прежде наполнять сущности, запрос уже выполнен и RS уже заполнен внутри.

для аналога ZF3 - работает в точно так же как описано в документации ZF, только передается в метод initialize объект RecordSet (запись не поддерживается)


для аналога Doctrine:

создаем RecordSet и наполняем его
$rs=new RecordSet();
$rs->CursorType = adOpenKeyset;
$rs->Open("select * from Admins",$this->connection);

//получаем массив заполненых сущностей
$user = $rs->FetchEntityAll(Admins::class);

//получить один элемент
$user = $rs->FetchEntity(Admins::class);

для записи данных из сущности в базу:
$rs->persist(Объект_сущности);

