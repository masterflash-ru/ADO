<?php

namespace ADO\Entity;


class Property
{ // объект - элемент коллекции
 	public $Name; // имя параметра
 	public $Type; // тип данных
 	public $Value; // зеначение
 	public $Attributes; // атрибюуты ЗАРЕЗЕРВИРОВАНО
 	
 	public function __construct ($Name = NULL, $Type = NULL, $Value = NULL, 	$Attributes = NULL)
 	{
 	 	$this->Name = $Name;
 	 	$this->Type = $Type;
 	 	$this->Value = $Value;
 	 	$this->Attributes = $Attributes;
 	
 	}

}

?>