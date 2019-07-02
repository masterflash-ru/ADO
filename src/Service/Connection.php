<?php
namespace ADO\Service;

use ADO\Entity\Propertys;
use ADO\Entity\Property;
use ADO\Entity\Collections;
use ADO\Service\RecordSet;
use ADO\Exception\ADOException;
/*
конструктор генерации объекта Connection ADO
*/
class Connection
{ // объект cоnnection - содинение к базе данных текущего провайдера
    public $version = "1.02";

    public $ConnectionString; // Возвращает или задает строку, используемую для открытия базы данных.
    public $Database; // Получает имя текущей базы данных или базы данных,которая будет использоваться после открытия подключения.
    public $DefaultDatabase; // имя текущей базы данных
    public $DataSource; // /Получает имя сервера или имя файла источника данных.
    public $Provider; // Получает имя поставщика OLE DB, указанное в выражении "Provider= " строки подключения.
    public $ServerVersion; // Получает строку, содержащую номер версии сервера,к которому подключается клиент.
    
    public $State = 0; // Получает текущее состояние подключения. Серия констант adState*
    
    public $Properties; // свойсчват соединения
    public $Errors; // коллекция (объект) ошибок
    
    public $Mode; // тип соединения, определяется константтами серии adMode*
    
    private $event = array();

    private $connect_link; // объект/ресурс - соединения с базой данных
    
    public $driver; // сам объект для работы с базой данных
                    
    // перегруженное сво-ва
    private $container = array('cursorlocation' => NULL);    // тип курсора массив серегруженными сво-вами
    
    public function __construct ($Provider = '')
    { 
        $this->Mode = adModeUnknown; // неопределенное состояние типа соединенения, разрешено чтение и запись
        $this->Errors = new Collections(); // объект-интератор ошибок объекта connect
        $this->Properties = new Collections(); // свой-ва соединения/сервера определяем события по умолчанию
        $this->event['ConnectComplete'] = array($this, 'ConnectComplete'); // по  умолчанию  это  заглушка
        $this->event['infoMessage'] = array($this, 'infoMessage'); // по умолчанию это заглушка
        $this->event['Disconnect'] = array($this, 'Disconnect'); // по  умолчанию  это  заглушка
        $this->event['WillConnect'] = array($this, 'WillConnect'); // по умолчанию это заглушка
        $this->event['ExecuteComplete'] = array($this, 'ExecuteComplete'); // по умолчанию это заглушка
        $this->container['cursorlocation'] = adUseServer; // по умолчанию курсор на стороне сервера
        if (!empty($Provider)) {
            $this->Provider=$Provider;
        }

        if ($this->Provider){
            $d='ADO\\Drivers\\'.$this->Provider;
            $this->driver = new $d;
        }
}

public function Open ($server = '', $user = '', $password = '', $database = '')
{ 
    /* Открывает  подключение к базе данных со значениями  свойств, определяемыми объектом ConnectionString.парсим строку
     соединения,  если явно указаны параметры открытия,  то заменяем*/
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
        call_user_func($this->event['infoMessage'], $this->Errors, adStatusErrorsOccurred, $this); // генерироватьсобатиеinfoMessage
        throw new ADOException($this, 1, 'Connection:', array($this->Provider));// ошибка, драйвер не найден
    }
    call_user_func_array($this->event['WillConnect'], array(&$this->ConnectionString, &$dsna['user'], &$dsna['pass'],     NULL, adStatusOK, &$this)); // генерировать событие WillConnect

    $status_array = $this->driver->connect($dsna); // открыть соединение, возвращает массив 3 элемента
    if ($status_array['number'] == 0) {
        // все хорошо
        $this->State = adStateOpen; // обхъект открыт
        call_user_func($this->event['ConnectComplete'], $this->Errors, adStatusOK, $this); // генерировать собатие ConnectComplete
        $this->connect_link = $status_array['connect_link']; // соединение  с  базой  данных  получить  информацию  о  подключении
        $arr = $this->driver->get_server_Properties($this->connect_link);
        $this->Properties = new Propertys(); // получить экземпляр объекта Propertys
        foreach ($arr as $k => $v){
            $this->Properties->Append(new Property($k, adEmpty, $v, NULL));
        }
    } else {
        unset($status_array['connect_link']);
        // генерируем событие и передаем ошибки
        call_user_func($this->event['infoMessage'], $this->Errors, adStatusErrorsOccurred, $this); // генерировать собатие infoMessage
        call_user_func($this->event['ConnectComplete'], $this->Errors, adStatusErrorsOccurred, $this); // генерировать собатие ConnectComplete
        $this->Errors->add([
            'number' => $status_array['number'],
            'description' => $status_array['description'],
            'source' => 'Connection',
            'SQLSTATE' => $status_array['description'], 
            'NativeError' => $status_array['number']
        ]);
        throw new ADOException($this); // добавим в колекцию ошибок данные и вызовим исключение
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
        
        
        //$a=$Options & adExecuteNoCreateRecordSet;
        //echo $Options.':'.$a.'<br>';
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
    $rez = $this->driver->Execute($this->connect_link, $CommandText,     $Options, $parameters);
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
        call_user_func_array($this->event['ExecuteComplete'], 
                             array($rez['RecordsAffected'],         //затронуто рядов
                                   $this->Errors,                            //коллекция ошибок
                                   adStatusErrorsOccurred,//флаг ошибки
                                   NULL,                                        //ссылка на объект Command
                                   NULL,                                        //ссыдка на RecordSet
                                   &$this                                        //ссылка на объект Connection
                                  )); // генерировать событие ExecuteComplete
        throw new ADOException($this); // вызвать исключение
    }
    $RecordsAffected = $rez['RecordsAffected'];
    call_user_func_array($this->event['ExecuteComplete'], 
                         array($rez['RecordsAffected'],         //затронуто рядов
                               $this->Errors,                            //коллекция ошибок
                               adStatusOK,//флаг ошибки
                               NULL,                                        //ссылка на объект Command
                               NULL,                                        //ссыдка на RecordSet
                               &$this                                        //ссылка на объект Connection
                              )); // генерировать событие ExecuteComplete
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
        call_user_func($this->event['Disconnect'], adStateClosed,     adStatusErrorsOccurred, $this); // генерировать собатие ConnectComplete
    }
    
    // ------------------------------------------------------------------
    // СОБЫТИЯ
    // *************** событие ConnectComplete
    public function ConnectComplete ($pError, $adStatus, $pConnection)
    { // событие происходит,когда произошло  соединения
        /*
         * здесь это заглушка, но можно определить $pError - объект коллекции
         * ошибок $adStatus - статус, см. константы серии adStatus* $pConnection
         * - данный объект (connection)
         */
        // echo 'событие ConnectComplete-'.$adStatus;
    }
    
    // *************** событие infoMessage
    public function infoMessage ($pError, $adStatus, $pConnection)
    { // событие  происходит,  когда  произошло  ошибка  соединения
        /*
         * здесь это заглушка, но можно определить $pError - объект коллекции
         * ошибок $adStatus - статус, см. константы серии adStatus* $pConnection
         * - данный объект (connection)
         */
    }

    public function Disconnect ($adStatus, $pConnection)
    { //
        /*
         * здесь это заглушка, но можно определить Событие возникает после того,
         * как прервано подключение к источнику данных. Параметры аналогичны
         * параметрам события ConnectComplete.
         */
    }

    public function WillConnect (&$ConnectionString, &$UserID, &$Password, $Options, $adStatus, $pConnection)
    { //
        /*
         * здесь это заглушка, но можно определить Событие возникает перед тем,
         * как осуществлено подключение к источнику данных. Параметры в основном
         * аналогичны параметрам события ConnectComplete. Options -
         * зарезервировано. В обработчике события можно изменять параметры
         * подключения.
         */
    }

    public function ExecuteComplete ($RecordsAffected, $pError, $adStatus,     $pCommand, $pRecordset, $pConnection)
    { //
        /*
         * здесь это заглушка, но можно определить Событие происходит после
         * завершения работы команды. Параметр RecordsAffected - целое число
         * (long) - содержит количество записей, которые затрагивает команда.
         * Остальные параметры аналогичны одноимённым параметрам описанных выше
         * других событий. Событие ExecuteComplete может произойти вследствие
         * вызовов Connection.Execute, Command.Execute, Recordset.Open,
         * Recordset.Requery или Recordset.NextRecordset.
         */
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
   
