<?php

namespace ADO\Collection;
use ArrayIterator;
use IteratorAggregate;
use ADO\Entity\DataColumn;

class DataColumns implements IteratorAggregate 
{ 
     public $count = 0;
     private $position = 0;

     public $Item = [];
     public $Item_text = [];
     
     public function __construct ()
     {
          $this->count = 0;
          $this->Item = [];
          $this->Item_text = [];
     }
     
     
     public function Add (DataColumn $Item)
     {
          $this->Item[$this->count] = $Item; // записать в виде объекта
          $this->Item_text[$Item->ColumnName] = $Item; // записать в виде объекта
          $this->count ++;
     }

     public function Delete ($index)
     {
          unset($this->Item_text[$this->Item[$index]->ColumnName]);
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
            foreach ($this->Item as $i=>$Item)  {
                $this->Item[$i] = clone $this->Item[$i];
            }
            foreach ($this->Item_text as $i=>$Item) {
                $this->Item_text[$i] = clone $this->Item_text[$i];
            }
    }
}
