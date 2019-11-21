<?php
/*
 * версия 1.00 вспомогательная библиотека для RS касаемо работы с XML выделена
 * для уменьшения объемов объектов в памяти данные из RS передаются посредством
 * объекта stdClass; Для изменения в RS нужно св-ва передавать по ССЫЛКЕ!
 * Внимание! ReadXmlSchema не реализована!
 */
	
namespace ADO\Extend;

use ADO\Entity\DataColumns;
use ADO\Entity\Fields;
use ADO\Entity\DataColumn;
use ADO\Entity\Field;



class AdoXml
{

	public function __construct ()
	{
		mb_internal_encoding("UTF-8");
		mb_detect_order(['Windows-1251', 'UTF-8']);
	}

public function ReadXmlSchema (\stdClass $IOstdClass)
	{ // настраивает RS в  соответсвии со  схемой  Схема примитивная,  содержит ТОЛЬКО  ОДНУ таблицу, без  каких-либо  связей!!!!!  $source - либо  строка с самой  схемой либо имя  файла
		return;
		$IOstdClass->rs->Close();
		$IOstdClass->rs->Open(); // инициализируем все
		$IOstdClass->rs->Fields = new Fields(); // коллекция полей
		$IOstdClass->rs->DataColumns = new DataColumns(); // коллекция полей
		$xml = ADO::SimpleXMLIterator($IOstdClass->source);
		
		$ns_schema = "http://www.w3.org/2001/XMLSchema";
		$IOstdClass->rs->RecordSetName = (string) $xml->children($ns_schema)->attributes()->name[0]; // имя рекордсета это элемент таблицы
		$tab_element = $xml->children($ns_schema)->children($ns_schema)->children($ns_schema)->children($ns_schema);
		$tab_name = (string) $tab_element->attributes()->name[0]; // это имя таблицы
																	  
		// разбираем колонки таблицы
		$columnCount = 0;
		$column_element_special = $tab_element->children($ns_schema)->children($ns_schema); // специальные эл-ты таблицы, скрытые, в виде атрибут
		$column_element = $column_element_special->children($ns_schema);
		print_r($column_element_special);
		// пробежим по обычным элементам
		foreach ($column_element as $v) 
		{
			print_r($v);
		}
	
}

public function GetXmlSchema (\stdClass $IOstdClass)
{ // получить схему  структуры RS в виде / строки
    $IOstdClass->rs->MoveFirst(); // перематываем в начало  если просто схема, то генерируем как  обычно, иначе по особому, т.к. схема  вставляется после первого узла!
	if (! $IOstdClass->flag_create_xsdxml){
        $xml = new \SimpleXMLIterator('<?xml version="1.0" encoding="utf-8"?><xs:schema id="' . $IOstdClass->rs->RecordSetName . '" xmlns="http://masterflash.ru" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:msdata="urn:schemas-microsoft-com:xml-msdata" />');
    } else { // $this->_create_xsdxml хранит ухел куда нужно вставить схему
            $xml = $IOstdClass->_create_xsdxml;
    }
    $xml1 = $xml->addChild('element', '', "http://www.w3.org/2001/XMLSchema"); // print_r($this->flag_create_xsdxml);exit;
    $xml1->addAttribute('name', $IOstdClass->rs->RecordSetName);
    $xml1->addAttribute('msdata:IsDataSet', "true", 	"urn:schemas-microsoft-com:xml-msdata");
    $xml1->addAttribute('msdata:UseCurrentLocale', "true", "urn:schemas-microsoft-com:xml-msdata");
    $xml1 = $xml1->addChild('complexType', '')->addChild('choice', '');
    $xml1->addAttribute('minOccurs', 0);
    $xml1->addAttribute('maxOccurs', "unbounded");
    $xml1 = $xml1->addChild('element', '');
    $xml1->addAttribute('name', $IOstdClass->rs->DataColumns->Item[0]->Table);
    $xml1 = $xml1->addChild('complexType', '');
    $flag_sequence = false; // флаг вывода значений (хоть одного) в виде узла
    $ii = 0;
    foreach ($IOstdClass->rs->DataColumns as $v){
        if ($v->ColumnMapping == ADO::MappingTypeElement) {
            if (! $flag_sequence) {
                $xml2 = $xml1->addChild('sequence', ''); // добавим только ОДИН раз!!!
            }
            $xml3 = $xml2->addChild('element', ''); // добавми описатель элмента
            $xml3->addAttribute('name', $v->ColumnName);
            $xml3->addAttribute('minOccurs', 0);
            // тип данных
            $this->create_xml_element_type($v, $xml3); // сгенерировать атрибуты для данного узла, в зависимости от типа данных
            $xml3->addAttribute('msdata:Ordinal', $ii, "urn:schemas-microsoft-com:xml-msdata"); // порядоковый номер поля
            $flag_sequence = true; // флаг генерации sequence
            $ii ++;
        }
        // проверяем колонки у которых вывод в виде атрибут
        if ($v->ColumnMapping == ADO::MappingTypeAttribute)	 {
            $xml3_1 = $xml1->addChild('attribute', ''); // добавми описатель элмента
            $xml3_1->addAttribute('name', $v->ColumnName);
            // тип данных
            $this->create_xml_element_type($v, $xml3_1); // сгенерировать атрибуты для данного узла, в зависимости от типа данных
            if ($v->ColumnMapping == ADO::MappingTypeHidden){
                $xml3_1->addAttribute('use', "prohibited"); // добавим один атрибут, если колонка скрытая
            }
        }
    }
    $IOstdClass->rs->MoveFirst(); // перематываем в начало
    return $xml->asXML();
}

public function ReadXml (\stdClass $IOstdClass)
{
if (is_string($IOstdClass->source))	$xml = ADO::SimpleXMLIterator($IOstdClass->source, NULL, true); // считываем из файла и сразу разбираем
$this->container['locktype'] = ADO::adLockBatchOptimistic; // переключим в пакетный режим
															
// разбор формата DiffGram
$IOstdClass->rs->RecordSetName = $xml->children()->getName(); // имя рекордсета и имя таблицы получить имена полей,/ в 2-х форматах
$IOstdClass->get_field_name_false = array_keys((array) $xml->children()->children()); // массив  полей  в  формате  array(0=>имя0,1=>имя1.....)
foreach ($IOstdClass->get_field_name_false as $v)
	$IOstdClass->get_field_name_true[$v] = count($IOstdClass->get_field_name_true); // массив наоборот начнем первичную инициализацию объекта, т.е. имитируем считывание из базы данных кол-во колонок в таблице
$columnCount = count($IOstdClass->get_field_name_false);
$IOstdClass->columnCount = $columnCount;

// $this->rez_array[0]=array_fill(0,$this->columnCount,NULL) ;
// $this->rez_array[0]['status']=array('flag_change'=>false,'flag_new'=>false,'flag_canceled'=>false,'flag_delete'=>false);
$IOstdClass->rs->Fields = ADO::Fields(); // коллекция полей
$IOstdClass->rs->DataColumns = ADO::DataColumns(); // коллекция полей

for ($i = 0; $i < $columnCount; $i ++) 
	{ // в коллекцию полей вносим объекты полей имеющие только имя, остальное все NULL, т.к. мы ничего не знаем о полях
			$field = new Field(array('name' => $IOstdClass->get_field_name_false[$i]));
			$field->set_parent_recordset($IOstdClass->rs); // укажем объекту Field родительский RecordSet, что бы при изменении в полях вызывались функции рекордсета
			$IOstdClass->rs->Fields->Add($field); // отправим в коллецию
											 
	// ---------------------------------------------------------данный раздел в зачаточном состоянии!!!!!!!!!!!!! генерируем коллекцию DataColumn
	$DataColumn = new DataColumn($IOstdClass->rs->Fields->Item[$i]->Name); // ,$ColumnMeta['Type']);
	$DataColumn->Ordinal = $i;
	$DataColumn->Caption = $IOstdClass->rs->Fields->Item[$i]->Name;
	// $DataColumn->AllowDbNull=!in_array('not_null',
	// $ColumnMeta['flags']);
	$IOstdClass->rs->DataColumns->Add($DataColumn);
	}

// пробежим по узлам c данными
foreach ($xml->children() as $node) 
	{
		$rowOrder = (int) $node->attributes("urn:schemas-microsoft-com:xml-msdata"); // порядковый номер  строки  echo  $rowOrder;  получим  идентификатор  строки  и  атрибуты  новых/измененных  полей
		$atr = (array) $node->attributes("urn:schemas-microsoft-com:xml-diffgram-v1"); // $atr['@attributes'];//сами  стрибуты  узла  с  данными  пробежим  по  всем  атрибутам?  атрибут  от  id  определяет  поведение  загрузки  данных
		$hasChanges = NULL;
		while (list ($key, $val) = each($atr['@attributes'])) 
			{
				if ($key == 'id')$id = $val; // это идентификатор строки
				if ($key == 'hasChanges')	$hasChanges = $val; // inserted || modified		// (вставка/модификация)
			}
	if (empty($hasChanges)) 
			{ // запись данных как есть, это не модифицированные данные
			$Fields = array();
			$Values = array();
			foreach ($node->children() as $k => $n) 
						{ // $k=>$n - имя столбца=>значение столбца
						$Values[] = (string) $n;
						$Fields[] = $k;
						}
			// print_r($Fields);
			$IOstdClass->rs->AddNew($Fields, $Values); // добавим запись
			}
	}
}

public function GetXml (\stdClass $IOstdClass)
{ // получить структуру RecordSet в виде XML (без схемы)
// вначале пробежим по всем колонкам таблицы и получим простанства имен
$ns = array();
/*
 * чрезе одно место добавляем пространство имен, надо разобраться почему через
 * addAttribute неверно добавляется это дело
 */

// $IOstdClass->DiffGram_MappingTypeHidden - флаг, когда работаем с
// форматом Diffgram равен true
if (empty($IOstdClass->DiffGram_MappingTypeHidden))	$IOstdClass->DiffGram_MappingTypeHidden = false;

foreach ($IOstdClass->rs->DataColumns as $columns)
	if ($columns->NameSpace && $columns->Prefix)
			$ns[] = 'xmlns:' . $columns->Prefix . '="' . $columns->NameSpace . '"';
	// удалим дубликаты
$ns = array_unique($ns);
// если у нас нет NAMESPACE вообще, то и уберем его по умолчанию
if (! empty($ns))	$ns_ = ' xmlns="http://masterflash.ru/recordset" ';
		else	$ns_ = '';
$ns = implode(' ', $ns);
if (! $IOstdClass->flag_create_xsdxml)
	$xml = new \SimpleXMLIterator('<?xml version="1.0" encoding="utf-8"?><' .$IOstdClass->rs->RecordSetName . $ns_ . $ns . '/>');
		else 
			{
				$xml = new \SimpleXMLIterator('<?xml version="1.0" encoding="utf-8"?><' .$IOstdClass->rs->RecordSetName . $ns_ . $ns .'><xs:schema id="' . $IOstdClass->rs->RecordSetName . '" xmlns="http://masterflash.ru" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:msdata="urn:schemas-microsoft-com:xml-msdata" /></' . $IOstdClass->rs->RecordSetName . '>');

				$IOstdClass->_create_xsdxml = $xml->children("http://www.w3.org/2001/XMLSchema"); // $IOstdClass->flag_create_xsdxml=false;
				$this->GetXmlSchema($IOstdClass);
			}
$IOstdClass->rs->MoveFirst(); // перематываем в начало получить имя таблицы с которой работаем,$this->DataColumns->Item[0]->Table

do // while (!$this->EOF) //обязательно 1 проход, если данных нет, генерируем пустые колонки
	{ // генерируем узел - имя таблицы
	$i = 0;
	// если генерируем схему и данные в одном файле, то сохраним
	// SimpleXMLIterator для метода GetXmlSchema()
	if ($IOstdClass->flag_create_xsdxml && ! empty($IOstdClass->_create_xsdxml)) 
			{ // сохраним  экземпляр  интератора  $this->_create_xsdxml=$xml;  генерируем  схему  и  внедряем  ее  в  документ  $this->GetXmlSchema();
				}
$xml1 = $xml->addChild($IOstdClass->rs->DataColumns->Item[0]->Table);
$i = 0; // print_r($ColumnMeta);
foreach ($IOstdClass->rs->Fields as $k => $Field) 
		{ // echo/ $Field->Name."\t".$Field->Value."\n";
			// в коллекции имена объектов $Field числовые и строковые, берем только строковые.

		if (is_string($k)) 
			{
				$ColumnMeta = $IOstdClass->rs->DataColumns->Item[$i]; // экземпляр объекта DataColumn для данной колонки если у нас двоичные данные, упаковываем их в base 64
				if ($ColumnMeta->DataType == ADO::adBinary) $Value = base64_encode($Field->Value);
							else $Value = $this->convert_to_utf8($Field->Value);
				// $xml2=$xml1->addChild($Field->Name,$Value);
				$i ++;
				// сформируем простанство имен если есть + префикс для
				// атрибут
				if (! empty($ColumnMeta->NameSpace) && ! empty($ColumnMeta->Prefix)) 
						{
							$ns = $ColumnMeta->NameSpace;
							$prefix = $ColumnMeta->Prefix . ":";
						} 
					else 
						{
							$ns = NULL;
							$prefix = "";
						}
			// проверим как выводить в итоговый XML документ
			switch ($ColumnMeta->ColumnMapping) 
				{
					case ADO::MappingTypeAttribute:
							{ // вариант записи в виде атрибут
								$xml1->addAttribute($prefix . $Field->Name, $Value, $ns);
								break;
							}
					case ADO::MappingTypeHidden:
							{
							// $xml1->addAttribute('msdata:hidden'.$Field->Name,$Value,"urn:schemas-microsoft-com:xml-msdata");
							break;
							}
					default:
							$xml2 = $xml1->addChild($Field->Name, $Value, $ns); // это ADO::MappingTypeElement
				}
			}//if (is_string($k)) 
		}//foreach
	$IOstdClass->rs->MoveNext();
	} while (! $IOstdClass->rs->EOF);
$IOstdClass->rs->MoveFirst(); // перематываем в начало
return $xml->asXML();
}

public function WriteXml (\stdClass $IOstdClass)
{ // записывает recordset в формате XML

$IOstdClass->rs->MoveFirst(); // перематываем в начало селектор типа выходного XML

switch ($IOstdClass->WriteMode) 
	{
		case ADO::DiffGram:
				{
				mb_internal_encoding("UTF-8");
				// флаг формата DiffGram
				$IOstdClass->DiffGram_MappingTypeHidden = true;
				$diffrg_errors = array(); // здесь мы хъраним массив ошибок что бы потом в конце вставить в XML
				$diffrg_before = NULL; // хранит узел XML раздела before
				// mb_detect_order(ADO::$mb_detect_order);
				$xml = new \SimpleXMLIterator(	'<?xml version="1.0" encoding="utf-8"?><diffgr:diffgram xmlns="http://masterflash.ru"  xmlns:msdata="urn:schemas-microsoft-com:xml-msdata" xmlns:diffgr="urn:schemas-microsoft-com:xml-diffgram-v1" xmlns:xsd="http://www.w3.org/2001/XMLSchema"/>');
				do	 // while (!$this->EOF) Обязательно один проход, на тот случай, если данных вообще нет
						{ // генерируем узел - имя таблицы для пустого набора данных, имитируем наличие записи, что бы избежать ошибки, и генерируем пустые колонки таблицы
							if (! ($IOstdClass->rs->Status & ADO::adRecDeleted)) 
										{ // генерируем данные только для НЕ удаленных
											$xml1 = $xml->addChild(	$IOstdClass->rs->DataColumns->Item[0]->Table, '', "http://masterflash.ru");
											// $new['status']=array('flag_change'=>false,'flag_new'=>true,'flag_canceled'=>false,'flag_delete'=>false,'BookMark'=>sprintf("%u",crc32(microtime())));
											// флаги обновления записи если установлены
											if ($IOstdClass->rs->Status & ADO::adRecModified) $xml1->addAttribute('diffgr:hasChanges', "modified", "urn:schemas-microsoft-com:xml-diffgram-v1");
											if ($IOstdClass->rs->Status & ADO::adRecNew) $xml1->addAttribute('diffgr:hasChanges', "inserted", "urn:schemas-microsoft-com:xml-diffgram-v1");
											// идентификатор
											$xml1->addAttribute('diffgr:id', $IOstdClass->rs->DataColumns->Item[0]->Table . $IOstdClass->rs->AbsolutePosition, "urn:schemas-microsoft-com:xml-diffgram-v1");
											// порядковый номер записи
											$xml1->addAttribute('diffgr:rowOrder', $IOstdClass->rs->AbsolutePosition - 1, "urn:schemas-microsoft-com:xml-msdata");
											// print_r($this->Fields);exit;
											if (is_object($IOstdClass->rs->_get_rec_error()))
																$xml1->addAttribute('diffgr:hasErrors', "true", 	"urn:schemas-microsoft-com:xml-msdata");
											$i = 0; // print_r($ColumnMeta);
											foreach ($IOstdClass->rs->Fields as $k => $Field) 
																{ // echo $Field->Name."\t".$Field->Value."\n";
																  // в коллекции имена объектов $Field числовые и строковые, беремтолько строковые.
																	if (is_string($k)) 
																			{
																				$ColumnMeta = $IOstdClass->rs->DataColumns->Item[$i]; // экземпляр  объекта  DataColumn  для  данной  колонки  если  у  нас  двоичные  данные,  упаковываем  их  в  base  64
																				if ($ColumnMeta->DataType == ADO::adBinary)	$Value = base64_encode($Field->Value);
																											else	$Value = $this->convert_to_utf8($Field->Value);
																				// $xml2=$xml1->addChild($Field->Name,$Value);
																				$i ++;
																				// проверим как выводить в итоговый XML документ
																				switch ($ColumnMeta->ColumnMapping) 
																							{
																								/*
																								 * case ADO::MappingTypeAttribute: {//вариант записи в
																								 * виде атрибут
																								 * $xml1->addAttribute($Field->Name,$Value); break; }
																								 */
																									case ADO::MappingTypeHidden:
																																{
																																	$xml1->addAttribute('msdata:hidden' . $Field->Name, $Value, "urn:schemas-microsoft-com:xml-msdata");
																																	break;
																																}
																										default:
																														$xml2 = $xml1->addChild($Field->Name, $Value); // это  ADO::MappingTypeElement
																							}
				
																				}
																		}
										}//if (! ($IOstdClass->rs->Status & ADO::adRecDeleted)) 
						// проверим саму ошибку
						if (is_object($IOstdClass->rs->_get_rec_error())) 
								{
									// проверим, есть ли ошибка, если есть и $diffrg_errors пустой, тогда там создаем XML узел
									$diffrg_errors['diffgr:id'][] = $IOstdClass->rs->DataColumns->Item[0]->Table . $IOstdClass->rs->AbsolutePosition;
									$diffrg_errors['diffgr:Error'][] = $IOstdClass->rs->_get_rec_error()->getMessage();
								}
						// удален или изменен?
					if (($IOstdClass->rs->Status & ADO::adRecDeleted) || ($IOstdClass->rs->Status & ADO::adRecModified)) 
							{
									if (! is_object($diffrg_before))	$diffrg_before = $xml->addChild('before', '', "urn:schemas-microsoft-com:xml-diffgram-v1");
									$xml1 = $diffrg_before->addChild($IOstdClass->rs->DataColumns->Item[0]->Table, 	'', "http://masterflash.ru");
									// идентификатор
									$xml1->addAttribute('diffgr:id', $IOstdClass->rs->DataColumns->Item[0]->Table . $IOstdClass->rs->AbsolutePosition, "urn:schemas-microsoft-com:xml-diffgram-v1");
									// порядковый номер записи
									$xml1->addAttribute('diffgr:rowOrder', $IOstdClass->rs->AbsolutePosition - 1, "urn:schemas-microsoft-com:xml-msdata");
							
							// if
							// (isset($IOstdClass->old_rez_array[$IOstdClass->rs->AbsolutePosition
							// - $IOstdClass->AbsolutePosition_min_max[0]]))
							// $this->rez_array2Field($IOstdClass->old_rez_array[$IOstdClass->rs->AbsolutePosition
							// -
							// $IOstdClass->AbsolutePosition_min_max[0]],$IOstdClass);
							
								$i = 0;
								foreach ($IOstdClass->rs->Fields as $k => $Field)
									if (is_string($k)) 
										{
											$ColumnMeta = $IOstdClass->rs->DataColumns->Item[$i]; // экземпляр объекта DataColumn для данной колонки если у нас двоичные данные, упаковываем их в base 64
											if ($ColumnMeta->DataType == ADO::adBinary)	$Value = base64_encode($Field->originalvalue);
														else $Value = $this->convert_to_utf8($Field->originalvalue);
											$i ++;
											// проверим как выводить в итоговый XML документ
											switch ($ColumnMeta->ColumnMapping) 
												{
													case ADO::MappingTypeAttribute:
																{ // вариант записи в виде атрибут
																	$xml1->addAttribute($Field->Name, $Value);
																	break;
																}
													case ADO::MappingTypeHidden:
																{
																	$xml1->addAttribute('msdata:hidden' . $Field->Name, $Value);
																break;
																}
													default:
																$xml2 = $xml1->addChild($Field->Name, $Value); // это ADO::MappingTypeElement
												}
										}
							}
						$IOstdClass->rs->MoveNext();
					} 
						while (! $IOstdClass->rs->EOF);
				// добавим ошибки если есть
				if (isset($diffrg_errors['diffgr:id'])) 
						{
						// создаем узел diffgr:errors'
						$diffrg_errors1 = $xml->addChild('diffgr:errors', '', "urn:schemas-microsoft-com:xml-diffgram-v1");
						// в цикле добавляем для каждой записи узел с атрибутами ошибки
						foreach ($diffrg_errors['diffgr:id'] as $k => $id) 
								{
								$diffrg_errors2 = $diffrg_errors1->addChild(	$IOstdClass->rs->DataColumns->Item[0]->Table, '', "http://masterflash.ru");
								$diffrg_errors2->addAttribute('diffgr:id', $id, "urn:schemas-microsoft-com:xml-diffgram-v1");
								$diffrg_errors2->addAttribute('diffgr:Error', $diffrg_errors['diffgr:Error'][$k], "urn:schemas-microsoft-com:xml-diffgram-v1");
								}
							}
				//$IOstdClass->rs->MoveFirst(); // перематываем в начало
				$xml_text = $xml->asXML();
				break;
				} // case ADO::DiffGram:
			case ADO::IgnoreSchema:
				{ // игнорировать схему, просто XML данные
					$IOstdClass->flag_create_xsdxml = false;
					$xml_text = $this->GetXml($IOstdClass); // генерирует без схемы так же как и метод  GetXml()
					$this->_create_xsdxml = NULL; // овсободим память
					break;
				}
			case ADO::WriteSchema:
				{ // записать схему в XML данные
					$IOstdClass->flag_create_xsdxml = true;
					$xml_text = $this->GetXml($IOstdClass); // генерирует без  схемы так  же как и метод GetXml()
					$IOstdClass->flag_create_xsdxml = false;
					$this->_create_xsdxml = NULL; // овсободим память
				}
		
		} // конец switch
		$IOstdClass->rs->MoveFirst(); // перематываем в начало
		if (empty($IOstdClass->destination)) return $xml_text;
		if (is_string($IOstdClass->destination)) 	file_put_contents($IOstdClass->destination, $xml_text);
}

private function create_xml_element_type (DataColumn $v, \SimpleXMLIterator $xml3)
{
		// тип данных добавляет нужные атрибуты в элемент $xml3, в зависимости от типа данных
switch ($v->dataType) 
		{
			case ADO::adInteger:
				{
					$xml3->addAttribute('type', 'xs:int');
					break;
				}
			case ADO::adSingle:
				{
					$xml3->addAttribute('type', 'xs:int');
					break;
				}
			case ADO::adDouble:
				{
					$xml3->addAttribute('type', 'xs:double');
					break;
				}
			case ADO::adBinary:
				{
					$xml3->addAttribute('type', 'xs:base64Binary');
					break;
				}
			case ADO::adCurrency:
			case ADO::adDecimal:
				{
					$xml3->addAttribute('type', 'xs:float');
					break;
				}
			case ADO::adTinyInt:
				{
					$xml3->addAttribute('type', 'xs:byte');
					break;
				}
			case ADO::adUnsignedBigInt:
				{
					$xml3->addAttribute('type', 'xs:unsignedLong');
					break;
				}
			case ADO::adUnsignedTinyInt:
				{
					$xml3->addAttribute('type', 'xs:unsignedByte');
					break;
				}
			case ADO::adDBDate:
				{
					$xml3->addAttribute('type', 'xs:date');
					break;
				}
			case ADO::adDBTime:
				{
					$xml3->addAttribute('type', 'xs:time');
					break;
				}
			case ADO::adDBTimeStamp:
				{
					$xml3->addAttribute('type', 'xs:dateTime');
					break;
				}
			default:
				$xml3->addAttribute('type', 'xs:string'); // по умолчанию - строка
		}
}

private function convert_to_utf8 ($in)
	{
		$encode_name = mb_detect_encoding($in); // определить имя кодировки
		if ($encode_name == 'UTF-8')
			return $in;
			// конвертируем в UFT-8
			// echo $encode_name.' ';
		return mb_convert_encoding($in, "UTF-8", $encode_name);
	}

}
?>