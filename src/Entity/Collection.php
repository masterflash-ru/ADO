<?php

namespace ADO\Entity;

class Collection
{ // объект - элемент коллекции
    private $array_item = []; // массив перегруженных объектов
    
    public function __construct ($arr)
    {
        if (is_array($arr) || is_object($arr)){
            foreach ($arr as $k => $v){
                $this->array_item[strtolower($k)] = $v; // сохраняем данные
            }
        } else {
            return false;
        }
    }

    function __get ($nm)
    {
        return @$this->array_item[strtolower($nm)];
    }

    function __set ($nm, $val)
    {
        $this->array_item[strtolower($nm)] = $val;
    }

    public function __toString ()
    {
        return @$this->array_item['name'];
    }
}
