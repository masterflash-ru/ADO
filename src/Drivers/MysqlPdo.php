<?php
/*
* драйвер для соединения с MySql посредством PDO
*/
namespace ADO\Drivers;

use ADO\Exception\ADOException;
use PDO;

class MysqlPdo 
{
    const adCmdText = 1; // текстовое определение команды/процедуры
    const adCmdTable = 2; // создать SQL запрос, который вернет все строки указанной таблицы
    const adCmdStoredProc = 4; // хранимая процедура
    const adExecuteNoRecords = 128; // не возвращать строки, просто исполнить и все
    
    public $NamedParameters = false; // передавать только порядковые номера параметров, если true тогда передаются и  имена
    private $data_type = []; // типы данных - соответсвие между ADO и провайдером
    private $Direction = []; // направление переменных в параметрах    
    private $attributes; // константы для получения атрибут соединения
    private $cache_meta_data=[];
    
    public function __construct()
    {
        //проверим наличие драйвера PDO в системе
        if (!in_array("pdo_mysql",get_loaded_extensions ())){
            throw new ADOException(NULL,18,'driver',array('PDO_MYSQL'));
        }

        //соотвествия констант (типов) принятых в ADO и PDO
        $this->data_type [adSmallInt] = PDO::PARAM_INT;
        $this->data_type [adInteger] = PDO::PARAM_INT;
        $this->data_type [adSingle] = PDO::PARAM_INT;
        $this->data_type [adDouble] = PDO::PARAM_INT;
        $this->data_type [adError] = PDO::PARAM_INT;
        $this->data_type [adUnsignedTinyInt] = PDO::PARAM_INT;
        $this->data_type [adUnsignedSmallInt] = PDO::PARAM_INT;
        $this->data_type [adUnsignedInt] = PDO::PARAM_INT;
        $this->data_type [adBigInt] = PDO::PARAM_INT;
        $this->data_type [adUnsignedBigInt] = PDO::PARAM_INT;
        $this->data_type [adBinary] = PDO::PARAM_LOB;
        $this->data_type [adChar] = PDO::PARAM_STR;
        $this->data_type [adDBDate] = PDO::PARAM_STR;
        $this->data_type [adDBTime] = PDO::PARAM_STR;
        // $this->data_type[adDecimal]=PDO::PARAM_LOB;
        // $this->data_type[adCurrency]=PDO::PARAM_LOB;
        $this->data_type [adEmpty] = PDO::PARAM_STR;
        $this->data_type [adDBTime] = PDO::PARAM_STR;
        $this->data_type [adBoolean] = PDO::PARAM_STR;
        $this->data_type [adDBTimeStamp] = PDO::PARAM_STR;
        $this->data_type [adBoolean] = PDO::PARAM_BOOL;
        
        // обратное преобразование тип_колонки -> тип в АДО
        $this->data_type1 ['BIT'] = adBoolean;
        $this->data_type1 ['TINYINT'] = adTinyInt; // не работает пока
        $this->data_type1 ['TINY'] = adTinyInt;
        $this->data_type1 ['BOOLEAN'] = adBoolean; // не работает пока
        $this->data_type1 ['LONG'] = adInteger;
        $this->data_type1 ['SHORT'] = adBigInt;
        $this->data_type1 ['INT24'] = adBigInt;
        $this->data_type1 ['LONGLONG'] = adBigInt;
        $this->data_type1 ['FLOAT'] = adSingle;
        $this->data_type1 ['DOUBLE'] = adDouble;
        $this->data_type1 ['NEWDECIMAL'] = adDecimal;
        $this->data_type1 ['DATE'] = adDBDate;
        $this->data_type1 ['DATETIME'] = adChar;
        $this->data_type1 ['TIMESTAMP'] = adDBTimeStamp;
        $this->data_type1 ['TIME'] = adDBTime;
        $this->data_type1 ['YEAR'] = adInteger;
        $this->data_type1 ['STRING'] = adChar;
        $this->data_type1 ['VAR_STRING'] = adChar;
        $this->data_type1 ['BLOB'] = adBinary;
        $this->data_type1 ['GEOMETRY'] = adEmpty;
        
        $this->Direction [2] = PDO::PARAM_INPUT_OUTPUT;
        $this->Direction [3] = PDO::PARAM_INPUT_OUTPUT;
        $this->attributes = [
            "AUTOCOMMIT",
            "ERRMODE",
            "CASE",
            "CLIENT_VERSION",
            "CONNECTION_STATUS",
            "PERSISTENT",
            "SERVER_INFO",
            "SERVER_VERSION" 
            ];
    }


    function connect($dsna) 
    {
        try {
            //разбираем параметры в драйвер (передаются в драйвер при подключении, подробно в документации)
            $param=[];
            $charset="";
            if (isset($dsna['query'])) {
                $p=explode('&',$dsna['query']);
                foreach ($p as $v) {
                    $p1=explode("=",$v); 
                    //если константы не существует, просто игнорируем параметр и все
                    if (defined($p1[0])) {
                        $param[ constant($p1[0])]=$p1[1];
                    } elseif ($p1[0]=="charset") {
                        //если есть кодировка
                        $charset=";charset=".$p1[1];
                    }
                }
            }
            $dsn=str_ireplace ( "MysqlPdo", "mysql", $dsna ['scheme'] ) . ':dbname=' . $dsna ['path'] . ';';
            
            if ($dsna ['host']=="unix_socket"){
                $dsn.="unix_socket=".$dsna ['unix_socket'];
            } else {
                $dsn.="host=".$dsna ['host'];
            }

            if ($dsna ['port']){
                $dsn.=";port=".$dsna ['port'];
            }
            $dsn.=$charset;

            @$connect_link = new PDO ( $dsn, $dsna ['user'], $dsna ['pass'] ,$param);
            $connect_link->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            
        } catch (PDOException $e ) { 
            // ошибка, обращаемся к обработчику ошибок соединения
            return ['connect_link' => NULL, 'number' => 2, 'description' => $e->getMessage (), 'source' => $e->getFile () ];
        }
        return ['connect_link' => $connect_link, 'number' => 0, 'description' => '', 'source' => '' ]; // вернуть ОК
    }
    
    public function get_server_Properties($connect_link) 
    { // возвращает информацию (массив, ключ - имя атрибута) соединения, атрибуты
        $arr = [];
        foreach ( $this->attributes as $val ) {
            $k = strtolower ( $val );
            $arr [$k] = $connect_link->getAttribute ( constant ( "PDO::ATTR_$val" ) );
        }
        return $arr;
    }

    public function Execute($connect_link, $commandtext = '', $Options = MysqlPdo::adCmdText, &$parameters = NULL) 
        { // выполняет запрос и возвращает объект/русурс с результатом

        /* ключи массива error
        0  	SQLSTATE error code (a five characters alphanumeric identifier defined in the ANSI SQL standard).
        1 	Driver specific error code.
        2 	Driver specific error messag

        возвращается массив 
            'error'=>$error, см. выше
            'stmt'=>$stmt  объект с результатом, который будет обрабатывать этот же объект
            RecordsAffected=>$RecordsAffected - кеол-во затронутых рядов при операции
        */
        try {
            if ($Options & MysqlPdo::adCmdTable){ 
                // вернуть все строки таблицы, имя таблицы указано в строке запроса
                $stmt = $connect_link->prepare ( 'select * from ' . $commandtext, array (PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL ) ); // готовим  к исполнению
                $stmt->execute ();
            } elseif ($Options & MysqlPdo::adCmdStoredProc)	{ 
                // хранимые процедуры, указывается только имя процедуры, все остальное приклеиваем мы
                $stmt = $connect_link->prepare ( 'call ' . $commandtext , array (PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL ) ); // готовим к исполнению
                $stmt = $this->bindparam ( $stmt, $parameters );
                $stmt->execute ();
            } else {
                // обычная текстовая команда
                $stmt = $connect_link->prepare ( $commandtext, array (PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL ) ); // готовим  к  исполнению
                $stmt = $this->bindparam ( $stmt, $parameters );
                $stmt->execute ();
            }
        } catch ( PDOException $e ) {
            $stmt->_row_number = 0;
            $RecordsAffected = 0;
            $stmt->errorInfo = $stmt->errorInfo ();
            return [
                'error' => $e,            // объект PDOException
                'stmt' => $stmt,          // результат выборки, его будем обрабатывать
                'RecordsAffected' => 0 
                ];
        }
        $stmt->_row_number = 1;             //начальное положение указателя
        $RecordsAffected = $stmt->rowCount ();
        
        /*
        * Ошибки - массив из 3-х элементов: 0 SQLSTATE error code (a five
        * characters alphanumeric identifier defined in the ANSI SQL standard).
        * 1 Driver specific error code. 2 Driver specific error message.
        */
        return [
            'error' => NULL,
            'stmt' => $stmt,        // результат выборки, его будем обрабатывать
            'RecordsAffected' => $RecordsAffected 
            ];
    }

    public function stmt_data_seek($stmt, $row_number)
    {
        // перемещает указатель на запись номер $row_number (1......)
        if ($row_number < $stmt->_row_number && $row_number>0 || ! isset ( $stmt->_row_number )) {
            $stmt->_row_number = 1;
            // вначале переходим в начало и потом переходим на нужную позицию
            $stmt->execute (); // заново выполнить запрос
            for($i =1; $i < $row_number; $i ++) {
                $this->fetchNext ( $stmt ); // считать запись, и перемотать указатель, если больше одной записи
            }
        }
        if ($row_number > $stmt->_row_number) {
            // перемотать указатель на нужное положение вперед след. запись
            // будет готова для чтения
            $c = $row_number - $stmt->_row_number;
            for($i = 0; $i < $c; $i ++)	$this->fetchNext ( $stmt ); // считать запись
        }
    }

    /*
     возвращает кол-во колонок в результате выборки*
    */
    public function columnCount($stmt) 
    {
        return $stmt->columnCount ();
    }

/*
* служебная функция для генерации SQL для обновления записей посредством
* RecordSet $stmt - объект с резульатом выборки в формате провайдера
* $old_value_array - массив в виде имя_поля1=>значение1,..... возможно 2
* варианта, с использованием первичного ключа, и без, от этого зависит
* условие в конструкции where $status - смассив статусов
* (новая/иземененная)
  
  возвращается массив:
  array ('sql' => $sql,				- сам SQL запрос
	  			 'values' => $v,				- то что вставляется в иструкцию insert, сами значения
				  'type' => 'insert',		-сама операция (insert update delete)
				   'sql1' => $sql_start 	- начальная инструкция при добавлении, пример, "insert into (....) values (.....)"  (актуально если мы вставляем массово записи в одной инструкции insert - это не стандартная реализация)
				  'primary_key_number_field' - номер поля с первичным ключем, если ключа нет - NULL (нужно что бы RS получил последний ID записи и внес свое поле с этим ключем)
				   );
*/
    public function create_sql_update($stmt, $old_value_array = [], $new_value_array = [], $status =['flag_change'=>false,'flag_new'=>false,'flag_delete'=>false]) 
    { 
        if ($status ['flag_new']) { // создание новой записи - ассоциированый массив
            $s1 = [];$primary_key_number_field=NULL;
            $i=0;
            foreach ( $new_value_array as $k => $v ){
                if (is_null ( $v )) {
                    $s1 [] = '`'.$k . "`=null";
                } else {$s1 [] = "'" . addslashes ( $v ) . "'";}
                //получить описание полей и проверить на primary_key
                //$ColumnMeta = $stmt->getColumnMeta ( $i);
                $ColumnMeta =$this->loadColumnMeta($stmt, $i);
                $flags = $ColumnMeta ['flags']; // флаги в колонках
                if (in_array ( 'primary_key', $flags )) {
                    $primary_key_number_field=$i; //есть первичный ключ, запомним его номер в списке полей
                }
                $i++;
            }
            /*
            * возвращаем несколько параметров что бы за олдин запрос внести
            * несколько записей, тем самым снизить нагрузку на базу путем
            * уменьшения кол-ва обращений
            */
            $v = " (" . implode ( ",", $s1 ) . ")";
            $sql_start = "insert into `" . $ColumnMeta ['table'] . "` (`" . implode ( '`,`', array_keys ( $new_value_array ) ) . "`) values ";
            $sql = $sql_start . $v;
            return array ('sql' => $sql, 'values' => $v, 'type' => 'insert', 'sql1' => $sql_start ,'primary_key_number_field'=>$primary_key_number_field);
        }
        
        if ($status ['flag_change']){ 
            // измненение существующей записи
            $column_count = count ( $old_value_array ); // кол-во колонок
            $primary_key = []; // хранит массив имен полей которые являются первичными ключами
            for($i = 0; $i < $column_count; $i ++) {
                // пробежим по колонкам и поищем первичный ключ
                //$ColumnMeta = $stmt->getColumnMeta ( $i );
                $ColumnMeta =$this->loadColumnMeta($stmt, $i);
                $flags = $ColumnMeta ['flags']; // флаги в колонках
                if (in_array ( 'primary_key', $flags )){
                    $primary_key [$ColumnMeta ['name']] = $old_value_array [$ColumnMeta ['name']];
                }
            }
            if (count ( $primary_key ) > 0) {
                // первичный ключ есть
                $s = [];
                foreach ( $primary_key as $k => $v ){
                    if (is_null ( $v )) {
                        $s [] ='`'. $k . "`=null";
                    } else {
                        $s [] ='`'. $k . "`='" . addslashes ( $v ) . "'";
                    }
                }
                $s1 = [];
                foreach ( $new_value_array as $k => $v ){
                    if (is_null ( $v )) {
                        $s1 [] = '`'.$k . "`=null";
                    } else {
                        $s1 [] = '`'.$k . "`='" . addslashes ( $v ) . "'";
                    }
                }
            } else { // первичного ключа нет
                $s = [];
                foreach ( $old_value_array as $k => $v ){
                    if (is_null ( $v )) {
                        $s [] ='`'.$k . "`=null";
                    } else {
                        $s [] = '`'.$k . "`='" . addslashes ( $v ) . "'";
                    }
                }
                $s1 = [];
                foreach ( $new_value_array as $k => $v ){
                    if (is_null ( $v )) {
                        $s1 [] = '`'.$k . "`=null";
                    } else {
                        $s1 [] ='`'. $k . "`='" . addslashes ( $v ) . "'";
                    }
                }
            }
            $sql = "update `" . $ColumnMeta ['table'] . "` set " . implode ( ',', $s1 ) . " where " . implode ( ' and ', $s );
            $v = NULL;
            return array ('sql' => $sql, 'values' => $v, 'type' => 'update', 'sql1' => NULL,'primary_key_number_field'=>NULL );
        }
        
        if ($status ['flag_delete']) {
            // удаление существующей записи
            $column_count = count ( $old_value_array ); // кол-во колонок
            
            $primary_key = []; // хранит массив имен полей которые являются первичными ключами
            for($i = 0; $i < $column_count; $i ++) {
                // пробежим по колонкам и поищем первичный ключ
                //$ColumnMeta = $stmt->getColumnMeta ( $i );
                $ColumnMeta =$this->loadColumnMeta($stmt, $i);
                $flags = $ColumnMeta ['flags']; // флаги в колонках
                if (in_array ( 'primary_key', $flags )) {
                    $primary_key [$ColumnMeta ['name']] = $old_value_array [$ColumnMeta ['name']];
                }
            }
            if (count ( $primary_key ) > 0) {
                // первичный ключ есть
                $s = [];
                foreach ( $primary_key as $k => $v ){
                    if (is_null ( $v )) {
                        $s [] ='`'. $k . "`=null";
                    } else {
                        $s [] = '`'.$k . "`='" . addslashes ( $v ) . "'";
                    }
                }
            } else {
                // первичного ключа нет
                $s = [];
                foreach ( $old_value_array as $k => $v ){
                    if (is_null ( $v )) {
                        $s [] = '`'.$k . "`=null";
                    } else {
                        $s [] ='`'. $k . "`='" . addslashes ( $v ) . "'";
                    }
                }
            }
            $sql = "delete from `" . $ColumnMeta ['table'] . "` where " . implode ( ' and ', $s );
            $v = NULL;
            return array ('sql' => $sql, 'values' => $v, 'type' => 'delete', 'sql1' => NULL ,'primary_key_number_field'=>NULL );
        }
        
    }

    public function get_last_insert_id($db_connect)
    {
        // получить ID вставлено записи
        return $db_connect->lastInsertId(); 
    }

// закрыть курсор
    public function Close($stmt) 
    {
        $stmt->closeCursor();
    }

/*
получить метаданные поля
$stmt - резкльтат запроса PDO
$col - номер колонки 0...
*/	
public function loadColumnMeta($stmt, $col)
 {
    if (isset($this->cache_meta_data[spl_object_hash($stmt)][$col])) {
        return $this->cache_meta_data[spl_object_hash($stmt)][$col];
    }
    // получить описания поля и заполнить
    $ColumnMeta = $stmt->getColumnMeta ( $col );
    
    // конвертировать в стандарт ADO
    if (isset ( $ColumnMeta ['native_type'] ) && isset ( $this->data_type1 [$ColumnMeta ['native_type']] )){
        $ColumnMeta ['Type'] = $this->data_type1 [$ColumnMeta ['native_type']];
    } else {
        $ColumnMeta ['Type'] = adEmpty;
    }
		
    // функция getColumnMeta эксперементальная, сейчас не определяет тип
    // BLOB, насильно проверим и вставми тип, остальные типы не так критичны
    // конечно все через задницу, но нужно для экспорта в XML, по этому
    // типу производится кодировка в base64
    // if (isset($ColumnMeta['native_type']) &&
    // strtolower($ColumnMeta['native_type']) == 'blob')
    // $ColumnMeta['Type']=adBinary;
    
    $ColumnMeta ['NumericScale'] = $ColumnMeta ['precision']; // точность чисел
    $ColumnMeta ['DefinedSize'] = NULL; // установленная максимальная размерность поля
    //кеш для ускорения обработки
    $this->cache_meta_data[spl_object_hash($stmt)][$col]=$ColumnMeta;
    return $ColumnMeta;
    }

/*
* $stmt - объект с результатом запроса BOF - true если указатель
* указывает в начало (т.е. сразу после исполнения запроса) возвращается
* ассоциативный массив
*/
    public function fetchFirst($stmt) 
    { // получить строку 0
        $stmt->_row_number = 1;
        $stmt->execute (); // если первая строка тогда заново исполнить, что бы перемотать указатель в начало
        return $stmt->fetch ( PDO::FETCH_NUM );
    }


    public function fetchNext($stmt) 
    {
        $rez=[];
        $rez = $stmt->fetch ( PDO::FETCH_NUM );
        
        $hash=spl_object_hash($stmt);
        //проверяем типы колонок и устанавливаем данные в соотвествии с этим типом
        foreach ($rez as $col=>$value){
            if (!isset($this->cache_meta_data[$hash][$col])) {
                $this->loadColumnMeta($stmt, $col);
            }
            $meta=$this->cache_meta_data[$hash][$col];
            
			switch ($meta["Type"]){
                case adSmallInt:
                case adInteger:
                case adTinyInt:
                case adUnsignedTinyInt:
                case adUnsignedSmallInt:
                case adUnsignedInt:
                case adBigInt:
                case adUnsignedBigInt: {
                    $rez[$col]=(int)$rez[$col];
                    break;
                }
                case adSingle:
                case adDouble:
                case adCurrency:
                case adDecimal:{
                    $rez[$col]=(float)$rez[$col];
                    break;
                }
            }
        }
        $stmt->_row_number ++;
        return $rez;
    }
	
	/*
	 * public function fetchLast($stmt) {//получить последнюю строку /* $stmt -
	 * объект с результатом запроса возвращается ассоциативный массив /
	 * $c=$stmt->rowCount()-1;//получить кол-во строк for ($i=0;$i<$c;$i++)
	 * $rez=$stmt->fetch(PDO::FETCH_NUM);//перемотать указатель return $rez; }
	 */

/*
public function fetchPrevious($stmt,$AbsolutePosition)
{//получить предыдущую строку
/*
$stmt - объект с результатом запроса
AbsolutePosition - положение указателя 1....
возвращается ассоциативный массив
 
$stmt->execute();
for ($i=1;$i<$AbsolutePosition;$i++) $rez=$stmt->fetch(PDO::FETCH_NUM);//перемотать указатель
return $rez;
}
*/


/*
* для внутренних нужд параметры в SQL, массив объектов $parametrs: [0]
* => ADO_Parameter Object ( [array_item:private] => Array ( [Name] =>
* n1 имя параметра [Type] => 3 тип данных [Direction] => direction
* направление переменной [Size] => 10 размерность [value] => value
* зеначение [Attributes] => атрибуты1 атрибюуты ) ) возвращает объект с
* результатом
*/

    private function bindparam($stmt, &$parameters)
    { // разбирает по параметрам
        if (is_object ( $parameters )){
            if ($this->NamedParameters){
                foreach ( $parameters as $k => &$v ) {
                    // нумерация начинается с 1, поправим индекс +1
                    $stmt->bindParam ( ":" . $v->Name, $v->Value, $this->data_type [$v->Type], $v->Size );
                }
            } else {
                foreach ( $parameters as $k => $v ) {
                    // нумерация начинается с 1, поправим индекс +1
                    $stmt->bindParam ( $k + 1, $v->Value, $this->data_type [$v->Type], $v->Size );
                }
            }
        }
        return $stmt;
    }
}