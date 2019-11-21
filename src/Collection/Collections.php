<?php
/**
* коллекция общего применения
*/

namespace ADO\Collection;

use ADO\Entity\Collection;
use IteratorAggregate;
use ArrayIterator;

class Collections implements IteratorAggregate 
{ // объект для генерации коллекций
	
	public $count = 0; // кол-во элементов
	private $position = 0;

	public $Item = []; // массив объектов числовой
	
	public function __construct ()
	{
		$this->count = 0;
		$this->Item = [];
	}

public function add ($item, $index = NULL)
	{ // добавить в коллекцию
	  // сообщение об ошибке
		/*
		 * 0-й элемент это собственно объект/массив 1-й - индекс (номер)
		 */
    if (is_null($index)){
        $index = $this->count; // порядковый номер
    }
    if (! is_object($item)) {
        $item = new Collection($item);
    } else {
        return false;
    }
    $this->Item[$index] = $item; // записать в виде объекта
    $this->count ++;
	}

public function Remove ($index)
	{ // удалить элемент из коллекции
		$name = $this->Item[$index]->name;
		if ($name) {
            unset($this->Item[$name]);
        }
		unset($this->Item[$index]);
	}

	public function Item ($index = 0)
	{ // получить жлемент из коллекции,
	  // возвращается объект
		return $this->Item[$index];
	}

	public function getIterator ()
	{
		return new ArrayIterator($this->Item);
	}

}
