<?php

namespace ADO\Exception;
use ADO\Connection;
use ADO\Entity\Collections;

class ADOException extends \RuntimeException
{
	public $errors; // коллекция ошибок ADO
	public static $ADOMessage;
	public function __construct ($connection = NULL, $code = 0, $source = 'not known', 	$m_a = array())
	{ /*
	   * $connection - ссылка на объект Connection, в его коллекцию будет
	   * записана ошибка перед выбросом исключения, если $connection не
	   * Connection, в коллекцию игнорируется запись $code - код $m_a -
	   * подстановочный текст в элементы вида %0 %1 если низкоуровневая ошибка
	   * провайдера, то предварительно в коллекцию $errors заносим все ошибки, а
	   * потом вызываем исключение throw new ADOException(Объект connection);
	   * при этом в само исключение записывается последний элемент коллекции
	   * ошибок
	   */

		if ($connection instanceof Connection) { 
            //  обработка ошибок ADO когда имеем соединение с базой т.е. имеем
		  // нормальны объект Connection
			if ($code) {
				// обычная обработка ADO, коды ошибок и сообщения в xml файле
				$text = self::GetADOMessage($code, $m_a); // получить текстовое сообщение об ошибке в зависимости от локали
				$connection->Errors->add(
											array('number' => $code,
                                                  'description' => $text, 
                                                  'source' => $source
                                                 )
										);
			} else { // более низкого уровня ошибка, берем последнюю из
					 // коллекции
				$code = $connection->Errors->Item[count($connection->Errors->Item) - 1]->number; // ошибка провайдера
				$text = $connection->Errors->Item[count($connection->Errors->Item) - 1]->description; // текст сообщения провайдера
			}
			$this->errors = $connection->Errors; // добавим в коллекцию ошибку// объекта Connection не имеем, обычные ошибки
		}		 
		else {
			
			if ($connection instanceof ADOException) {
                // на входе может быть экземпляр ADOException print_r($connection);
				$text = $connection->getMessage();
				$code = $connection->getCode();
				$this->errors = $connection->errors; // перепишем коллекцию, если она есть
			} else {
				$text = self::GetADOMessage($code, $m_a); // получить текстовое сообщение об ошибке в зависимости от локали
				$this->errors = new Collections();
				$this->errors->add(
						array('number' => $code, 
								'description' => $text, 
								'source' => $source)
								);
			}
		}
		// инициализировать родительский объект
		
		parent::__construct($text, $code);
	}



private  static function GetADOMessage ($code = 0, $m_a = array())
{ // для внутренних целей - получает текст сообщения по его номеру, из файла с сообщениями, в зависимости от локали
    if (empty(self::$ADOMessage)) {
        if (! file_exists(__DIR__ . '/../Data/' . ADO_LOCALE . '.xml')) {
            echo "Fatal error! File ".__DIR__ . '/../Data/' . ADO_LOCALE . '.xml not found!';
        } else {
            self::$ADOMessage = new \SimpleXMLIterator(__DIR__ . '/../Data/' . ADO_LOCALE . '.xml', NULL, true);
        }
    }
    
    $text = self::$ADOMessage->xpath("/message/msg [@id ='$code']");
    if (count($text)) {
        $text = $text[0]->__tostring();
    }else{
        $text = 'Unknown error in ADO system, code=' . $code;
    }
    
    foreach ($m_a as $k => $v) {
        $text = str_replace("%" . $k, $v, $text);
    }
    return $text;
}


}