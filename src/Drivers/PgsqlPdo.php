<?php
/*
*/
namespace ADO\Drivers;

use ADO\Exception\ADOException;
use PDO;


class PgsqlPdo extends AbstractPdo
{
	
    public function __construct()
    {
        //проверим наличие драйвера PDO в системе
        if (!in_array("pdo_pgsql",get_loaded_extensions ())){
            throw new ADOException(null,18,'driver',array('PDO_PGSQL'));
        }
        $this->attributes = [
            "ERRMODE",
            "CASE",
            "CLIENT_VERSION",
            "CONNECTION_STATUS",
            "PERSISTENT",
            "SERVER_INFO",
            "SERVER_VERSION",
            "DRIVER_NAME",
        ];
        parent::__construct();
    }
    public function Execute($connect_link, $commandtext = '', $Options = AbstractPdo::adCmdText, &$parameters = NULL) 
        {
        // выполняет запрос и возвращает объект/русурс с результатом

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
            if ($Options & AbstractPdo::adCmdTable){ 
                // вернуть все строки таблицы, имя таблицы указано в строке запроса
                $stmt = $connect_link->prepare ( 'select * from ' . $commandtext, array (PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL ) ); // готовим  к исполнению
                $stmt->execute ();
            } elseif ($Options & AbstractPdo::adCmdStoredProc)	{ 
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
        
        //огромный костыль, т.к. после SELECT $stmt->rowCount () всегда 0
        if (!$RecordsAffected){
            $regex = '/^SELECT\s+(?:ALL\s+|DISTINCT\s+)?(?:.*?)\s+FROM\s+(.*)$/i';
            if (preg_match($regex, $commandtext, $output) > 0) {
                $_stmt = $connect_link->query("SELECT COUNT(*) FROM {$output[1]}", PDO::FETCH_NUM);
                $RecordsAffected=(int)$_stmt->fetchColumn();
            }
        }
        return [
            'error' => NULL,
            'stmt' => $stmt,        // результат выборки, его будем обрабатывать
            'RecordsAffected' => $RecordsAffected 
            ];
    }
    
    /**
    * создает строки SQL в формате принятом для данного драйвера
    */
    public function create_sql_update($connect_link,$stmt, array $old_value_array = [], array $new_value_array = [], array $status =['flag_change'=>false,'flag_new'=>false,'flag_delete'=>false]) 
    { 
        if ($status ['flag_new']) { // создание новой записи - ассоциированый массив
            $s1 = [];
            $primary_key_number_field=null;
            $i=0;
            foreach ( $new_value_array as $k => $v ){
                //получить описание полей и проверить на primary_key
                $cc =$this->loadColumnMeta($stmt, $i);
                if (empty($cc['table'])){continue;}
                $ColumnMetaItem =$cc;
                $s1[] =$this->quote($v,$ColumnMetaItem,$connect_link);
                $flags = $ColumnMetaItem['flags']; // флаги в колонках
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
            $sql_start = "insert into " . $ColumnMetaItem ['table'] . " (" . implode ( ',', array_keys ( $new_value_array ) ) . ") values ";
            $sql = $sql_start . $v;
            return array ('sql' => $sql, 'values' => $v, 'type' => 'insert', 'sql1' => $sql_start ,'primary_key_number_field'=>$primary_key_number_field);
        }
        
        if ($status ['flag_change']){
            // измненение существующей записи
            $column_count = count ( $old_value_array ); // кол-во колонок
            $primary_key = []; // хранит массив имен полей которые являются первичными ключами
            $ColumnMeta=[];
            $keys=[];//просто ключи
            for($i = 0; $i < $column_count; $i ++) {
                // пробежим по колонкам и поищем первичный ключ
                $cc =$this->loadColumnMeta($stmt, $i);
                if (empty($cc['table'])){continue;}
                $ColumnMetaItem =$cc;

                $flags = $ColumnMetaItem ['flags']; // флаги в колонках
                if (in_array ( 'primary_key', $flags )){
                    $primary_key [$ColumnMetaItem ['name']] = $old_value_array [$ColumnMetaItem ['name']];
                }
                if (in_array ( 'multiple_key', $flags ) || in_array ( 'unique_key', $flags )){
                    $keys [$ColumnMetaItem ['name']] = $old_value_array [$ColumnMetaItem ['name']];
                }
                $ColumnMeta[$ColumnMetaItem ['name']]=$ColumnMetaItem;
            }

            if (count ( $primary_key ) > 0) {
                // первичный ключ есть
                $s = [];
                foreach ( $primary_key as $k => $v ){
                        $s [] =''. $k . "=" . $this->quote($v,$ColumnMeta[$k],$connect_link) ;
                }
                $s1 = [];
                foreach ( $new_value_array as $k => $v ){
                    if ($v!==$old_value_array[$k]){
                        $s1 [] = ''.$k . "=" . $this->quote($v,$ColumnMeta[$k],$connect_link);
                    }
                }
            } elseif (count ( $keys ) > 0){//обычные ключи
                $s = [];
                foreach ( $keys as $k => $v ){
                        $s [] =''. $k . "=" . $this->quote($v,$ColumnMeta[$k],$connect_link) ;
                }
                $s1 = [];
                foreach ( $new_value_array as $k => $v ){
                    if ($v!==$old_value_array[$k]){
                        $s1 [] = ''.$k . "=" . $this->quote($v,$ColumnMeta[$k],$connect_link);
                    }
                }

            } else { // первичного ключа нет и вообще нет никаких ключей, самый худший вариант
                $s = [];
                foreach ( $old_value_array as $k => $v ){
                        $s [] = ''.$k . "=" . $this->quote($v,$ColumnMeta[$k],$connect_link);
                }
                $s1 = [];
                foreach ( $new_value_array as $k => $v ){
                    if ($v!==$old_value_array[$k]){
                        $s1 [] =''. $k . "=" . $this->quote($v,$ColumnMeta[$k],$connect_link);
                    }
                }
            }
            $sql = "update " . $ColumnMetaItem ['table'] . " set " . implode ( ',', $s1 ) . " where " . implode ( ' and ', $s );
            return array ('sql' => $sql, 'values' => null, 'type' => 'update', 'sql1' => null,'primary_key_number_field'=>null );
        }
    }
/**
* экранирование строк в хапросах встроенными методами драйвера
* $str - экранируемая строка
* $ColumnMetaItem - описание колонки, то что возвращает getColumnMeta система PDO
* $connect_link - соединение с базой
* возвращает экранированную строку уже в одинарных кавычках!
* если null, int - возвращает как есть
* если float - тогда насильно меняет , на .
* строка экранируется
*/
protected function quote($str, $ColumnMetaItem,$connect_link)
{
    if (is_null($str)){
        return 'null';
    }
    /*проверим тип, если это целове число, возвращаем как есть*/
    if (in_array($ColumnMetaItem["Type"],[adTinyInt,adInteger,adBigInt,adSingle])){
        return (int)$str;
    }
    /*проверим тип, если это целове число, возвращаем как есть*/
    if (in_array($ColumnMetaItem["Type"],[adDecimal,adDouble])){
        return str_replace(",",".",(float)$str);
    }

    //экранируем строку
    return $connect_link->quote($str);
}

}
