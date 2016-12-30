<?php
/*
09.08.14 - перешли на preg_replace_callback, т.к. /е запрещен в версии 5.6

 * расширение RecordSet, для поиска и фильтрации данных по аналогу SQL запросов
 */
namespace ADO\Extend;


class FilterFind
{

/*
странно, но заработало в 5.4.3 : (увеличили жадность)
$this->patterns[] = '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\.]+)(\))?(\s)*(=|IS)(\s)*(\'|\")(.*)(\'|\")(\s)*#ieU';//пришлось добавить U для обработки строки вида 'sysname="pic" or sysname="stat_page_path"'



*/

	public $patterns1 = array();
 	public $replacements1 = array();
 	public $ereg = array();
	private $_item;
	
	private $_preg_filter_cache=array();
	private $_preg_filter_cache1=array();
	
	
 	public function __construct ()
 	{
 	 	
 	 	
 	 	/*
 	 	 * match SQL operators
 	 	 */
 	 	$this->ereg = array('%' => '(.*?)', '_' => '(.)');
 	 	// print_r($ereg);
		
		
		//===============
		
 	 	$this->replacements1[] = "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 == \\9 '";
 	 	$this->replacements1[] = "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 == \"\\10\" '";
 	 	$this->replacements1[] = "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 \\7 \\9 '";
 	 	$this->replacements1[] = "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 \\7 \\9 '";
 	 	$this->replacements1[] = "'false!=preg_match(\"/'.strtr(\"\\5\", \$this->ereg).'/i\", '.\$this->parse_where_key(\"\\1\").')'";  //4
 	 	$this->replacements1[] = "'false== preg_match(\"/'.strtr(\"\\5\", \$this->ereg).'/i\", '.\$this->parse_where_key(\"\\1\").')'";
		
		
		
	$this->patterns1[] = '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)*(=|IS)(\s)*(-?[[:digit:]]+)(\s)*#i';
 	$this->patterns1[] = '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)*(=|IS)(\s)*(\'|\")(.*)(\'|\")(\s)*#iU';//пришлось добавить U для обработки строки вида 'sysname="pic" or sysname="stat_page_path"'
 	$this->patterns1[] = '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)*(>|<)(\s)*(-?[[:digit:]]+)(\s)*#i';
 	$this->patterns1[] = '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)*(<=|>=)(\s)*(-?[[:digit:]]+)(\s)*#i';
 	$this->patterns1[] = '#([a-zA-Z0-9\.]+)(\s)+LIKE(\s)*(\'|\")(.*)(\'|\")#i';
 	$this->patterns1[] = '#([a-zA-Z0-9\.]+)(\s)+! +LIKE(\s)*(\'|\")(.*)(\'|\")#i';
		
		
		
 	}


public function Filter_callback($in)
{
	//echo "\n".$this->_item.":";	print_r($in);
	
	switch ($this->_item)
		{
			
			case 0: return $in[1].$this->parse_where_key($in[4]).$in[5]." == ".$in[9]." ";break;    //тип id=300
			case 1: return $in[1].$this->parse_where_key($in[4]).$in[5]." == \"".$in[10]."\" ";break;    //тип id='300'
			case 2:case 3: return $this->parse_where_key($in[4]).$in[5]." ".$in[7]." ".$in[9]." ";break;		//тип id<300 (>)
			case 4: return 'false!=preg_match("/'.strtr($in[5], $this->ereg).'/i", '.$this->parse_where_key($in[1]).')';break; //тип id like '30%'
			case 5: return 'false==preg_match("/'.strtr($in[5], $this->ereg).'/i", '.$this->parse_where_key($in[1]).')';break; //тип id ! like '30%'
		}
	return "";
}


 	public function RsFilter ($arr, $field_name, $where_string)
 	{
 	 	/*
 	 	 * из массива выбирает те записи, которые удовлетворяют критерию поиска
 	 	 * $arr - входной массив из рекордсета $field_name - имена полей, четкое
 	 	 * соответсвие !!!! $where_string - строка условия
 	 	 */
//echo $where_string.'<br>';
 	 	$rez = array(); // очистить выходной буфер
		$a=$where_string;
		//смотрим в кеше по хешу
		$h=md5($a);
		if (!array_key_exists($h,$this->_preg_filter_cache1) )
			{

				foreach ($this->patterns1 as $this->_item=>$pattern)
					{
						$a =trim(preg_replace_callback($pattern, array($this,"Filter_callback"), $a));
					}
			$a = str_ireplace(array(' and ', ' or '), 	array(' && ', ' || '), $a);
			$a_ = create_function('$rez_array1,$a', 	'return ' . $a . ';');
			$this->_preg_filter_cache1[$h]=$a_;
			}
			else
			{
				$a_=$this->_preg_filter_cache1[$h];
			}
		//echo "  По новому: $a\n";
			//-----------------------------------------
		
		
		
 	 	
		//echo $a.'<br>';
		
		//$a_ = create_function('$rez_array1,$a', 	'return ' . $a . ';');

 	 	foreach ($arr as $rez_array) 
		{
 	 	 	$rez_array_ = $rez_array;
 	 	 	unset($rez_array_['status']); // удалим служебные флаги
 	 	 	$rez_array1 = array_combine($field_name, $rez_array_); // сделать  массив что бы ключи были не числовые а имена полей, и преобразовать в переменные
			
			if ($a_($rez_array1, $a)) 	$rez[] = $rez_array; // выполнить условие
 	 	}
 	 	return $rez;
 	}

 	public function Filter ($arr_item, $field_name, $where_string)
 	{ // проверяет на предмет удовлетворения условиям поиска, строка налогична как SQL возвращает true | false
 	 	/*
 	 	 * из массива выбирает те записи, которые удовлетворяют критерию поиска
 	 	 * $arr_item - входной массив из рекордсета ТОЛЬКО ЭЛЕМЕНТ! $field_name
 	 	 * - имена полей, четкое соответсвие !!!! $where_string - строка условия
 	 	 * /
 	 	$a = stripslashes(
 	 	 	 	trim(
 	 	 	 	 	 	preg_replace($this->patterns, $this->replacements, 
 	 	 	 	 	 	 	 	$where_string)));
 	 	$a = str_ireplace(array(' and ', ' or ', '=', 'false===', 'false!=='), 
 	 	 	 	array(' && ', ' || ', '==', 'false==', 'false!='), $a);
 	 	$a = preg_replace("/={3,}/", "==", $a);
 	 	
 	 	*/
		$a=$where_string;
		//смотрим в кеше по хешу
		$h=md5($a);
		if (!array_key_exists($h,$this->_preg_filter_cache) )
			{
				foreach ($this->patterns1 as $this->_item=>$pattern)
					{
						$a =trim(preg_replace_callback($pattern, array($this,"Filter_callback"), $a));
					}
			$a = str_ireplace(array(' and ', ' or '), 	array(' && ', ' || '), $a);
			$a_ = create_function('$rez_array1,$a', 'return ' . $a . ';');
			$this->_preg_filter_cache[$h]=$a_;
			}
			else
			{
				$a_=$this->_preg_filter_cache[$h];
				
			}
		
		//echo "  По новому: $a\n";
			//-----------------------------------------
		
		//$a_ = create_function('$rez_array1,$a', 'return ' . $a . ';');

		
		unset($arr_item['status']); // удалим служебную информацию
		//echo "<pre>$where_string ";print_r($field_name);print_r($arr_item);
 	 	$rez_array1 = array_combine($field_name, $arr_item); // сделать  массив что  бы ключи были  не  числовые а  имена полей,  и  преобразовать  в  переменные
 	 	return $a_($rez_array1, $a); // /выполнить условие и вернуть результат
 	}

 	
	
	
	private function parse_where_key ($key)
 	{
 	 	return "\$rez_array1['" . $key . "']";
 	}

}
