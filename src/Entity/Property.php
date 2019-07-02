<?php

namespace ADO\Entity;


class Property
{ // объект - элемент коллекции
     public $Name; // имя параметра
     public $Type; // тип данных
     public $Value; // зеначение
     public $Attributes; // атрибюуты ЗАРЕЗЕРВИРОВАНО
     
     public function __construct ($Name = null, $Type = null, $Value = null,     $Attributes = null)
     {
          $this->Name = $Name;
          $this->Type = $Type;
          $this->Value = $Value;
          $this->Attributes = $Attributes;
     
     }

}

