<?php

namespace ADO\Entity;
use ArrayIterator;
use IteratorAggregate;

class DataColumns implements IteratorAggregate // Iterator
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
            foreach ($this->Item as $i=>$Item)  {
                $this->Item[$i] = clone $this->Item[$i];
            }

            //принудительно копируем объект
            foreach ($this->Item_text as $i=>$Item) {
                $this->Item_text[$i] = clone $this->Item_text[$i];
            }
    }
}
