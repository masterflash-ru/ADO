<?php

namespace ADO\Service;

use ADO\Collection\Parameters;
use ADO\Exception\ADOException;
use ADO\Entity\Parameter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\SqlInterface;

// --------------------- COMMAND -параметрические зхапросы
class Command
{
    
    public $CommandText; // Возвращает или задает оператор SQL или хранимую  процедуру, выполняемую над источником данных.
    public $CommandType; // Возвращает значение, указывающее, как  интерпретируется свойство CommandText
    public $ActiveConnection; // объект соединения с базой, или строка соединения, тогда мы должны автоматом вызвать объект ADOConnection!
    public $Properties; // ПОКА не используется
    public $Parameters; //
    public $NamedParameters = false; // передавать только порядковые номера параметров, если true тогда передаются и имена
    
    private $index_in_sql_query = 0; // номер подзапроса в строке запроса, который будет исполняться, если запрос один, тогда 0
    private $sql_item = array(); // результат парсинга мультизапроса, если  запрос  один, тогда тут один элемент
    private $ParameterType=array();//технологический массив допустимых типов 
    
public function __construct ($CommandText = '')
    { // конструктолр
        $this->Parameters = new Parameters(); // коллекция параметров
        $this->CommandText = $CommandText;
        $this->sql_item = $this->sql_parser($CommandText); // разобрать мультизапрос, если есть
        $this->index_in_sql_query = 0;
        $this->CommandType=adCmdText;//для совместимости вносим это значение
        //технологический массив
        $this->ParameterType=[
            adEmpty,
            adSmallInt,
            adInteger,
            adSingle,
            adDouble,
            adCurrency,
            adError,
            adBoolean,
            adDecimal,
            adTinyInt,
            adUnsignedTinyInt,
            adUnsignedSmallInt,
            adUnsignedInt,
            adBigInt,
            adUnsignedBigInt,
            adBinary,
            adChar,
            adUserDefined,
            adDBDate,
            adDBTime,
            adDBTimeStamp
        ];
    }

public function Execute (&$RecordsAffected = 0, &$Parameters = NULL,   $Options = adCmdText)
    { /*  
     Выполняет  запрос,  оператор  SQL,  хранимую  процедуру  или  любую  другую  команду,  доступную  провайдеру.  В  основном  аналогичен
     методу  Execute()  объекта  Connection  (см.  выше).  Все  параметры  являются  необязательными.  Параметр  "Parameters"  представляет  собой
    КОЛЛЕКЦИЮ  параметров,  передаваемый  оператору  SQL  (не  для  выходных  параметров). 
    вначале проверим объект соединения с базой, если там строка
    соединения, тогда автоматом сгенерировать объект коннекта и
    переприсвоить*/
    if (! is_object($this->ActiveConnection)) { // имеем строку, генерируем объет
            $dsn = $this->ActiveConnection;
            $this->ActiveConnection = new Connection(); // новый экземпляр объекта коннекта
            $this->ActiveConnection->ConnectionString = $dsn; // строка соединения
            $this->ActiveConnection->Open(); // открыть соединение
    }
    //проверим на объект select,insert,update,delete  из ZF3
    if ($this->CommandText instanceof SqlInterface) {
        $adapter=$this->ActiveConnection->getZfAdapter();
       //преобразуем в строку SQL
        $sql    = new Sql($adapter);
        $this->CommandText=$sql->buildSqlString($this->CommandText);
    }

    $this->ActiveConnection->driver->NamedParameters = $this->NamedParameters; // флаг передачи параметров по номерам или по именам
    // проверим, парсили ли мы запрос или нет
        if (count($this->sql_item) < 1) {
            // нет, парсим
            $this->sql_item = $this->sql_parser($this->CommandText); // разобрать  мультизапрос,  если  есть
            $this->index_in_sql_query = 0;
        }
        
        // выполняем запрос
        if (is_null($Parameters)){
            $Parameters_ = $this->Parameters;
        } else {
            $Parameters_ = $Parameters;
        }
        
        if (!$Options & adExecuteNoCreateRecordSet  && $Options != adExecuteNoRecords) {
            // генерируем RS
            $rs = new RecordSet();
            $rs->ActiveCommand = $this;
            $rs->open();
            $RecordsAffected = $rs->RecordCount; // кол-во застронутых строк
            return $rs;
        }
        $RecordsAffected=0;
        // ПРОВЕРИМ СЛУЧАЙ, когда передан пустой запрос
        if (empty($this->sql_item)) {
            $this->sql_item[$this->index_in_sql_query] = "";
        }
        $rez = $this->ActiveConnection->Execute($this->sql_item[$this->index_in_sql_query], $RecordsAffected, $Options, $Parameters_);
        if (count($this->sql_item) > $this->index_in_sql_query + 1) {
            $this->index_in_sql_query ++; // переместиться к след. запросу, если есть
        }
        return $rez;
    }

public function CreateParameter ($Name, $Type, $Direction, $Size, $Value,    $Attributes = adParamNullable)
    { /*   
    Создаёт  и  возвращает  объект  Parameter  с  заданными  свойствами.  
    Метод  CreateParameter  не  добавляет  созданный  параметр  к  коллекции  Parameters.  
    Для  этого  следует  использовать  метод  Append(objParameter)  этой  коллекции.  
    Аргументы  метода  CreateParameter  (все  аргументы  необязательные):  
    Name  -  строка,  имя  параметра.  Type  -  целое  число  (long),  
    тип  данных  параметра  (строка,  число, булево и т.д.).
    Подробнее   -   см.   в   MSDN   значения   перечисления   DataTypeEnum,   а   также   свойство   Type   объекта   Parameter   в   данной   статье.
    Direction   -   целое   число   (long),   "направление"   параметра.   
    Возможные   значения:   
    adParamUnknown(0)   -   направление   параметра     неизвестно.   
    adParamInput(1)   -   по   умолчанию,   входной   параметр.   
    adParamOutput(2)   -   выходной   параметр.   
    adParamInputOutput(3)   -    параметр представляет собой и входной, и выходной параметр
    adParamReturnValue(4)  -  параметр  представляет  собой  возвращаемое  значение.  
    Size  -  целое  число  (long),  максимальная  длина  параметра  в  символах  или  байтах.  Value  -  Variant,  значение  параметра.  
    $Attributes  -  атрибуты */
    //проверим валидность
    if (!in_array($Type,$this->ParameterType)) {
        throw new ADOException($this->ActiveConnection, 17, 'Command:', array('Type='.$Type));
    }
    if (! ($Attributes & ( adParamSigned + adParamNullable + adParamLong))) {
        throw new ADOException($this->ActiveConnection, 17, 'Command:', array('Attributes='.$Attributes));
    }

    if ($Direction!=adParamUnknown && 
                $Direction!=adParamInput &&
                $Direction!=adParamOutput &&
                $Direction!=adParamReturnValue 
                ) {
        throw new ADOException($this->ActiveConnection, 17, 'Command:', array('Direction='.$Direction));
    }

    $p = new Parameter();
    $p->Name = $Name;
    $p->Type = $Type;
    $p->Direction = $Direction;
    $p->Size = $Size;
    $p->Value = $Value;
    $p->Attributes = $Attributes;
    return $p;
}
    
    // внутренняя функция которая парсит мультизапросы, возвращается массив с
    // отдельными запросами
private function sql_parser ($sql)
{
    $queries = array();
    $strlen = strlen($sql);
    $position = 0;
    $query = '';
    for (; $position < $strlen; ++ $position) {
        $char = $sql[$position];
        switch ($char) {
            case '-':
                if (substr($sql, $position, 3) !== '-- ') {
                    $query .= $char;
                    break;
                }
            case '#':
                while ($char !== "\r" && $char !== "\n" && $position < $strlen - 1){
                    $char = $sql[++ $position];
                }
                break;
  
            case '`':
            case '\'':
            case '"':
                $quote = $char;
                $query .= $quote;
                while ($position < $strlen - 1) {
                    $char = $sql[++ $position];
                    if ($char === '\\') {
                        $query .= $char;
                        if ($position < $strlen - 1) {
                            $char = $sql[++ $position];
                            $query .= $char;
                            if ($position < $strlen - 1){
                                $char = $sql[++ $position];
                            }
                        } else {
                            break;
                        }
                    }
                    if ($char === $quote){
                        break;
                    }
                    $query .= $char;
                }
                $query .= $quote;
                break;
            case ';':
                $query = trim($query);
                if ($query){
                    $queries[] = $query;
                }
                $query = '';
                break;
            default:
                $query .= $char;
                break;
        }
    }
    $query = trim($query);
    if ($query){
        $queries[] = $query;
    }
    return $queries;
}
        
}