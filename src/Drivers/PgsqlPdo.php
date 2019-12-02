<?php
/*
*/
namespace ADO\Drivers;

use ADO\Exception\ADOException;
use PDO;


class PgsqlPdo extends AbstractPdo
{
	protected $pg_meta_cache;
    
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
    
    
/*
получить метаданные поля
$stmt - резкльтат запроса PDO
$col - номер колонки 0...
$connect_link - соединение с базой нужно что бы считать из схемы наличие ключей таблицы
*/	
public function loadColumnMeta($stmt, $col,$connect_link=null)
 {
    //возможно 2 типа обращения, с соединением для извлечения ключенй и без, просто метаданные полей
    if (empty($connect_link)){
        $cl=1;
    } else {
        $cl=0;
    }
    if (isset($stmt->ColumnMeta[$cl][$col])){
        return $stmt->ColumnMeta[$cl][$col];
    }

    // получить описания поля и заполнит
    $ColumnMeta =$this->getPgMeta($stmt, $col,$connect_link); 
    
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
    $stmt->ColumnMeta[$cl][$col]=$ColumnMeta;

    return $ColumnMeta;
}

/*
* получение ключей для таблицы из недр postgresql
*/
protected function getPgMeta($stmt, $col,$connect_link=null)
{
    $ColumnMeta =$stmt->getColumnMeta ($col);
    $ColumnMeta["flags"]=[];
    if (empty($connect_link)){
        return $ColumnMeta;
    }
    if (!isset($this->pg_meta_cache[$ColumnMeta["table"]])){
        $rez=[];
        if (!empty($ColumnMeta["table"])){
            //читаем наличие ключенй
            $st=$connect_link->prepare("SELECT a.attname, format_type(a.atttypid, a.atttypmod) AS data_type,i.indisprimary, a.attnotnull
                    FROM   pg_index i
                    JOIN   pg_attribute a ON a.attrelid = i.indrelid
                    AND a.attnum = ANY(i.indkey)
                    WHERE  i.indrelid = ?::regclass",[$ColumnMeta["table"]]);
            $st->execute([$ColumnMeta["table"]]);
            while ($row = $st->fetch(PDO::FETCH_ASSOC)){
                //перебираем и добавляем флаги
                if ($row["indisprimary"]){
                    $rez[$row["attname"]][]="primary_key";
                } else {
                    $rez[$row["attname"]][]="multiple_key";
                }
                if ($row["attnotnull"]){
                    $rez[$row["attname"]][]="not_null";
                }
            }
            //пишем в кеш, что бы не читать много раз одно и то же
            $this->pg_meta_cache[$ColumnMeta["table"]]=$rez;
        }
    }
    if (isset($this->pg_meta_cache[$ColumnMeta["table"]][$ColumnMeta["name"]])){
        //если имеется для данного поля флаги - добавим их иначе нисчего
        $ColumnMeta["flags"]=$this->pg_meta_cache[$ColumnMeta["table"]][$ColumnMeta["name"]];
    }
    return $ColumnMeta;
}
}
