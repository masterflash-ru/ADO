<?php
/*03.04.2013
введено клонирование объектов, теперь RS клониуется со всеми потрохами
*/

namespace ADO\Entity;

class DataColumns implements \IteratorAggregate // Iterator
{ // объект для генерации коллекций
 	
 	public $count = 0; // кол-во элементов
 	private $position = 0;

 	public $Item = array(); // массив объектов числовой
 	public $Item_text = array(); // массив объектов ассоциативный
 	
 	public function __construct ()
 	{
 	 	$this->count = 0;
 	 	$this->Item = array();
 	 	$this->Item_text = array();
 	}
 	
 	/*
 	 * public function Append($arr=array()) {//добавить в коллекцию $item=new
 	 * DataColumn(); if (is_array($arr)) {//свой-ва это ключи массива foreach
 	 * ($arr as $k=>$v) { $item->$k=$v; } }
 	 * $this->Item[$this->count]=$item;//записать в виде объекта
 	 * $this->Item_text[$Name]=$item;//записать в виде объекта $this->count++; }
 	 */
 	
 	public function Add (DataColumn $Item)
 	{ // добавить в коллекцию
 	 	$this->Item[$this->count] = $Item; // записать в виде объекта
 	 	$this->Item_text[$Item->ColumnName] = $Item; // записать в виде объекта
 	 	$this->count ++;
 	}

 	public function Delete ($index)
 	{ // удалить элемент из коллекции
 	 	unset($this->Item_text[$this->Item[$index]->ColumnName]);
 	 	unset($this->Item[$index]);
 	}

 	public function Item ($index = 0)
 	{ // получить жлемент из коллекции, возвращается объект
 	 	return $this->Item[$index];
 	}

 	public function getIterator ()
 	{
 	 	return new ArrayIterator($this->Item);
 	}
function __clone()
    {
		//принудительно копируем объект
		foreach ($this->Item as $i=>$Item)
			{
				$this->Item[$i] = clone $this->Item[$i];
			}

		//принудительно копируем объект
		foreach ($this->Item_text as $i=>$Item)
			{
				$this->Item_text[$i] = clone $this->Item_text[$i];
			}
	}
}
