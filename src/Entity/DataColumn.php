<?php
/*03.04.2013
введено клонирование объектов, теперь RS клониуется со всеми потрохами
*/

namespace ADO\Entity;

/*
 * Лбъект DataColumn - описатель всей колонки таблицы имеет только свойства,
 * которые заполняются объектом RecordSet в момент открытия все св-ва доступны
 * для измнения
 */

class DataColumn
{

 	public $AllowDbNull = true; // равно true если столбец может содержать NULL
 	public $AutoIncrement; // равно true если поле автоинкрементное
 	public $AutoIncrementSeed; // начальное значение для инкрементного поля
 	public $AutoIncrementStep; // шаг инкремента
 	public $Caption; // заголовок столбца (имя колонки таблицы)
 	public $ColumnMapping; // управляет соотвествие вывода данных в XML  (значение  или в виде атрибута)
 	public $ColumnName; // имя колонки таблицы
 	public $DataType; // тип данных в колонке
 	public $DefaultValue; // значение по умолчанию колонки public $Expression;
 	public $Table; // имя таблицы которому принадлежит данная колонка таблицы
 	public $MaxLength; // максимальная длинна текста
 	public $NameSpace; // простарнство имен для колонки
 	public $Ordinal; // порядковый номер колонки
 	public $Prefix; // XML -префикс
 	public $ReadOnly = false; // возвращает true если данные объекта только для чтения
 	public $Unique; // true - если данные уникальны в колонке
 	
 	public function __construct ($columnName, $dataType = NULL, $expr = NULL, 
 	 	 	$MappingType = NULL)
 	{ /* 
	 $columnName  -  имя  колонки  $dataType  код  типа  данных  
	$expr  -  $MappingType  -  код  типа  отображения  при  экспорте  в  XML  (константа  серии  MappingType)
	 	*/
 	 	$this->ColumnName = $columnName;
 	 	$this->dataType = $dataType;
 	 	// режим отображения колонки/атрибут/сам узел при экспорте в XML
 	 	if (! empty($MappingType))
 	 	 	$this->ColumnMapping = $MappingType;
 	 	else
 	 	 	$this->ColumnMapping = MappingTypeElement; // делаем по умолчанию вывод в XML в виде узла
 	
 	}

}
