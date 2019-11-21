<?php
namespace ADO\Collection;

use ArrayIterator;
use IteratorAggregate;

class Parameters implements IteratorAggregate
{     
     public $count = 0;
     private $position = 0;

     public $Item = [];
     
     public function __construct ()
     {
          $this->count = 0;
          $this->Item = [];
     }

     public function Append ($item, $index = NULL)
     {
          if (is_null($index)){
              $index = $this->count;
          }
          if (! is_object($item)){
              return false;
          }
          $this->Item[$index] = $item;
          $this->count ++;
     }

     public function Delete ($index)
     {
          $name = $this->Item[$index]->name;
          if ($name){
              unset($this->Item[$name]);
          }
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

}

