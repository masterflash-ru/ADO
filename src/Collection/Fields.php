<?php
namespace ADO\Collection;
use IteratorAggregate;
use ArrayIterator;
use ADO\Entity\Field;

class Fields implements IteratorAggregate
{
     
     public $count = 0;
     private $position = 0;

     public $Item = [];
     
     public function __construct ()
     {
          $this->count = 0;
          $this->Item = [];
     }

     public function Append ($Name, $Type, $DefinedSize)
     {
          $item = new Field();
          $item->Name = $Name;
          $item->Type = $Type;
          $item->DefinedSize = $DefinedSize;
          $this->Item[$this->count] = $item; // записать в виде объекта
          $this->Item[$Name] = $item; // записать в виде объекта
          $this->count ++;
     }

     public function Add (Field $Item)
     {
          $this->Item[$this->count] = $Item; // записать в виде объекта
          $this->Item[$Item->Name] = $Item; // записать в виде объекта
          $this->count ++;
     }

     public function Delete ($index)
     {
          unset($this->Item[$this->Item[$index]->Name]);
          unset($this->Item[$index]);
     }

     public function Item ($index = 0)
     {
          return $this->Item[$index];
     }

     public function getIterator ()
     {
          return new ArrayIterator($this->Item);
     }
    function __clone()
        {
            foreach ($this->Item as $i=>$Item) {
                if (is_numeric($i)) {
                    $this->Item[$i] = clone $this->Item[$i];
                    $this->Item[$this->Item[$i]->Name]=$this->Item[$i];
                }
            }

        }
}

