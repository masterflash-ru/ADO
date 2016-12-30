<?php
namespace ADO\Entity;

// ---------------------------------------------------------------------------

class Parameter
{ // объект - элемент коллекции
 	public $Name; // имя параметра
 	public $Type; // тип данных
 	public $Direction; // направление переменной
 	public $Size; // размерность
 	public $Value; // зеначение
 	
 	/*
 	 * const adParamSigned=16;// - параметр принимает значения со знаком. const
 	 * adParamNullable=64;// - параметр принимает пустые значения. const
 	 * adParamLong=128;// - параметр принимает двоичные данные.
 	 */
 	public $Attributes; // атрибюуты см. выше
 	public $Precision; // стиепень точности для числовых значений
 	public $NumericScale = 2; // кол-во знаков после зяпятой в числах

}
