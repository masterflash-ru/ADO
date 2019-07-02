<?php
namespace ADO\Entity;
use IteratorAggregate;
use ArrayIterator;

class Fields implements IteratorAggregate // Iterator
{ // объект для генерации коллекций
     
     public $count = 0; // кол-во элементов
     private $position = 0;

     public $Item = []; // массив объектов числовой
     
     public function __construct ()
     {
          $this->count = 0;
          $this->Item = [];
     }

     public function Append ($Name, $Type, $DefinedSize)
     { // добавить в коллекцию
          $item = new Field();
          $item->Name = $Name;
          $item->Type = $Type;
          $item->DefinedSize = $DefinedSize;
          $this->Item[$this->count] = $item; // записать в виде объекта
          $this->Item[$Name] = $item; // записать в виде объекта
          $this->count ++;
     }

     public function Add (Field $Item)
     { // добавить в коллекцию
          $this->Item[$this->count] = $Item; // записать в виде объекта
          $this->Item[$Item->Name] = $Item; // записать в виде объекта
          $this->count ++;
     }

     public function Delete ($index)
     { // удалить элемент из коллекции
          unset($this->Item[$this->Item[$index]->Name]);
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
    function __clone()
        {
            //принудительно копируем объект Field
            foreach ($this->Item as $i=>$Item) {
                if (is_numeric($i)) {
                    $this->Item[$i] = clone $this->Item[$i];
                    $this->Item[$this->Item[$i]->Name]=$this->Item[$i];
                }
            }

        }

}

