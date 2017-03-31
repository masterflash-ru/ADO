<?php

/*
сущность для менеджера репозитариев
собственно тут идет вся работа с RS по поиску и гидратации

в сущности можно описать поля:
private static $__map__=[
	"fields"=>[
	колонка=>[name=>имя_в_сущности, type=>имя_типа, length=>размерность с символах]
			"id"=>["name"=>"id","type"=>"int"],
			"email"=>["name"=>"email"],
			"name"=>["name"=>"name"],
			"pass"=>["name"=>"pass"],
			"fullname"=>["name"=>"fullname","type"=>"string","length"=>100],
			"email"=>["name"=>"email"],
			"tel_mobil"=>["name"=>"tel_mobil"]
			],
	"table"=>"admins"
	];
имя_типа - строка которую принимает settype - это http://php.net/manual/ru/function.settype.php
если указан тип int или integer - параметр length - игнорируется

*/
namespace ADO\Entity;

use ADO\Hydrator\Rs as HydratorRs;
use ADO\Service\RecordSet;
use ReflectionProperty;
use ReflectionClass;


class EntityRepository
{
	
    /**
     * @var string
     */
    protected $_entityName;

    /**
     * @var RS
     */
    protected $_rs;

	/*метаданные для полей*/
	protected $_meta=[];
	
	/*гидратор RS*/
	protected $hydrator;
	
	/*сущности которые возвращались, и их хеши*/
	protected $_entitys=[];
	

public function __construct (RecordSet $rs, $entityName)
{
	$this->_rs=$rs;
	$this->_entityName=$entityName;
	$this->hydrator=new HydratorRs();
	
	$obj = new ReflectionClass($this->_entityName);
	if ($obj->hasProperty('__map__'))
		{
			//смотрим что в объекте на предмет карты полей
			$r=new ReflectionProperty($this->_entityName,'__map__');
			$r->setAccessible(true);
			$this->_meta=$r->getValue();
			$r->setAccessible(false);
		}
		else
			{
				//карта не указана, генерируем на основе методов и имени класса
				$table=array_reverse(explode("\\",$this->_entityName));
				$this->_meta["table"]=$table[0];
				$this->_meta["fields"]=[];
				foreach ($obj->getMethods() as $item)
					{
						$name=$item->getName();
						if (stristr($name,"Set")===false) {continue;}
						$name=substr($name, 3);
						$this->_meta["fields"][$name]=["name"=>$name];
					}
			}
}


/**
получить заполненную сущность текущей записью из RS
 */
public function FetchEntity()
{
	$rs=$this->_rs;
	if ($rs->EOF) {return NULL;}
	$object = new $this->_entityName;
	$this->hydrator->hydraters($rs, $object,$this->_meta);
	$this->_entitys[spl_object_hash($object)]=$rs->AbsolutePosition;
	return $object;
}

/**
получить массив заполненных сущностей данными из всего RS
 */
public function FetchEntityAll()
{
	$rs=$this->_rs;
	if ($rs->EOF) {return [];}
	$rez=[];
	while (!$rs->EOF)
		{
			$object = new $this->_entityName;
			$this->hydrator->hydraters($rs, $object,$this->_meta);
			$rez[]=$object;
			$this->_entitys[spl_object_hash($object)]=$rs->AbsolutePosition;
			$rs->MoveNext();
		}
	return $rez;
}



/*обновление данных в базе
$entity - экземпляр сущности которую надо записывать
*/
public function persist($entity)
{
	$rs=$this->_rs;
	$hash=spl_object_hash($entity);
	//проверяем новая запись или обновление существубщей
	if (!isset($this->_entitys[$hash]))
		{
			$rs->AddNew();
		}
		else
			{
				//перемотаем RS на эту запись
				$rs->AbsolutePosition=(int)$this->_entitys[$hash];
			}
	$this->hydrator->extractrs($rs,$entity,$this->_meta);
}

	
}

