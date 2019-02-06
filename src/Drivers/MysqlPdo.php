<?php
/*
* драйвер для соединения с MySql посредством PDO
*/
namespace ADO\Drivers;

use ADO\Exception\ADOException;
use PDO;

class MysqlPdo extends AbstractPdo
{


    public function __construct()
    {
        //проверим наличие драйвера PDO в системе
        if (!in_array("pdo_mysql",get_loaded_extensions ())){
            throw new ADOException(NULL,18,'driver',array('PDO_MYSQL'));
        }
        parent::__construct();
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
    public function create_sql_update($connect_link,$stmt, array $old_value_array = [], array $new_value_array = [], array $status =['flag_change'=>false,'flag_new'=>false,'flag_delete'=>false]) 
    { 
        if ($status ['flag_new']) { // создание новой записи - ассоциированый массив
            $s1 = [];
            $primary_key_number_field=NULL;
            $i=0;
            foreach ( $new_value_array as $k => $v ){
                //получить описание полей и проверить на primary_key
                $ColumnMetaItem =$this->loadColumnMeta($stmt, $i);
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
            $sql_start = "insert into `" . $ColumnMetaItem ['table'] . "` (`" . implode ( '`,`', array_keys ( $new_value_array ) ) . "`) values ";
            $sql = $sql_start . $v;
            return array ('sql' => $sql, 'values' => $v, 'type' => 'insert', 'sql1' => $sql_start ,'primary_key_number_field'=>$primary_key_number_field);
        }
        
        if ($status ['flag_change']){
            // измненение существующей записи
            $column_count = count ( $old_value_array ); // кол-во колонок
            $primary_key = []; // хранит массив имен полей которые являются первичными ключами
            $ColumnMeta=[];
            for($i = 0; $i < $column_count; $i ++) {
                // пробежим по колонкам и поищем первичный ключ
                $ColumnMetaItem =$this->loadColumnMeta($stmt, $i);
                $flags = $ColumnMetaItem ['flags']; // флаги в колонках
                if (in_array ( 'primary_key', $flags )){
                    $primary_key [$ColumnMetaItem ['name']] = $old_value_array [$ColumnMetaItem ['name']];
                }
                $ColumnMeta[$ColumnMetaItem ['name']]=$ColumnMetaItem;
            }

            if (count ( $primary_key ) > 0) {
                // первичный ключ есть
                $s = [];
                foreach ( $primary_key as $k => $v ){
                        $s [] ='`'. $k . "`=" . $this->quote($v,$ColumnMeta[$k],$connect_link) ;
                }
                $s1 = [];
                foreach ( $new_value_array as $k => $v ){
                        $s1 [] = '`'.$k . "`=" . $this->quote($v,$ColumnMeta[$k],$connect_link);
                }
            } else { // первичного ключа нет
                $s = [];
                foreach ( $old_value_array as $k => $v ){
                        $s [] = '`'.$k . "`=" . $this->quote($v,$ColumnMeta[$k],$connect_link);
                }
                $s1 = [];
                foreach ( $new_value_array as $k => $v ){
                        $s1 [] ='`'. $k . "`=" . $this->quote($v,$ColumnMeta[$k],$connect_link);
                }
            }
            $sql = "update `" . $ColumnMetaItem ['table'] . "` set " . implode ( ',', $s1 ) . " where " . implode ( ' and ', $s );
            $v = NULL;
            return array ('sql' => $sql, 'values' => $v, 'type' => 'update', 'sql1' => NULL,'primary_key_number_field'=>NULL );
        }
        
        if ($status ['flag_delete']) {
            // удаление существующей записи
            $column_count = count ( $old_value_array ); // кол-во колонок
            $ColumnMeta=[];
            $primary_key = []; // хранит массив имен полей которые являются первичными ключами
            for($i = 0; $i < $column_count; $i ++) {
                // пробежим по колонкам и поищем первичный ключ
                $ColumnMetaItem =$this->loadColumnMeta($stmt, $i);
                $flags = $ColumnMetaItem ['flags']; // флаги в колонках
                if (in_array ( 'primary_key', $flags )) {
                    $primary_key [$ColumnMetaItem['name']] = $old_value_array [$ColumnMetaItem ['name']];
                }
                $ColumnMeta[$ColumnMetaItem['name']]=$ColumnMetaItem;
            }
            if (count ( $primary_key ) > 0) {
                // первичный ключ есть
                $s = [];
                foreach ( $primary_key as $k => $v ){
                    $s[] ='`'. $k . "`=" . $this->quote($v,$ColumnMeta[$k],$connect_link);                
                }
            } else {
                // первичного ключа нет
                $s = [];
                foreach ( $old_value_array as $k => $v ){
                    $s[] ='`'. $k . "`=" . $this->quote($v,$ColumnMeta[$k],$connect_link);
                }
            }
            $sql = "delete from `" . $ColumnMetaItem ['table'] . "` where " . implode ( ' and ', $s );
            $v = NULL;
            return array ('sql' => $sql, 'values' => $v, 'type' => 'delete', 'sql1' => NULL ,'primary_key_number_field'=>NULL );
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