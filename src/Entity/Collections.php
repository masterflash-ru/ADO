<?php

namespace ADO\Entity;

use ADO\Entity\Collection;


class Collections implements \IteratorAggregate // Iterator
{ // объект для генерации коллекций
	
	public $count = 0; // кол-во элементов
	private $position = 0;

	public $Item = array(); // массив объектов числовой
	
	public function __construct ()
	{
		$this->count = 0;
		$this->Item = array();
	}

public function add ($item, $index = NULL)
	{ // добавить в коллекцию
	  // сообщение об ошибке
		/*
		 * 0-й элемент это собственно объект/массив 1-й - индекс (номер)
		 */
		if (is_null($index))
			$index = $this->count; // порядковый номер
		if (! is_object($item)) {
			$item = new Collection($item);
		} else
			return false;
		$this->Item[$index] = $item; // записать в виде объекта
		
		/*
		 * if ($item->name!=NULL) {//если есть ключ с именем name, записать
		 * отдельно еще $this->Item[$item->name]=&$this->Item[$index];//echo
		 * '*'; }
		 */
		$this->count ++;
	}

public function Remove ($index)
	{ // удалить элемент из коллекции
		$name = $this->Item[$index]->name;
		if ($name)
			unset($this->Item[$name]);
		unset($this->Item[$index]);
	}

	public function Item ($index = 0)
	{ // получить жлемент из коллекции,
	  // возвращается объект
		return $this->Item[$index];
	}

	public function getIterator ()
	{
		return new \ArrayIterator($this->Item);
	}

}
