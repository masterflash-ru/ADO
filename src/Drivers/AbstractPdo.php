<?php
/*
* драйвер для соединения с MySql посредством PDO
*/
namespace ADO\Drivers;

use ADO\Exception\ADOException;
use PDO;
use Laminas\Db\Adapter\Driver\Pdo\Pdo as ZfPdoDriver;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;

class AbstractPdo
{
    const adCmdText = 1; // текстовое определение команды/процедуры
    const adCmdTable = 2; // создать SQL запрос, который вернет все строки указанной таблицы
    const adCmdStoredProc = 4; // хранимая процедура
    const adExecuteNoRecords = 128; // не возвращать строки, просто исполнить и все
    
    public $NamedParameters = false; // передавать только порядковые номера параметров, если true тогда передаются и  имена
    protected $data_type = []; // типы данных - соответсвие между ADO и провайдером
    protected $Direction = []; // направление переменных в параметрах    
    protected $attributes; // константы для получения атрибут соединения
    
    public function __construct()
    {
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
        $this->data_type1 ['INT4'] = adInteger;
        $this->data_type1 ['SHORT'] = adBigInt;
        $this->data_type1 ['INT24'] = adBigInt;
        $this->data_type1 ['LONGLONG'] = adBigInt;
        $this->data_type1 ['FLOAT'] = adDouble;
        $this->data_type1 ['DOUBLE'] = adDouble;
        $this->data_type1 ['NEWDECIMAL'] = adDecimal;
        $this->data_type1 ['DATE'] = adDBDate;
        $this->data_type1 ['DATETIME'] = adDBTimeStamp;
        $this->data_type1 ['TIMESTAMP'] = adDBTimeStamp;
        $this->data_type1 ['TIME'] = adDBTime;
        $this->data_type1 ['YEAR'] = adInteger;
        $this->data_type1 ['STRING'] = adChar;
        $this->data_type1 ['VARCHAR'] = adChar;
        $this->data_type1 ['VAR_STRING'] = adChar;
        $this->data_type1 ['BLOB'] = adBinary;
        $this->data_type1 ['GEOMETRY'] = adEmpty;
        
        $this->Direction [2] = PDO::PARAM_INPUT_OUTPUT;
        $this->Direction [3] = PDO::PARAM_INPUT_OUTPUT;
    }

/**
* собственно соединение
* $dsna - массив с параметрами соединения или уже готовый к работе экземпляр PDO
*/
public function connect($dsna) 
    {
        try {
            //разбираем параметры в драйвер (передаются в драйвер при подключении, подробно в документации)
            if (is_array($dsna)){
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
                            $pgsqlcharset=$p1[1];
                        }
                    }
                }
                $drv=str_ireplace ( "MysqlPdo", "mysql", $dsna ['scheme'] );
                $drv=str_ireplace ( "PgsqlPdo", "pgsql", $drv );
                $dsn= $drv. ':dbname=' . $dsna ['path'] . ';';

                if ($dsna ['host']=="unix_socket"){
                    $dsn.="unix_socket=".$dsna ['unix_socket'];
                } else {
                    $dsn.="host=".$dsna ['host'];
                }

                if ($dsna ['port']){
                    $dsn.=";port=".$dsna ['port'];
                }
                if ($drv=="mysql"){
                    $dsn.=$charset;
                }
                
                @$connect_link = new PDO ( $dsn, $dsna ['user'], $dsna ['pass'] ,$param);
                $connect_link->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
                if (isset($pgsqlcharset) && $drv== 'pgsql') {
                    $connect_link->exec('SET NAMES ' . $connect_link->quote($pgsqlcharset));
                }

            }
            if ($dsna instanceof PDO){
                $connect_link=$dsna;
            }
            
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

    public function Execute($connect_link, $commandtext = '', $Options = AbstractPdo::adCmdText, &$parameters = NULL) 
        {
        try {
            if ($Options & AbstractPdo::adCmdTable){ 
                // вернуть все строки таблицы, имя таблицы указано в строке запроса
                $stmt = $connect_link->prepare ( 'select * from ' . $commandtext ); // готовим  к исполнению
                $stmt->execute ();
            } elseif ($Options & AbstractPdo::adCmdStoredProc)	{ 
                // хранимые процедуры, указывается только имя процедуры, все остальное приклеиваем мы
                $stmt = $connect_link->prepare ( 'call ' . $commandtext  ); // готовим к исполнению
                $stmt = $this->bindparam ( $stmt, $parameters );
                $stmt->execute ();
            } else {
                // обычная текстовая команда
                $stmt = $connect_link->prepare ( $commandtext ); // готовим  к  исполнению
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
            for($i = 0; $i < $c; $i ++)	{
                $this->fetchNext ( $stmt ); // считать запись
            }
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
*/
    public function create_sql_update(
        $connect_link,
        $stmt, 
        array $old_value_array = [], 
        array $new_value_array = [], 
        array $status =['flag_change'=>false,'flag_new'=>false,'flag_delete'=>false]
    ) 
    {
        $sql = new Sql($this->getZfAdapter($connect_link));
        if ($status ['flag_new']) { // создание новой записи - ассоциированый массив
            $primary_key_number_field=null;
            $i=0;
            foreach ($new_value_array as $k => $v ){
                //получить описание полей и проверить на primary_key
                $cc =$this->loadColumnMeta($stmt, $i,$connect_link);
                if (empty($cc['table'])){continue;}
                $ColumnMetaItem =$cc;
                if (empty($ColumnMetaItem['flags'])){continue;}
                $flags = $ColumnMetaItem['flags']; // флаги в колонках
                if (in_array ( 'primary_key', $flags )) {
                    $primary_key_number_field=$i; //есть первичный ключ, запомним его номер в списке полей
                    //первичный ключ не может быть null, в postgresql это строго, вообще удалим из массива если там null
                    if (is_null($v)){
                        unset($new_value_array[$k]);
                    }
                }
                $i++;
            }
            /*
            * возвращаем несколько параметров что бы за олдин запрос внести
            * несколько записей, тем самым снизить нагрузку на базу путем
            * уменьшения кол-ва обращений
            */
            $insert=$sql->Insert($ColumnMetaItem ['table']);
            $insert->values($new_value_array);
            $sql=$sql->buildSqlString($insert);
            $s=explode('VALUES',$sql);
            return ['sql' => $sql, 'values' => $s[1], 'type' => 'insert', 'sql1' => $s[0]." VALUES " ,'primary_key_number_field'=>$primary_key_number_field];
        }
        
        
        
        
        $column_count = count($old_value_array); // кол-во колонок
        $primary_key = []; // хранит массив имен полей которые являются первичными ключами
        $ColumnMeta=[];
        $keys=[];//просто ключи
        for($i = 0; $i < $column_count; $i ++) {
            // пробежим по колонкам и поищем первичный ключ
            $cc =$this->loadColumnMeta($stmt, $i,$connect_link);
            if (empty($cc['table'])){continue;}
            $ColumnMetaItem =$cc;
            if (empty($ColumnMetaItem['flags'])){continue;}
            $flags = $ColumnMetaItem ['flags']; // флаги в колонках
            if (in_array ( 'primary_key', $flags )){
                $primary_key [$ColumnMetaItem ['name']] = $old_value_array [$ColumnMetaItem ['name']];
            }
            if (in_array ( 'multiple_key', $flags ) || in_array ( 'unique_key', $flags )){
                $keys [$ColumnMetaItem ['name']] = $old_value_array [$ColumnMetaItem ['name']];
            }
            $ColumnMeta[$ColumnMetaItem ['name']]=$ColumnMetaItem;
        }

        if ($status ['flag_change']){
            // измненение существующей записи
            if (count($primary_key) > 0) {
                // первичный ключ есть
                $update=$sql->Update($ColumnMetaItem ['table']);
                $update->set($this->normalizeType(array_diff_key($new_value_array,$primary_key),$ColumnMeta));
                $update->where($this->normalizeType($primary_key,$ColumnMeta));

            } elseif (count ( $keys ) > 0){//обычные ключи
                $update=$sql->Update($ColumnMetaItem ['table']);
                $update->set($this->normalizeType(array_diff_key($new_value_array,$keys),$ColumnMeta));
                $update->where($this->normalizeType($keys,$ColumnMeta));

            } else { // первичного ключа нет и вообще нет никаких ключей, самый худший вариант
                $update=$sql->Update($ColumnMetaItem ['table']);
                $update->set($this->normalizeType(array_diff($new_value_array,$old_value_array),$ColumnMeta));
                $update->where($this->normalizeType($old_value_array,$ColumnMeta));
            }
            $sql=$sql->buildSqlString($update);
            return ['sql' => $sql, 'values' => null, 'type' => 'update', 'sql1' => null,'primary_key_number_field'=>null];
        }
        
        if ($status ['flag_delete']) {
            // удаление существующей записи
            if (count($primary_key) > 0) {
                // первичный ключ есть
                $delete=$sql->Delete($ColumnMetaItem ['table']);
                $delete->where($this->normalizeType($primary_key,$ColumnMeta));

            } elseif (count ( $keys ) > 0){//обычные ключи
                $delete=$sql->Delete($ColumnMetaItem ['table']);
                $delete->where($this->normalizeType($keys,$ColumnMeta));

            } else { // первичного ключа нет и вообще нет никаких ключей, самый худший вариант
                $delete=$sql->Delete($ColumnMetaItem ['table']);
                $delete->where($this->normalizeType($old_value_array,$ColumnMeta));
            }
            $sql=$sql->buildSqlString($delete);
            return ['sql' => $sql, 'values' => null, 'type' => 'delete', 'sql1' => null ,'primary_key_number_field'=>null];
        }
        
    }

public function get_last_insert_id($db_connect)
{
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
$connect_link - соединение с базой
*/	
public function loadColumnMeta($stmt, $col,$connect_link=null)
 {
    if (isset($stmt->ColumnMeta[$col])){
        return $stmt->ColumnMeta[$col];
    }

    // получить описания поля и заполнить
    $ColumnMeta = $stmt->getColumnMeta ( $col );
    
    $native_type=strtoupper($ColumnMeta['native_type']);
    
    // конвертировать в стандарт ADO
    if (isset ( $ColumnMeta ['native_type'] ) && isset ( $this->data_type1[$native_type] )){
        $ColumnMeta ['Type'] = $this->data_type1[$native_type];
    } else {
        $ColumnMeta ['Type'] = adEmpty;
    }
    
    $ColumnMeta ['NumericScale'] = $ColumnMeta['precision']; // точность чисел
    $ColumnMeta ['DefinedSize'] = $ColumnMeta["len"]; // установленная максимальная размерность поля
    //кеш для ускорения обработки
    $stmt->ColumnMeta[$col]=$ColumnMeta;
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
        $rez = $stmt->fetch ( PDO::FETCH_NUM );
        if (empty($rez)){
            return [];
        }
        $hash=spl_object_hash($stmt);
        //проверяем типы колонок и устанавливаем данные в соотвествии с этим типом
        foreach ($rez as $col=>$value){
            if (is_null($rez[$col])) {
                continue;
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

/**
* получить ZF3 адаптер базы
* нужно, если мы захотим работать с базой в стиле ZF3 
*/
public function getZfAdapter(Pdo $connect_link)
{
    $driver=new ZfPdoDriver($connect_link);
    return new Adapter($driver);  
}

/**
* нормализация типов данных
* приводит к типу поля, работает преобразование к целому числу или с плавающей запятой
*/    
protected function normalizeType(array $data, array $ColumnMeta)
{
    $rez=$data;
    foreach ($data as $field=>$value) {
        if (isset($ColumnMeta[$field])){
            if (in_array($ColumnMeta[$field]["Type"],[adTinyInt,adInteger,adBigInt,adSingle])){
                $rez[$field]=(int)$value;
            }
            /*проверим тип, если это целове число, возвращаем как есть*/
            if (in_array($ColumnMeta[$field]["Type"],[adDecimal,adDouble])){
                $rez[$field]= str_replace(",",".",(float)$value);
            }
        }
    }
    return $rez;
}
/*
* для внутренних нужд параметры в SQL, массив объектов $parametrs: [0]
* => ADO_Parameter Object ( [array_item:private] => Array ( [Name] =>
* n1 имя параметра [Type] => 3 тип данных [Direction] => direction
* направление переменной [Size] => 10 размерность [value] => value
* зеначение [Attributes] => атрибуты1 атрибюуты ) ) возвращает объект с
* результатом
*/

    protected function bindparam($stmt, &$parameters)
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