<?php
/**
* простой объект для генерации массива/коллекции результатов выборки
*/

namespace ADO\Entity;


class Universal
{
    protected $value=[];

    
    /**
    *все методы перегружены, в разных проектах колонки таблицы расширения разные
    */
	public function __call($name, $arguments)
    {
        /*установка значения*/
        if(0===stripos($name,"set")){
            $this->value[strtolower (substr($name,3))]=$arguments[0];
        }
        /*чтение значения*/
        if(0===stripos($name,"get")){
            $key=strtolower (substr($name,3));
            if (isset($this->value[$key])) {
                return $this->value[$key];
            }
            return null;
        }
    }
	
    public function toArray()
    {
        return $this->value;
    }
}
