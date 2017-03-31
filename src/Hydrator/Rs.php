<?php
/**
 */
namespace ADO\Hydrator;

use Zend\Hydrator\ClassMethods;
use Zend\Hydrator\Exception\BadMethodCallException;
use ADO\Service\RecordSet;
use ADO\Exception\ADOException;

class Rs extends ClassMethods
{

    public function __construct()
    {
        parent::__construct(false);
    }


/*
надстройка над стандартным гидратором 
расширяет метод extract
*/
public function extractrs(RecordSet $rs,$entity,array $meta=[])
{
	$rez=$this->extract($entity);
	$fields=$meta["fields"];
	$data=[];
	//\Zend\Debug\Debug::dump($rs->DataColumns->Item_text);
	foreach ($rez as $field_db=>$value)
		{
			if (!array_key_exists($field_db,$rs->DataColumns->Item_text) ) {throw new ADOException(NULL, 25,NULL,[$field_db] );}
			
			if (isset($fields[$field_db]["type"]))
				{//преобразование типа, если указано в карте сущности
					settype($value,$fields[$field_db]["type"]);
				}
			$code=mb_detect_encoding($value);
			if (isset($fields[$field_db]["length"]) 
							&& mb_strlen($value,$code) > (int)$fields[$field_db]["length"]
							&& $fields[$field_db]["type"]!="int" && $fields[$field_db]["type"]!="integer"
						)
				{//преобразование длинны, если указано в карте сущности
					$value=mb_substr($value,0,(int)$fields[$field_db]["length"],$code);
				}
			$rs->Fields->Item[$field_db]->Value=$value;
		}
	
}



    /**
	надстройка над стандартный гидратором Zend
	просто RecordSet преобразуем в массив и далее все стандартно
	если имеется карта соотвествия полей и объекта, тогда работаем с ней
	$rs - RS  с записью которую грузим
	$object - объект куда грузим
	$meta - метаданные сущности, если есть (массив соответсвий полей сущности и таблицы в RS)
     */
    public function hydraters(RecordSet $rs, $object, array $meta=[])
    {
		$data=[];
		$fields=$meta["fields"];
		foreach ($rs->DataColumns->Item_text as $property=>$columninfo) /*имя_поля_таблицы => метаданные*/
		{
			if (array_key_exists($property,$fields)) 
				{
					$property1=$fields[$property]["name"];
					$data[$property1]=$rs->Fields->Item[$property]->Value;
					if (isset($fields[$property]["type"]))
						{//преобразование типа, если указано в карте сущности
							settype($data[$property1],$fields[$property]["type"]);
						}
					$code=mb_detect_encoding($data[$property1]);
					if (isset($fields[$property]["length"]) 
							&& mb_strlen($data[$property1],$code) > (int)$fields[$property]["length"]
							&& $fields[$property]["type"]!="int" && $fields[$property]["type"]!="integer"
						)
						{//преобразование длинны, если указано в карте сущности
							$data[$property1]=mb_substr($data[$property1],0,(int)$fields[$property]["length"],$code);
						}
				}
				else 
					{
						//нет описания, пишем как есть
						$data[$property]=$rs->Fields->Item[$property]->Value;
					}
			
			
        }
        return $this->hydrate($data, $object);
    }

}
