<?php
// define("ASC",1);
// define("DESC",-1);

namespace ADO\Extend;

use ADO\Exception\ADOException;

class Sort
{
 	// The array to be sorted
 	public $arr = array();

 	public $sortDef = array();
 	
 	// направление сортировки
 	const ASC = 1;

 	const DESC = - 1;
 	
 	// Constructor
 	function __construct ()
 	{
 	 	$this->arr = array();
 	 	$this->sortDef = array();
 	}
 	
 	// setArray method - sets the array to be sorted
 	function setArray ($arr)
 	{
 	 	$this->arr = $arr;
 	}
 	
 	/*
 	 * addColumn method - ads entry to sorting definition If column exists,
 	 * values are overwriten.
 	 */
 	function addColumn ($colName = "", $colDir = 'ASC', $compareFunc = NULL)
 	{
 	 	
 	 	$colDir = strtoupper($colDir);
 	 	if ($colDir != 'ASC' && $colDir != 'DESC') 	throw new ADOException(NULL, 15, "RecordSet::Sort", array($colDir));
 	 	
 	 	$colDir = constant("self::" . $colDir); // получить направление сортировки 1 или -1
 	 	$idx = $this->_getColIdx($colName);
 	 	if ($idx < 0) 
			{
 	 	 	$this->sortDef[] = array();
 	 	 	$idx = count($this->sortDef) - 1;
	 	 	}
 	 	$this->sortDef[$idx]["colName"] = $colName;
 	 	$this->sortDef[$idx]["colDir"] = $colDir;
 	 	$this->sortDef[$idx]["compareFunc"] = $compareFunc;
 	}
 	
 	// removeColumn method - removes entry from sorting definition
 	function removeColumn ($colName = "")
 	{
 	 	$idx = $this->_getColIdx($colName);
 	 	if ($idx >= 0)	array_splice($this->sortDef, $idx, 1);
 	}
 	
 	// resetColumns - removes any columns from sorting definition. Array to sort
 	// is not affected.
 	function resetColumns ()
 	{
 	 	$this->sortDef = array();
 	}
 	
 	// sort() method
 	function &sort ()
 	{
 	 	usort($this->arr, array($this, "_compare"));
 	 	return $this->arr;
 	}
 	
 	// _getColIdx method [PRIVATE]
 	function _getColIdx ($colName)
 	{
 	 	$idx = - 1;
 	 	for ($i = 0; $i < count($this->sortDef); $i ++)
			{
 	 	 	$colDef = $this->sortDef[$i];
 	 	 	if ($colDef["colName"] == $colName)
 	 	 	 	$idx = $i;
	 	 	}
 	 	return $idx;
 	}
 	
 	// Comparison function [PRIVATE]
 	function _compare ($a, $b, $idx = 0)
 	{
 	 	if (count($this->sortDef) == 0) 	return 0;
 	 	$colDef = $this->sortDef[$idx];
 	 	$a_cmp = $a[$colDef["colName"]];
 	 	$b_cmp = $b[$colDef["colName"]];
 	 	if (is_null($colDef["compareFunc"]))
			 {
 	 	 	$a_dt = strtotime($a_cmp);
 	 	 	$b_dt = strtotime($b_cmp);
 	 	 	if (($a_dt == - 1) || ($b_dt == - 1) || ($a_dt == false) ||	 ($b_dt == false)) 	$ret = $colDef["colDir"] *	 strnatcasecmp($a_cmp, $b_cmp);
 	 	 			else 
						{
			 	 	 	 	$ret = $colDef["colDir"] *	 (($a_dt > $b_dt) ? 1 : (($a_dt < $b_dt) ? - 1 : 0));
	 	 	 	 	 	}
 	 	 	 	} 
				else 
					{
 	 	 	 	 	$code = '$ret = ' . $colDef["compareFunc"] . '("' . $a_cmp . '","' . $b_cmp . '");';
 	 	 	 	 	eval($code);
 	 	 	 	 	$ret = $colDef["colDir"] * $ret;
 	 	 	 	 	}
 	 	if ($ret == 0) 
			{
 	 	 		if ($idx < (count($this->sortDef) - 1)) 	return $this->_compare($a, $b, $idx + 1);
 	 	 	 	 	 	 	else 	return $ret;
 	 	 	 } 
			 	else 	return $ret;
 	}
 
}
 ?>