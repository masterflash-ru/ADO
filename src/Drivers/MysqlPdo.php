<?php
/*
26.4.16 - исправлена генерация строк запросов sql, добавлены символы ` в имена колонок и таблиц

08.08.14 - исправлены ошибки связанные  обратной перемотки записей и сообтсетсвенно с граничными условиями

6.05.14 - исправлена stmt_data_seek - в части граничныного номера записи, это устранило удвоение данных

21.04.14 - добавлена проверка наличия расширения PDO, если его нет, возвращается исключение с ошибкой 18

14.02.2014 - добавлен метод get_last_insert_id() - возвращает ID вставленой записи
*/
namespace ADO\Drivers;

use ADO\Exception\ADOException;
use PDO;

class MysqlPdo {
	
	const adCmdText = 1; // текстовое определение команды/процедуры
	const adCmdTable = 2; // создать SQL запрос, который вернет все строки
	 	 	 	 	 	// указанной таблицы
	const adCmdStoredProc = 4; // хранимая процедура
	const adExecuteNoRecords = 128; // не возвращать строки, просто исполнить и все
	
	public $NamedParameters = false; // передавать только порядковые номера
	 	 	 	 	 	 	 	   // параметров, если true тогда передаются и
	 	 	 	 	 	 	 	   // имена
	private $data_type = array (); // типы данных - соответсвие между ADO и
	 	 	 	 	 	 	 	// провайдером
	private $Direction = array (); // направление переменных в параметрах
	//private $_row_number = 0; // порядковый номер строки (c учетом фильтра)
	private $attributes; // константы для получения атрибут соединения
	 	 	 	 	 	 
	// public $Filter;//фильтр от рекордсета
	
	public function __construct() {//print_r(get_loaded_extensions ());
		
		//проверим наличие драйвера PDO в системе
		if (!in_array("pdo_mysql",get_loaded_extensions ()))  throw new ADOException(NULL,18,'driver',array('PDO_MYSQL'));
		
		/*
		 * const adEmpty=0; - значение не задано. const adSmallInt=2; -
		 * двухбайтное целое со знаком. const adInteger=3; - четырёхбайтное
		 * целое со знаком. const adSingle=4; - число с плавающей запятой с
		 * одинарной точностью. const adDouble=5; - число с плавающей запятой с
		 * двойной точностью. const adCurrency=6; - денежная сумма с
		 * фиксированной точкой с четырьмя цифрами справа от десятичной точки
		 * восьмибайтное целое число со знаком;. const adError=10; - 32-битный
		 * код ошибки. const adBoolean=11; - булево значение. const
		 * adDecimal=14; - числовое значение с фиксированной точностью и
		 * масштабом. const adTinyInt=16; - однобайтное целое со знаком. const
		 * adUnsignedTinyInt=17; - однобайтное целое без знака. const
		 * adUnsignedSmallInt=18; - двухбайтное целое без знака. const
		 * adUnsignedInt=19; - четырёхбайтное целое без знака. const
		 * adBigInt=20; - восьмибайтное целое со знаком. const
		 * adUnsignedBigInt=21; - восьмибайтное целое без знака. const
		 * adBinary=128; - двоичное значение. const adChar=129; - строковое
		 * значение. const adDBDate=133; - дата формата yyyymmdd. const
		 * adDBTime=134; - время формата hhmmss. const adDBTimeStamp=135; - дата
		 * и время формата yyyymmddhhmmss плюс тысячные доли секунды. это
		 * параметр Direction объекта command /parameter const adParamUnknown=0;
		 * - направление параметра неизвестно. const adParamInput=1; - по
		 * умолчанию, входной параметр. const adParamOutput=2; - выходной
		 * параметр. const adParamInputOutput=3; - параметр представляет собой и
		 * входной, и выходной параметр
		 */
		
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
		
		$this->data_type1 ['BIT'] = adBoolean; // не работает пока
		$this->data_type1 ['TINYINT'] = adTinyInt; // не работает пока
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
		$this->attributes = array (
											"AUTOCOMMIT",
											"ERRMODE",
											"CASE",
											"CLIENT_VERSION",
											"CONNECTION_STATUS",
											"PERSISTENT",
											"SERVER_INFO",
											"SERVER_VERSION" 
											);
	
	}
	
	function connect($dsna) { /*
	   * открывает соединение с базой данных [scheme] => mysql [host] =>
	   * localhost [user] => root [pass] => vfibyf [path] => test )
	   */
		try 
			{
				//разбираем параметры в драйвер (передаются в драйвер при подключении, подробно в документации)
				$param=array();
				if (isset($dsna['query'])) 
							{
								$p=explode('&',$dsna['query']);
								foreach ($p as $v)   
										{
											$p1=explode("=",$v); 
											//если константы не существует, просто игнорируем параметр и все
											if (defined($p1[0]))	$param[ constant($p1[0])]=$p1[1];
										}
							}
				
				
				@$connect_link = new PDO ( str_ireplace ( "MysqlPdo", "mysql", $dsna ['scheme'] ) . ':dbname=' . $dsna ['path'] . ';host=' . $dsna ['host'], $dsna ['user'], $dsna ['pass'] ,$param);
				$connect_link->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		
			}
				 catch ( PDOException $e )
				 			 { 
							 // ошибка, обращаемся к обработчику ошибок соединения
							  // print_r($e);
								return array ('connect_link' => NULL, 'number' => 2, 'description' => $e->getMessage (), 'source' => $e->getFile () );
							}
		return array ('connect_link' => $connect_link, 'number' => 0, 'description' => '', 'source' => '' ); // вернуть ОК
	}
	
	public function get_server_Properties($connect_link) { // возвращает информацию (массив, ключ - имя атрибута) соединения, атрибуты
		$arr = array ();
		foreach ( $this->attributes as $val ) {
			$k = strtolower ( $val );
			$arr [$k] = $connect_link->getAttribute ( constant ( "PDO::ATTR_$val" ) );
		}
		return $arr;
	
	}
	
	public function Execute($connect_link, $commandtext = '', $Options = MysqlPdo::adCmdText, &$parameters = NULL) 
{ // выполняет запрос и возвращает объект/русурс с результатом
		
		/*
		 * $connect_link - коннект к базе //константы для метода Execute,
		 * определяют тип команды const adCmdText=1;//текстовое определение
		 * команды/процедуры const adCmdTable=2;//создать SQL запрос, который
		 * вернет все строки указанной таблицы const
		 * adCmdStoredProc=4;//хранимая процедура const
		 * adExecuteNoRecords=128;//не возвращать строки, просто исполнить и все
		 * ------------- параметры в SQL, массив объектов $parametrs: [0] =>
		 * ADO_Collection Object ( [array_item:private] => Array ( [name] => n1
		 * имя параметра [type] => 3 тип данных [direction] => direction
		 * направление переменной [size] => 10 размерность [value] => value
		 * зеначение [attributes] => атрибуты1 атрибюуты ) )
		 */


/* ключи массива error
0  	SQLSTATE error code (a five characters alphanumeric identifier defined in the ANSI SQL standard).
1 	Driver specific error code.
2 	Driver specific error messag

возвращается массив 
	'error'=>$error, см. выше
	'stmt'=>$stmt  объект с результатом, который будет обрабатывать этот же объект
	RecordsAffected=>$RecordsAffected - кеол-во затронутых рядов при операции
*/

$error = array (); // возможные ошибки
		 	 	 	 	
			
		try {
			switch ($Options) {
				
				case MysqlPdo::adCmdTable :
					{ // вернуть все строки таблицы, имя таблицы указано в строке запроса
						$stmt = $connect_link->prepare ( 'select * from ' . $commandtext, array (PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL ) ); // готовим  к исполнению
						$stmt->execute ();
						break;
					}
				
				case MysqlPdo::adCmdStoredProc :
					{ // хранимые процедуры, указывается только имя процедуры, все остальное приклеиваем мы
						$stmt = $connect_link->prepare ( 'call (' . $commandtext . ')', array (PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL ) ); // готовим к исполнению
						$stmt = $this->bindparam ( $stmt, $parameters );
						$stmt->execute ();
						break;
					}
				
				default :
					{
						// обычная текстовая команда
						$stmt = $connect_link->prepare ( $commandtext, array (PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL ) ); // готовим  к  исполнению
						$stmt = $this->bindparam ( $stmt, $parameters );
						//echo $commandtext.'<br>';
						//echo "<br>";
						$stmt->execute ();
						
						
						//echo "<br> , COUNT= ";

						break;
					}
			}
		} 		// try {
		catch ( PDOException $e ) 
			{
			$stmt->_row_number = 0;
			$RecordsAffected = 0;
			// $error=$e;
			$stmt->errorInfo = $stmt->errorInfo ();
			// print_r($stmt->errorInfo);
			return array (
								'error' => $e, 			// объект PDOException
								'stmt' => $stmt, 			// результат выборки, его будем обрабатывать
				 	 	 	   // 'stmt_dop'=>clone ($stmt), //дубликат результата выборки
								'RecordsAffected' => 0 
								);
		}
		
		$stmt->_row_number = 1;
		$RecordsAffected = $stmt->rowCount ();
		$error = NULL; // $stmt->errorInfo();
		//echo $RecordsAffected." ";
		/*
		 * Ошибки - массив из 3-х элементов: 0 SQLSTATE error code (a five
		 * characters alphanumeric identifier defined in the ANSI SQL standard).
		 * 1 Driver specific error code. 2 Driver specific error message.
		 */
		// $stmt->v=microtime();
		// var_dump($stmt);
		return array (
								'error' => NULL,
								'stmt' => $stmt, 		// результат выборки, его будем обрабатывать
				 	 	 	   // 'stmt_dop'=>clone ($stmt), //дубликат результата выборки
								'RecordsAffected' => $RecordsAffected 
							);
	
}
	
	public function stmt_data_seek($stmt, $row_number)
	 {
		// перемещает указатель на запись номер $row_number (1......)
		// переход вперед?
		 //echo "row_number=$row_number \n";
		// var_dump($stmt->_row_number);

		// переход назад?
		if ($row_number < $stmt->_row_number && $row_number>0 || ! isset ( $stmt->_row_number )) 
			{//echo "row_number=$row_number ";//if ($row_number==13)throw new Exception("stop");
					$stmt->_row_number = 1;
					// вначале переходим в начало и потом переходим на нужную позицию
					$stmt->execute (); // заново выполнить запрос
					for($i =1; $i < $row_number; $i ++) 
						{
							$this->fetchNext ( $stmt ); // считать запись, и перемотать указатель, если больше одной записи
						}
			}
		if ($row_number > $stmt->_row_number) 
				{
				// перемотать указатель на нужное положение вперед след. запись
				// будет готова для чтения
				$c = $row_number - $stmt->_row_number;
				for($i = 0; $i < $c; $i ++)	$this->fetchNext ( $stmt ); // считать запись
				}

	}
	
public function columnCount($stmt) 
	{ // возвращает кол-во колонок в результате выборки
	return $stmt->columnCount ();
	}
	
	public function create_sql_update($stmt, $old_value_array = array(), $new_value_array = array(), $status = array('flag_change'=>false,'flag_new'=>false,'flag_delete'=>false)) 
	{ /*
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
		// print_r($old_value_array);
		// print_r($new_value_array);
		// print_r( $status);
		if ($status ['flag_new']) 
				{ // создание новой записи - ассоциированый массив
				//$ColumnMeta = $stmt->getColumnMeta ( 0 ); // получим описание таблицы/колонки 
				//print_r(array_keys($new_value_array));
				$s1 = array ();$primary_key_number_field=NULL;
				$i=0;
				foreach ( $new_value_array as $k => $v )
					{
						if (is_null ( $v )) $s1 [] = '`'.$k . "`=null";
							else	$s1 [] = "'" . addslashes ( $v ) . "'"; // print_r(implode(",",$s1));
					
					//получить описание полей и проверить на primary_key
					$ColumnMeta = $stmt->getColumnMeta ( $i);
					$flags = $ColumnMeta ['flags']; // флаги в колонках
					if (in_array ( 'primary_key', $flags )) 	$primary_key_number_field=$i; //есть первичный ключ, запомним его номер в списке полей
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
		
		if ($status ['flag_change'])
			 { // измненение существующей записи
				$column_count = count ( $old_value_array ); // кол-во колонок

				$primary_key = array (); // хранит массив имен полей которые являются первичными ключами
				for($i = 0; $i < $column_count; $i ++) 
						{ // пробежим по колонкам и поищем первичный ключ
							$ColumnMeta = $stmt->getColumnMeta ( $i );
							$flags = $ColumnMeta ['flags']; // флаги в колонках
							if (in_array ( 'primary_key', $flags )) 	$primary_key [$ColumnMeta ['name']] = $old_value_array [$ColumnMeta ['name']];
						}
			if (count ( $primary_key ) > 0) 
					{ // первичный ключ есть
						$s = array ();
						foreach ( $primary_key as $k => $v )
								if (is_null ( $v ))$s [] ='`'. $k . "`=null";
										else	$s [] ='`'. $k . "`='" . addslashes ( $v ) . "'"; // print_r($s);
						$s1 = array ();
						foreach ( $new_value_array as $k => $v )
								if (is_null ( $v )) $s1 [] = '`'.$k . "`=null";
										else	$s1 [] = '`'.$k . "`='" . addslashes ( $v ) . "'"; // print_r($s1);
					} 
					else
					 { // первичного ключа нет
						$s = array ();
						foreach ( $old_value_array as $k => $v )
								if (is_null ( $v )) $s [] ='`'.$k . "`=null";
									else	$s [] = '`'.$k . "`='" . addslashes ( $v ) . "'";
						$s1 = array ();
						foreach ( $new_value_array as $k => $v )
								if (is_null ( $v ))$s1 [] = '`'.$k . "`=null";
									else $s1 [] ='`'. $k . "`='" . addslashes ( $v ) . "'";
					}

			$sql = "update `" . $ColumnMeta ['table'] . "` set " . implode ( ',', $s1 ) . " where " . implode ( ' and ', $s );
			$v = NULL;
			return array ('sql' => $sql, 'values' => $v, 'type' => 'update', 'sql1' => NULL,'primary_key_number_field'=>NULL );
		}
		
	if ($status ['flag_delete']) 
		{ // удаление существующей записи
			$column_count = count ( $old_value_array ); // кол-во колонок

			$primary_key = array (); // хранит массив имен полей которые являются первичными ключами
			for($i = 0; $i < $column_count; $i ++) 
				{ // пробежим по колонкам и поищем первичный ключ
					$ColumnMeta = $stmt->getColumnMeta ( $i );
					$flags = $ColumnMeta ['flags']; // флаги в колонках
					if (in_array ( 'primary_key', $flags )) 	$primary_key [$ColumnMeta ['name']] = $old_value_array [$ColumnMeta ['name']];
				}
			if (count ( $primary_key ) > 0) 
				{ // первичный ключ есть
				$s = array ();
				foreach ( $primary_key as $k => $v )
					if (is_null ( $v )) $s [] ='`'. $k . "`=null";
								else	$s [] = '`'.$k . "`='" . addslashes ( $v ) . "'"; // print_r($s);
				// $s1=array();
				// foreach
				// ($new_value_array
				// as
				// $k=>$v)if
				// (is_null($v))
				// $s1[]=$k."=null";
				// else
				// $s1[]=$k."='".addslashes($v)."'";//print_r($s1);
				} 
				else 
					{ // первичного ключа нет
						$s = array ();
						foreach ( $old_value_array as $k => $v )
								if (is_null ( $v )) $s [] = '`'.$k . "`=null";
											else $s [] ='`'. $k . "`='" . addslashes ( $v ) . "'";
						// $s1=array();
						// foreach ($new_value_array as $k=>$v)if (is_null($v))
						// $s1[]=$k."=null"; else $s1[]=$k."='".addslashes($v)."'"; 
					}
		$sql = "delete from `" . $ColumnMeta ['table'] . "` where " . implode ( ' and ', $s ); // echo $sql;
		$v = NULL;
		return array ('sql' => $sql, 'values' => $v, 'type' => 'delete', 'sql1' => NULL ,'primary_key_number_field'=>NULL );
		}
	
	}

public function get_last_insert_id($db_connect)
{
		// получить ID вставлено записи
		return $db_connect->lastInsertId(); 
}

	
public function Close() 
{
		// закрыть соединение с базой
}
	
public function loadColumnMeta($stmt, $col)
 { // получение записи
		/*
		 * $stmt - объект с результатом запроса $field экземпляр объекта Field,
		 * его и возвращает функция заполненым, атрибуты поля запполняются
		 * только при $index=0 $col - номер колонки 0...
		 */
		
		// получить описания поля и заполнить
$ColumnMeta = $stmt->getColumnMeta ( $col );
		// print_r($ColumnMeta);
		
		// $ColumnMeta['Type']=array_search
		// ($ColumnMeta['pdo_type'],$this->data_type);//тип, нужно
		// конвертировать в стандарт ADO
	if (isset ( $ColumnMeta ['native_type'] ) && isset ( $this->data_type1 [$ColumnMeta ['native_type']] ))
			$ColumnMeta ['Type'] = $this->data_type1 [$ColumnMeta ['native_type']];
		else
			$ColumnMeta ['Type'] = adEmpty;
			
// функция getColumnMeta эксперементальная, сейчас не определяет тип
// BLOB, насильно проверим и вставми тип, остальные типы не так критичны
// конечно все через задницу, но нужно для экспорта в XML, по этому
// типу производится кодировка в base64
// if (isset($ColumnMeta['native_type']) &&
// strtolower($ColumnMeta['native_type']) == 'blob')
// $ColumnMeta['Type']=adBinary;
		
$ColumnMeta ['NumericScale'] = $ColumnMeta ['precision']; // точность чисел
$ColumnMeta ['DefinedSize'] = NULL; // установленная максимальная размерность
		 	 	 	 	 	 	 	 	 // поля
		 	 	 	 	 	 	 	 	  //print_r($ColumnMeta);
		
		return $ColumnMeta;
	
	}
	
public function fetchFirst($stmt) 
{ // получить строку 0
		/*
		 * $stmt - объект с результатом запроса BOF - true если указатель
		 * указывает в начало (т.е. сразу после исполнения запроса) возвращается
		 * ассоциативный массив
		 */
		// echo "запрос в БД fetchFirst\n";
$stmt->_row_number = 1;
$stmt->execute (); // если первая строка тогда заново исполнить, что бы перемотать указатель в начало
return $stmt->fetch ( PDO::FETCH_NUM );
}
	
public function fetchNext($stmt) 
{
	$rez=array();
	 // получить след. строку
		/*
		 * $stmt - объект с результатом запроса возвращается ассоциативный
		 * массив
		 */

// var_dump($stmt);

//игнорировать ошибку, если запрос вообще ничего не возвращает
//try
//{
	$rez = $stmt->fetch ( PDO::FETCH_NUM );//print_r($rez);
	$stmt->_row_number ++;
//} catch (PDOException $e){}
// print_r($rez);
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



private function bindparam($stmt, &$parameters) 
{ // разбирает по параметрам
		/*
		 * для внутренних нужд параметры в SQL, массив объектов $parametrs: [0]
		 * => ADO_Parameter Object ( [array_item:private] => Array ( [Name] =>
		 * n1 имя параметра [Type] => 3 тип данных [Direction] => direction
		 * направление переменной [Size] => 10 размерность [value] => value
		 * зеначение [Attributes] => атрибуты1 атрибюуты ) ) возвращает объект с
		 * результатом
		 */

if (is_object ( $parameters ))
	{ // print_r($parameters);
		if ($this->NamedParameters)
				foreach ( $parameters as $k => &$v ) 
						{ // нумерация начинается с 1, поправим индекс +1
					  // echo $v->name."=".$v->value."\n";
						$stmt->bindParam ( ":" . $v->Name, $v->Value, $this->data_type [$v->Type] or $this->Direction, $v->Size );
						}
			else
				foreach ( $parameters as $k => $v ) 
					{ // нумерация начинается с 1, поправим индекс +1
					  // echo $v->Name."=".$v->Value."\n";
						$stmt->bindParam ( $k + 1, $v->Value, $this->data_type [$v->Type] or $this->Direction, $v->Size );
					}
		}
return $stmt;
}





}
?>