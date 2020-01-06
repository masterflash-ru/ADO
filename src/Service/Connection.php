<?php
namespace ADO\Service;

use ADO\Collection\Propertys;
use ADO\Entity\Property;
use ADO\Collection\Collections;
use ADO\Service\RecordSet;
use ADO\Exception\ADOException;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\SqlInterface;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Adapter\Driver\Pdo\Pdo as zfPdoDriver;
/*
конструктор генерации объекта Connection ADO
*/
class Connection
{
    public $ConnectionString; // Возвращает или задает строку, используемую для открытия базы данных.
    public $Database; // Получает имя текущей базы данных или базы данных,которая будет использоваться после открытия подключения.
    public $DefaultDatabase; // имя текущей базы данных
    public $DataSource; // /Получает имя сервера или имя файла источника данных.
    public $Provider; // Получает имя поставщика OLE DB, указанное в выражении "Provider= " строки подключения.
    
    public $State = 0; // Получает текущее состояние подключения. Серия констант adState*
    
    public $Properties; // свойсчват соединения
    public $Errors; // коллекция (объект) ошибок
    
    public $Mode; // тип соединения, определяется константтами серии adMode*
    
    private $connect_link; // объект/ресурс - соединения с базой данных
    
    public $driver; // сам объект для работы с базой данных
                    
    // перегруженное сво-ва
    private $container = array('cursorlocation' => NULL);    // тип курсора массив серегруженными сво-вами
    
    
    /**
    * $Provider - строка имени провайдера, в терминологии ADO, например, MysqlPdo
    * или экземпляр с интерфейсом Laminas\Db\Adapter\AdapterInterface - это адаптер из ZF3
    */
    public function __construct ($Provider = null)
    { 
        $this->Mode = adModeUnknown; // неопределенное состояние типа соединенения, разрешено чтение и запись
        $this->Errors = new Collections(); // объект-интератор ошибок объекта connect
        $this->Properties = new Collections(); // свой-ва соединения/сервера определяем события по умолчанию
        $this->container['cursorlocation'] = adUseServer; // по умолчанию курсор на стороне сервера
        if (is_string($Provider)){
            $this->Provider=$Provider;
            $d='ADO\\Drivers\\'.$this->Provider;
            $this->driver = new $d;
        }
        if ($Provider instanceof AdapterInterface){
            $this->setZfAdapter($Provider);
        }
}

public function Open ($server = '', $user = '', $password = '', $database = '')
{ 
    /* Открывает  подключение к базе данных со значениями  свойств, определяемыми объектом ConnectionString.парсим строку
     соединения,  если явно указаны параметры открытия,  то заменяем*/
    if ($this->State>0){
        throw new ADOException($this, 9, 'Connection:', array('Connection'));// ошибка, драйвер не найден
    }
    $dsna = [];
    $dsna = $this->Parser_ConnectionString($this->ConnectionString); // разобрать  строку соединения
    if ($server) {
        $dsna['host'] = $server;
    }
    if ($user) {
        $dsna['user'] = $user;
    }
    if ($password){
        $dsna['pass'] = $password;
    }
    if ($database) {
        $dsna['path'] = $database;
    }
    if (!empty($dsna['scheme'])) {
        $this->Provider = $dsna['scheme'];
    } else {
        if ($this->Provider) {
            $dsna['scheme']=$this->Provider ;
        }
    }

    if (! $this->driver) {
        $d='ADO\\Drivers\\'.$this->Provider;
        $this->driver = new $d;
    }
    // драйвер так и не определился, ошибка
    if (! $this->driver) {
        throw new ADOException($this, 1, 'Connection:', array($this->Provider));// ошибка, драйвер не найден
    }

    $status_array = $this->driver->connect($dsna); // открыть соединение, возвращает массив 3 элемента
    if ($status_array['number'] == 0) {
        // все хорошо
        $this->State = adStateOpen; // обхъект открыт
        $this->connect_link = $status_array['connect_link']; // соединение  с  базой  данных  получить  информацию  о  подключении
        $arr = $this->driver->get_server_Properties($this->connect_link);
        $this->Properties = new Propertys(); // получить экземпляр объекта Propertys
        foreach ($arr as $k => $v){
            $this->Properties->Append(new Property($k, adEmpty, $v, NULL));
        }
    } else {
        unset($status_array['connect_link']);
        $this->Errors->add([
            'number' => $status_array['number'],
            'description' => $status_array['description'],
            'source' => 'Connection',
            'SQLSTATE' => $status_array['description'], 
            'NativeError' => $status_array['number']
        ]);
        throw new ADOException($this, 1, 'Connection:', array($this->Provider)); // добавим в колекцию ошибок данные и вызовим исключение
    }
}
    
    
/**
* получить ZF3 адаптер базы
* нужно, если мы захотим работать с базой в стиле ZF3 
* запрос отправляем в драйвер напрямую, т.к. какой драйвер ZF3 нужно использовать определяется там
*/
public function getZfAdapter()
{
    return $this->driver->getZfAdapter($this->connect_link);
}

/**
* установить соединение в терминологии ADO из адаптера ZF3
*/
public function setZfAdapter(AdapterInterface $adapter)
{
    if (!$adapter->getDriver() instanceof zfPdoDriver){
        throw new ADOException($this, 1, 'Connection:', array($adapter->getDriver()->getConnection()->getDriverName())); // добавим в колекцию ошибок данные и вызовим исключение
    }
    switch ($adapter->getDriver()->getConnection()->getDriverName()){
        case "mysql":{
            $d='ADO\\Drivers\\MysqlPdo';
            $this->Provider="MysqlPdo";
            break;
        }
    }
    $this->driver = new $d;
    $status_array = $this->driver->connect($adapter->getDriver()->getConnection()->getResource() ); // открыть соединение, возвращает массив 3 элемента
    if ($status_array['number'] == 0) {
        // все хорошо
        $this->State = adStateOpen; // обхъект открыт
        $this->connect_link = $status_array['connect_link']; // соединение  с  базой  данных  получить  информацию  о  подключении
        $arr = $this->driver->get_server_Properties($this->connect_link);
        $this->Properties = new Propertys(); // получить экземпляр объекта Propertys
        foreach ($arr as $k => $v){
            $this->Properties->Append(new Property($k, adEmpty, $v, NULL));
        }
    } else {
        unset($status_array['connect_link']);
        $this->Errors->add([
            'number' => $status_array['number'],
            'description' => $status_array['description'],
            'source' => 'Connection',
            'SQLSTATE' => $status_array['description'], 
            'NativeError' => $status_array['number']
        ]);
        throw new ADOException($this, 1, 'Connection:', array($this->Provider)); // добавим в колекцию ошибок данные и вызовим исключение
    }
}

    
public function Execute ($CommandText, &$RecordsAffected = 0, $Options = adCmdText, $parameters = NULL)
    { // исполняет  запросы,  возвращает  кол-во  рядов  затронутых  при  исполнении
        /*
         * используются типы запросов: const adCmdText=1;//текстовое определение
         * команды/процедуры - по умолчанию const adCmdTable=2;//создать SQL
         * запрос, который вернет все строки указанной таблицы const
         * adCmdStoredProc=4;//хранимая процедура const
         * adExecuteNoRecords=128;//не возвращать строки, просто исполнить и все
         * возвращает объект RS если результат работы данного метода получается
         * набор записей $RecordsAffected - кол-во затронутых рядов, заполняет
         *adExecuteNoCreateRecordSet - НЕ возвращать RecordSet
         * провайдер после запроса
         */

    //проверим на объект select,insert,update,delete  из ZF3
    if ($CommandText instanceof SqlInterface) {
       //преобразуем в строку SQL
        $sql    = new Sql($this->getZfAdapter());
        $CommandText=$sql->buildSqlString($CommandText);
        $Options = adCmdText;
    }
        
    //проверим что нам надо вернуть
    if (!($Options & adExecuteNoCreateRecordSet)  && !($Options & adExecuteNoRecords)){
        // генерируем RS
        $rs = new RecordSet();
        $rs->ActiveConnect = $this;
        $rs->open($CommandText);
        $RecordsAffected = $rs->RecordCount; // кол-во застронутых строк
        return $rs;
    }
    
        
    // выполняем команды в провайдере
    $rez = $this->driver->Execute($this->connect_link, $CommandText, $Options, $parameters);
    // проверим на предмет ошибок, если они есть, добавим в коллекцию эти ошибки и выходим
    if (! empty($rez['error'])) {
        $this->Errors->add(array(
            'number' => $rez['error']->errorInfo[1],  // код ошибки драйвера
            'description' => $rez['error']->getMessage(), 
            'source' => $this->Provider, 
            'SQLSTATE' => $rez['error']->getCode(), 
            'NativeError' => $rez['error']->getMessage()
        )
                          ); // ошибка, какая-то проблема в запросе, запишем в коллекцию
        throw new ADOException($this); // вызвать исключение
    }
    $RecordsAffected = $rez['RecordsAffected'];
    if ($Options & adExecuteNoRecords) {
        return true;
    } else {
        return $rez;
    }
}

public function create_sql_update($stmt, array $old_value_array = [], array $new_value_array = [], array $status =['flag_change'=>false,'flag_new'=>false,'flag_delete'=>false]) 
{ 
    return $this->driver->create_sql_update($this->connect_link,$stmt, $old_value_array, $new_value_array, $status);
}

/**
* получить ID последней вставленой записи
*/
public function get_last_insert_id()
{
    return  $this->driver->get_last_insert_id($this->connect_link);    //вызываем одноименную функцию в драйвере
}



public function Close ()
    { // Закрывает подключение к источнику данных.
        $this->State = adStateClosed;
        $this->driver->Close(); // закрыть соединение с базой
    }
    
    // ***************************************** вспомогательные внутренние
    // функции
    private function Parser_ConnectionString ($db = '')
    {
        $dsna = array();
        $at = strpos($db, '://');
        
        $dsna = @parse_url('fake' . substr($db, $at));
        $dsna['scheme'] = substr($db, 0, $at);
        $dsna['host'] = isset($dsna['host']) ? $dsna['host'] : null;
        $dsna['port'] = isset($dsna['port']) ? $dsna['port'] : null;
        $dsna['unix_socket'] = isset($dsna['fragment']) ? $dsna['fragment'] : null;
        $dsna['user'] = isset($dsna['user']) ? rawurldecode($dsna['user']) : '';
        $dsna['pass'] = isset($dsna['pass']) ? rawurldecode($dsna['pass']) : '';
        $dsna['path'] = isset($dsna['path']) ? rawurldecode(substr($dsna['path'], 1)) : '';
        $dsna['query'] = isset($dsna['query']) ? rawurldecode($dsna['query']) : '';
        $this->DefaultDatabase = $dsna['path']; // текущая база данных
        $this->Database = $dsna['path'];
        return $dsna;
    
    }

public function BeginTrans() 
{
    $this->connect_link->beginTransaction();
}
public function CommitTrans() 
{
    $this->connect_link->commit();
}
public function RollbackTrans()
{
    $this->connect_link->rollBack();
}



// ************************** перегрузка
public function &__get ($var)
{
    $var = strtolower($var);
    // проверим к какой ппеременной обращается
    if (isset($this->container[$var])) {
        return $this->container[$var];
    }
    $arr = debug_backtrace();
    trigger_error("Undefined property: Connect::\$$var in " . $arr[0]['file'] . " on line " . $arr[0]['line'], E_USER_WARNING);
    return $this->State; // вернуть хоть что-либо
}

public function __set ($var, $value)
{
    $var = strtolower($var);
    switch ($var) {
        case 'cursorlocation': {
            $value = (int) $value;
            // расчитать кол-во страниц при указаном кол-ве записей
            if ($value > 0) {
                $this->container['cursorlocation'] = $value; // проверим на допустимость
            }
            break;
        }
            
    }
}

}
   
