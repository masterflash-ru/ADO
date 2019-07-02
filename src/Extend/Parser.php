<?php

namespace ADO\Extend;
use ADO\Extend\Parser\Lexer;
use Exception;

class Parser
{
    /**
     * @var    Lexer
     * @access public
     */
    protected $lexer;

    /**
     * @var    string
     * @access public
     */
    protected $token;

    /**
     * @var    array
     * @access public
     */
   protected $symbols = [];

    /**
     * @var    array
     * @access public
     */
    protected $operators = [];

    /**
     * @var    array
     * @access public
     */
  protected $lexeropts = [];

    /**
     * @var    array
     * @access public
     */
 protected $parseropts = [];

    /**
     * @var    array
     * @access public
     */
  protected $comments = [];

    /**
     * @var    array
     * @access public
     */
    protected $quotes = [];
    protected $_values=[];		//хранит имена переменных которые должны быть определены при eval строки
    protected $_values_null=[]; //null имена переменных


public function __construct($string = null)
    {
			$dialect = array(
			
				'operators' => array(
					'+',
					'-',
					'*',
					'/',
					'^',
					'=',
					'<>',
					'!=',
					'<',
					'<=',
					'>',
					'>=',
					'like',
					//'clike',
					//'slike',
					'not',
					//'is',
					//'in',
					//'between',
					'and',
					'or',
					'null'
				)
			);
			
        $this->operators  = array_flip($dialect['operators']);
        $this->symbols    = $this->operators;
        $this->lexeropts  = [ 'allowIdentFirstDigit' => true];
        $this->parseropts = [];
        $this->quotes     = array("'" => 'string','"' => 'string','`' => 'ident');

        if (is_string($string)) {
            $this->initLexer($string);
        }
    }
    // }}}

protected    function initLexer($string)
    {
        // Initialize the Lexer with a 3-level look-back buffer
        $this->lexer = new Lexer($string, 3, $this->lexeropts);
        $this->lexer->symbols  =& $this->symbols;
        $this->lexer->comments =& $this->comments;
        $this->lexer->quotes   =& $this->quotes;
    }


protected function raiseError($message)
    {
        $end = 0;
        if ($this->lexer->string != '') {
            while ($this->lexer->lineBegin + $end < $this->lexer->stringLen
             && $this->lexer->string{$this->lexer->lineBegin + $end} != "\n") {
                $end++;
            }
        }

        $message = 'Parse error: ' . $message . ' on line ' .
            ($this->lexer->lineNo + 1) . "\n";
        $message .= substr($this->lexer->string, $this->lexer->lineBegin, $end);
        $message .= "\n";
        $length   = is_null($this->token) ? 0 : strlen($this->lexer->tokText);
        $message .= str_repeat(' ', abs($this->lexer->tokPtr -
            $this->lexer->lineBegin - $length)) . "^";
        $message .= ' found: "' . $this->lexer->tokText . '"';

        throw new Exception($message);
    }
    // }}}


    // {{{ isVal()
    /**
     * Returns true if current token is a value, otherwise false
     *
     * @uses  SQL_Parser::$token  R
     * @return  boolean  true if current token is a value
     */
protected function isVal()
    {
        return ($this->token == 'real_val' ||
        $this->token == 'int_val' ||
        $this->token == 'text_val' ||
        $this->token == 'null');
    }
    // }}}



    // {{{ isOperator()
    /**
     * Returns true if current token is an operator, otherwise false
     *
     * @uses  SQL_Parser::$token  R
     * @uses  SQL_Parser::$operators R
     * @return  boolean  true if current token is an operator
     */
protected function isOperator()
    {
        return isset($this->operators[$this->token]);
    }
    // }}}




    // {{{ getTok()
    /**
     * retrieves next token
     *
     * @uses  SQL_Parser::$token  W to set it
     * @uses  SQL_Parser::$lexer  R
     * @uses  Lexer::lex()
     * @return void
     */
protected function getTok()
    {
        $this->token = $this->lexer->lex();
        //echo $this->token . "\t" . $this->lexer->tokText . "#\n";
    }

protected function parseCondition()
{
        $clause = [];$operator="";

        while (true) 
		{
            // parse the first argument
            if ($this->token == 'not') 
				{
                	$clause['neg'] = true;
	                $this->getTok();
    	        }

            if ($this->token == '(') 
				{
                	$this->getTok();
	                $clause['args'][] = $this->parseCondition();
    	            if ($this->token != ')') 
						{
	        	            $this->raiseError('Expected ")"');
    		            }
                	$this->getTok();
            	}
			elseif ($this->token == 'ident') {$column = $this->parseIdentifier();}
			else 
				{
					$arg = $this->lexer->tokText;
					$argtype = $this->token;
					$clause['args'][] = array(
						'value' => $arg,
						'type'  => $argtype,
						'column'=>$column,
						"operator"=>$operator
					);
					$this->getTok();
	            }

            if (! $this->isOperator()) 
				{
                	// no operator, return
	                return $clause;
    	        }

            // parse the operator
            $op = $this->token;
            if ($op == 'not') 
				{
                	$this->getTok();
	                $not = 'not ';
    	            $op = $this->token;
        		} else {$not = '';}

            $this->getTok();
            switch ($op) {
                case 'is':
                    // parse for 'is' operator
                    if ($this->token == 'not') {
                        $op .= ' not';
                        $this->getTok();
                    }
                   // $clause['ops'][] = $op;
					$operator= $op;
                    break;
                case 'like':
                    //$clause['ops'][] = $not . $op;
					$operator=$not . $op;
                    break;
                case 'between':
                    // @todo
                    //$clause['ops'][] = $not . $op;
                    //$this->getTok();
                    break;
                case 'and':
                case 'or':
                    $clause['ops'][] = $not . $op;
                    continue 2;
                    break;
                default:
                   // $clause['ops'][] = $not . $op;
					$operator=$not . $op;
            }
            // next argument [with operator]

        }

        return $clause;
    }
    // }}}


    /**
     * [[db.].table].column [[AS] alias]
     */
protected function parseIdentifier()
    {
        $column = $this->lexer->tokText;
        $prevTok = $this->token;

        $this->getTok();

        if ($prevTok != 'ident' && $prevTok != '*') {
            $this->raiseError('Expected name');
        }


        return $column;
    }


    public function parse($string = null)
    {
		$this->initLexer($string);
		$tree = [];
	// get query action
		$this->getTok();
		//var_Dump($this->token);
		$tree = $this->parseCondition();
		
        if (! is_null($this->token)) { $this->raiseError('Expected EOQ'); }

        return $tree;
    }
    // }}}


/*
генерация строки для выполнения в eval
* $field_name - массив имен колонок таблицы
*/
public function create($struct,$field_name)
{
	$r=$this->tree($struct);
	$rez="";
	foreach (array_diff($this->_values, $field_name) as $v){
            $rez.= "throw new Exception('Переменная ($v) в условии не определена');\n";
    }
	return $rez." return ".$r.";";
}


/*
формирует строку которую можно вставить в eval и проверить условие
возвращает строку
*/
protected function tree($struct)
{
    $rez='';
    $i=0;
    foreach ($struct["args"] as $item){
        if (array_key_exists("args",$item))	{
            //рекурския
            $rez.=" (".$this->tree($item).") ".$struct['ops'][$i];$i++;
        }else{
            //связывающие логические операторы
            if (isset($struct['ops'][$i])){
                $op=$struct['ops'][$i];
            } else {$op="";}
            $this->_values[]=$item['column'];
            if ($item['type']=='text_val') 	{
                switch ($item['operator']){
                    case "like":
                        $rez.=' (false!=preg_match("/'.strtr($item['value'],['%' => '(.*?)', '_' => '(.)']).'/iu", $'.$item['column'].'))';
                        break;
                    case "not like":
                        $rez.=' (false===preg_match("/'.strtr($item['value'],['%' => '(.*?)', '_' => '(.)']).'/iu", $'.$item['column'].'))';
                        break;
                    default:{
                        //само сравнение - оператор = > < <> !=
                        $value='"'.$item['value'].'"';
                        switch($item['operator']){
                            case "=":$l="==0";break;
                            case "!=":
                            case "<>":
                                $l="!=0";break;
                            default: $l=$item['operator']."0";
                        }
                        $rez.=" (strnatcasecmp(\${$item['column']},$value){$l}) ".$op;
                    }
                }
            } elseif ($item['type']=='int_val' or $item['type']=='real_val') {
                if ($item['operator']=="=") {$item['operator']="==";}
                $rez.=" \${$item['column']}{$item['operator']}{$item['value']} ".$op;
            } elseif($item['type']=='null'){
                //значение null
                $this->_values_null[]=$item['column'];
                if ($item['operator']=="=") {$item['operator']="==";}
                $rez.=" \${$item['column']}{$item['operator']}{$item['value']} ".$op;
            }
            $i++;
        }
    }
return $rez;
}

}
