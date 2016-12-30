<?php
/*03.04.2013
введено клонирование объектов, теперь RS клониуется со всеми потрохами
*/
namespace ADO\Entity;


class Field
{ // объект - элемент коллекции
 	private $parent_recordset; // объект родительсмкого рекордсета
 	// public $DateFormat;//формат даты
 	 	 	 	 	 	 	   
 	// перегруженное сво-ва
 	private $container = array('name' => NULL, 	 // имя параметра
 	'originalvalue' => NULL, 	 // значение поля до каких-либо изменений, т.е.  старое  значение после изменения
 	'value' => NULL, 	 // зеначение
 	'definedsize' => NULL, 	 // максимальный размер поля
 	'type' => NULL, 	 // тип данных
 	'precision' => NULL, 	 // стиепень точности для числовых значений
 	'numericscale' => NULL, 	 // кол-во знаков после зяпятой в числах
 	'actualsize' => NULL); 	// фактический размер поля  'status'=>array('new'=>array(),'old'=>array())  //хранит флаги тек.записи
 	 // массив сперегруженными сво-вами
 	
 	public function __construct ($arr = array())
 	{
 	 	if (isset($arr['name']))
 	 	 	$this->container['name'] = $arr['name'];
 	 	else
 	 	 	$this->container['name'] = NULL;
 	 	$this->container['value'] = NULL;
 	 	if (isset($arr['len']))
 	 	 	$this->container['actualsize'] = $arr['len'];
 	 	else
 	 	 	$this->container['actualsize'] = NULL;
 	 	if (isset($arr['DefinedSize']))
 	 	 	$this->container['definedsize'] = $arr['DefinedSize'];
 	 	else
 	 	 	$this->container['definedsize'] = NULL;
 	 	if (isset($arr['Type']))
 	 	 	$this->container['type'] = $arr['Type'];
 	 	else
 	 	 	$this->container['type'] = NULL;
 	 	if (isset($arr['precision']))
 	 	 	$this->container['precision'] = $arr['precision'];
 	 	else
 	 	 	$this->container['precision'] = NULL;
 	 	if (isset($arr['NumericScale']))
 	 	 	$this->container['numericscale'] = $arr['NumericScale'];
 	 	else
 	 	 	$this->container['numericscale'] = NULL;
 	 	// $this->DateFormat=$arr['DateFormat'];
 	}
 	
 	/*
 	 * public function __destruct() { echo "Field удален"	; }
 	 */
 	
 	// ************************** перегрузка
 	public function &__get ($var)
 	{
 	 	$var = strtolower($var);
 	 	// проверим к какой ппеременной обращается
 	 	if (array_key_exists($var, $this->container))
 	 	 	return $this->container[$var];
 	 	$arr = debug_backtrace();
 	 	trigger_error(
 	 	 	 	"Undefined property: Field::\$$var in " . $arr[0]['file'] .
 	 	 	 	 	 	 " on line " . $arr[0]['line'], E_USER_WARNING);
 	 	// return $this->container['name'];//вернуть хоть что-либо
 	 	return $var;
 	}

 	public function __set ($var, $value)
 	{
 	 	$var = strtolower($var);
 	 	if (! array_key_exists($var, $this->container)) {
 	 	 	$arr = debug_backtrace();
 	 	 	trigger_error(
 	 	 	 	 	"Undefined property: Field::\$$var in " . $arr[0]['file'] .
 	 	 	 	 	 	 	 " on line " . $arr[0]['line'], E_USER_WARNING);
 	 	}
 	 	$this->container[$var] = $value; // проверим на допустимость  смотрим что меняем, если value  (значение), то обратимся в  рекордсет и  изменим значение во внутреннем  массиве
 	 	if ($var == 'value')
 	 	 	$this->parent_recordset->change_value($this);
 	
 	}

 	public function __call ($name, $var)
 	{ // диспетчер служебных функций
 	 	if ($name == 'set_parent_recordset') {
 	 	 	$this->parent_recordset = $var[0];
 	 	 	return;
 	 	}
 	 	if ($name == 'set_value') {
 	 	 	$this->container['value'] = $var[0];
 	 	 	return;
 	 	}
 	 	echo 'Metod ' . $name . " is not found in Field object!\n";
 	
 	}
 	
 	// ************************* конец перегрузки

}
