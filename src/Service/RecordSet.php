<?php
// ----------------------------------- RecordSet
/*
9.8.17 - удалена FilterFind библиотека, ее место занял SQL_parser от PEAR (упрощенный), из-за простоты работы все встроено в RS


8.3.17 - исправлена функция Close - она кроме всего прочего вызывает одноименный метод в драйвере, нужна для освобождения буфера, например, после вызова процедуры в MySql
8.3.17 - введены функции для работы с внешними сущностями, используя гидратацию, можно так же вносить изменения в базе. RS использует внешний объект для работы
		с этими функциями 

20.12.2016 - перешли на ZF3, используется сессия, но используется нативные функции сессии, связано с многомерным массивом, а ZF3 сессии видимо не поддерживают прямого
			создания таких массивов, видимо нужно городить внешнее создание такого массива и потом уже запись в сессию ZF3 

14.2.16 - отключено клонирование PDOStatement $this->stmt. В php7 это вызывает крах


ОШИБКА! - при составном первичном ключе при записи в ключ тогоже значения генерируется INSERT в базу, должно быть UPDATE


2.12.14 - исправлена ошибка генерации BookMark элмента при перемотке RS назад


08.08.14 - исправлены ошибки связанные с граничными условиями буфера и обратной перемотки записей
4.08.14 - введена проверка, если в поле заносится тоже самое значение, тогда поле не модифицируется и не ставится влаг изменения! это экономит и память и щадит обращения в базу

19.06.14 - исправлена ошибка обработки удаления записи в буфере (без перечитывания буфера)
14.06.14 - исправлена ошибка записи в RS ID новой записи

6.05.14 - доработана выборка данных из драйвера - обрабатывается исключение, если данных невозможно получить

14.02.2014 доработан Update - после вставки новой записи заполняется поле с первичным ключем равным ID вставленой записи (но флаг изменения записи не устанавливается!)
	в записи добавлен флаг flag_deleting, true -запись физически удалена этим RS из базы, этот флаг заставляет перечитать буфер вновь при обращении к записям
	ВНИМАНИЕ! пока буфер не обновился кол-во записей в базе так же считается по старому и не обновляется!


03.04.2013
введено клонирование объектов, теперь RS клониуется со всеми потрохами, кроме соединения с базой
27.03.13 
исправлена GetRows - теперь возвращает верную структуру массива, если даже записей нет
*/
namespace ADO\Service;


use ADO\Service\Connection;
use ADO\Entity\DataColumn;
use ADO\Entity\DataColumns;
use ADO\Entity\Fields;
use ADO\Entity\Field;
use ADO\Extend\Sort;
use ADO\Extend\Parser;
use ADO\Extend\AdoXml;
use ADO\Exception\ADOException;
use ADO\Service\Command;
use \stdClass;

use ADO\Entity\EntityRepository;


class RecordSet
{
	/*
	 * объект работы с данными непосредственно
	 */
	public $PageCount; // всего страниц
	public $ActiveConnect; // объект connect
	public $ActiveCommand; // хранит объект command, еоторый породил данный  // объект, или пусто
	public $BOF; // true|false касаемо начала набора записей
	public $EOF; // true|false касаемо конца набор а записей
	 // public $Bookmark; //уникальный идентификатор записи, при
	// установке его в значение - перемещает указатель на прежнее  // состояние (заклатка)

	/*
	 * Значение свойстваEditMode отражает состояние буфера, используемого для
	 * создания и редактирова-ния записей. Оно используется, когда при выходе из
	 * режима редактирования выбран соответствующий метод (Update  илиCancelUpdate). 
	 0 Редактирование не выполняется. 1 Данные в текущей записи изменились, но сохранение еще не выполнялось. 
	 2 Данное значение свойство EditMode принимает после вызова метода AddNew. 
		 Оно показывает, что буфер копирования содержит еще не сохраненную новую запись. 
	 3 Текущая  запись была удалена.
	 */
	public $EditMode;

	// перегруженное сво-во MaxRecords, менять можно только до открытия объекта
	// private $_MaxRecords;

	public $RecordCount = 0; // кол-во записей в объекте recordSet
	public $Source; // сама строка подключения к базе или экземпляр объетка  // command()
	public $Status; // подробнее http://www.xtramania.com/Documentation/ADOxtra/Reference/Enums/RecordStatusEnum/
	public $Fields; // коллекция объектов Field
	public $Properties; // коллекция свойств объекта (пока пустоая коллекция)
	public $State; // состояние объекта 1-открытый, действительный, 0-закрытый

	public $RecordSetName; // имя данного рекорд сета (это в ADO NET) нужно для  генерации XML
	public $DataColumns; // коллекция объектов DataColumn

	
	/*кеш экземпляров EntityRepository*/
	protected $repositoryList=[];
	
	
	
	// перегруженное сво-ва
	protected $container = ['maxrecords' => NULL,   // указывает максимальное  кол-во записей которые  помещаются в объект  recordset, по умолчанию  10, если 0  тогда считать все записи  в кеш
											'sort' => '',	 // сортировка ПОСЛЕ чтения из базы данных, из "order by ID desc" указывается только "ID desc"
											'filter' => '',	 // Выборка записей в рекордсете по условию, работает только при $this->_MaxRecords =0
											'pagesize' => 10,	 // кол-во записей в одной странице Этот параметр обрабатывает перегрузка, чтобы пересчитать кол-во страниц при изменении $PageSize
											'absolutepage' => 0,	 // положение указателя страниц (по сути это номера страниц  1-....) пересчитывается всегда!!!! (перегружено!!!!)
											'bookmark' => '',	 // уникальная строка для записи в базе данных
											'cursorlocation' => NULL, 
											'cursortype' => NULL, 
											'locktype' => NULL, 
											'source' => NULL,			 // источник дaнных
											'absoluteposition' => NULL,	// абсолютный номер записи 1..
											
											];	

	private $stmt; // объект результата, в формате провайдера! хранит результат

	private $rez_array = []; // хранит массив результата, кол-во элементов  определеячется размером _MaxRecords

	// хранит старое значение $rez_array, когда мы редактируем записи, эти  значения нужны что бы в условии SQL  выбрать  верную запись, хранится не все, а только
	//  МОДИФИЦИРОВАННЫЕ записи, ключи идентичны
	private $old_rez_array = [];

	// точная копия $rez_array - нужна для возвращения при отмене или изменении
	// фильтра или сортировки
	private $temp_rez_array = array('sort' => [], 'filter' => []);

	private $AbsolutePosition_min_max = []; // хранит верхний и нижний номер $AbsolutePosition который находится в $rez_array (т.е. в кеше)
	private $columnCount; // кол-во колонок в таблице что бы не обращаться лишний раз в базу данных

	// хранит номер найденой записи в методе Find, нужно для реализации продолжения поиска
	private $Finding_record = NULL;

	public $Find_Criteria_hash = NULL; // хеш критерия поиска, если он  изменится,  значит это поводо начать искать с  самого  начала
	
	private $add_new_metod = false; // true когда выполняется метод AddNew -  нужен  для верной обработки кеша записей
	private $records_in_buffer = 0; // кол-во реальных записей расположенных в  буфере
									
	// имена колонок которые возвращает метод get_field_name(true/false)
	/*
	 * Array ( [id] => 0 [name] => 1 [value] => 2 [modul] => 3 [sysname] => 4
	 * [type_] => 5 )
	 */
	private $get_field_name_true = [];
	/*
	 * Array ( [0] => id [1] => name [2] => value [3] => modul [4] => sysname
	 * [5] => type_ )
	 */
	private $get_field_name_false = [];
	
	// массив имен полей
	private $columnNames=[];//имена колонок
	
	// хранит строку запроса для выборки, нужна для анализа возможности создания
	// SQL для записи/обновления через данный объект
	private $CommandText=NULL;
	
	// хранит сгенерированные иснтрукции update и insert, если запрос сложный то
	// в массиве хранятся FALSE
	private $tpl_sql_update_insert=array('update'=>false,'insert'=>false);
	private $primary_key=NULL;//
	
	private $RecordSetId = NULL; // внутренний идентификатор объекта, нужен для  организации временных файлов в процессе  работы
	
	private $flag_create_xsdxml = false; // хфлаг генерации в одном файле схемы и xml
	private $_create_xsdxml = NULL; // хранит экземпляр SimpleXmlInterator при генерации в одном файле схемы и xml
	
	//хранит контейнер сессии
	private $session;
	protected $_cache_where=[];
	protected $_cache_where1=[];
	
	//экземпдяр парсера SQL 
	protected $Parser;
	
	public function __construct ()
	{
		$this->PageCount = 0;
		$this->container['pagesize'] = 10;
		$this->container['maxrecords'] = 10;
		$this->container['cursorlocation'] = adUseClient; // положение курсора на строне сервера (не используется)
		$this->container['cursortype'] = adOpenForwardOnly; // тип курсора  (по  умолчанию  только  один проход  и  вперед)
		$this->container['locktype'] = adLockReadOnly; // тип блокировок  (по  умолчанию только  чтение)
		$this->columnCount = 0;
		$this->RecordSetId = md5(microtime()); // всегда уникальный
		$this->flag_create_xsdxml = false;
	}

	public function Open ($Source = NULL, $ActiveConnect = NULL,  $Options = adCmdText)
	{
		if ($this->State) throw new ADOException($this->ActiveConnect, 9, 'RecordSet:' . $this->RecordSetName, array('RecordSet'));
			
			// проверим Source: на входе либо текст запроса SQL, либо ссылка на объект Command
		if ($Source instanceof Command)  $this->container['source'] = $Source; // сохраним  объект  это  или  строка  запроса
		if (is_null($Source))  $this->container['source'] = NULL; // если ничего  нет на входу, так  же записываем NULL
		
		if ($this->ActiveCommand instanceof Command)
					 {
						$this->container['source'] = $this->ActiveCommand; // здесь и строка  запроса есть
					}
		
		// если входной параметр строка запроса, то генерируем объект command и его вносим в RecordSet
		if (is_string($Source)) 
			{ // строка запроса, создаем новый объект command
				$c = $Source; // строку сохраним если у нас имеется объект Command его записываем, иначе новый экземпляр
				$this->container['source'] = new Command(); // новый экземпляр
				$this->container['source']->CommandText = $c; // строка запроса
				// $this->CommandText=$c;//сохраним строку запроса, для возможного  анализа
			}
		
		// активное подключение указано?
		if (is_null($ActiveConnect) && $this->ActiveConnect instanceof Connection) 
				{ // нет не указано, если оно есть, то записываем его
		  		  if ($this->container['source'] instanceof Command)	 {$this->container['source']->ActiveConnection = $this->ActiveConnect;}
				} 
			else 
				{
					if ($ActiveConnect instanceof Connection) 
						{ // если тип соединения верный, записать его в недра RecordSet
							if ($this->container['source'] instanceof Command)	$this->container['source']->ActiveConnection = $ActiveConnect;
							$this->ActiveConnect = $ActiveConnect;
						}
						else
						{//вхрдной параметр в виде строки подключения к базе
							if (is_string($ActiveConnect)) 
									{//создаем новое подключение к базе
										$cn=new Connection();
										$cn->ConnectionString=$ActiveConnect;
										$cn->open();
										$this->container['source']->ActiveConnection =$cn;
									}
							//неверное обращение к объекту
							//else throw new ADOException(NULL, 6, 'RecordSet:' . $this->RecordSetName, array('RecordSet'));
						
						}
				}
		
		$this->ActiveCommand = $this->container['source'];
		$this->rez_array = []; // кеш результата (буфер обмена)
		$this->AbsolutePosition_min_max = array(0, 0); // верхний-нижний номер AbsolutePosition (нумерация с 1, если 0, значит не определено
		$this->BOF = true;
		$this->EOF = true;
		$this->AbsolutePage = 1;
		$this->RecordCount = 0;
		$this->EditMode = adEditNone;
		$this->State = 1; // объект успешно открыт
		
		$RecordsAffected = 0;
		if (is_null($this->container['source']))   return; // если мы просто  открыли без ничего, тогда  выход
						
		// выполним запрос к провайдеру
		$Parameters = NULL;
		if (is_null($Options))   $Options = adCmdText;
			// сделаем обращение в базу через объект command
		
		$a = $this->container['source']->Execute($RecordsAffected, $Parameters, $Options + adExecuteNoCreateRecordSet); // запрос  в  command,  а  он  вызывает  Execute  объекта  Command
		$this->stmt = $a['stmt'];
		
		$this->RecordCount = $RecordsAffected; // кол-во записей
		if ($RecordsAffected > 0)
			 {
			$this->EOF = false; // если записей >0 метку сонца поставить в false
			}
		$this->Fields = new Fields(); // коллекция полей
		$this->DataColumns = new DataColumns(); // коллекция полей кол-во колонок в результирущем наборе
		$this->rez_array[0] = [];
		$this->columnCount = $this->container['source']->ActiveConnection->driver->columnCount( $this->stmt);
		for ($i = 0; $i < $this->columnCount; $i ++) 
				{ // сделаем запрос провайдеру для полечения записи
					$ColumnMeta = $this->container['source']->ActiveConnection->driver->loadColumnMeta($this->stmt, $i);
					$ColumnMeta["Ordinal"]=$i;
					if (isset($ColumnMeta['table']))
						{$this->RecordSetName = $ColumnMeta['table'];} // имя  объекта  равно  имени  таблицы  с  выборкой
						else {$this->RecordSetName = "RecordSet";} // имя неизвестно
					
					$field = new Field($ColumnMeta);
					$field->set_parent_recordset($this); // укажем объекту Field  родительский RecordSet,  что бы при  изменении в полях  вызывались функции  рекордсета
					$this->Fields->Add($field); // отправим в коллецию
				 
				 
					// ---------------------------------------------------------данный
						 // раздел в зачаточном состоянии!!!!!!!!!!!!!
						 // генерируем коллекцию DataColumn
					$DataColumn = new DataColumn($this->Fields->Item[$i]->Name, $ColumnMeta);
					$this->DataColumns->Add($DataColumn);
					$this->rez_array[0][$i] = NULL;
			}
		//флаги изменения записей в буфере (ДО ОТПРАВКИ В БАЗУ)
		$this->rez_array[0]['status'] = array(
																'flag_delete' => false, 				//флаг удаления записи
																'flag_change' => false, 			//флаг модифицированной записи
																'flag_new' => false, 					//новая запись
																'flag_canceled' => false,			//отмена всех изменений
																'BookMark' => "",						//строка закладки
																'preserveptatus' => false,			//флаг отмены изменения этих флагов
																'errors' => NULL,						//коллекция ошибок в этой записи
																'flag_deleting'=>false				//true - запись удалена в базе, но в буфере еще болтается, эту запись нужно исключить из поиска и выдачи
																);
		// $this->PageSize=$this->container['pagesize'];//инициализировать
		// кол-во страниц
		
		$this->jmp_record(1); // внести первую запись
		
		// срхраним массивы полей что бы не обращаться по многу раз
		$this->get_field_name_true = $this->get_field_name(true);
		$this->get_field_name_false = $this->get_field_name(false);
		
		if (empty($this->RecordCount)) $this->rez_array = []; // если нет записей, обнуляем буфер
	
	}

	public function Move ($NumRecords, $start = adBookmarkCurrent)
	{ // перейти к записи номер (по умолчаниб от текущей $NumRecords - смещение, если <0 тогда переходить назад, иначе вперед
		if (is_string($start)) 
							{ // стартовая позиция указана в виде  закладки, пляшем от нее
								$this->find_book_mark($start); // перейти к закладке
								$this->jmp_record(
								$this->container['absoluteposition'] + $NumRecords); // переходим теперь к указаной записи, получается по отношению к закладке
							} 
				else
			 { // обработка обычная, старт указан в виде числа
			switch ($start) {
				case adBookmarkFirst:
					{ // от первой записи
						$this->jmp_record($NumRecords + 1);
						break;
					}
				case adBookmarkLast:
					{ // от последней
						$this->jmp_record($this->RecordCount + $NumRecords);
						break;
					}
				default:
					{
						$this->jmp_record(
								$this->container['absoluteposition'] +
										 $NumRecords);
					} // по умолчанию от текущей записи
			}
		}
	}

	public function MoveFirst ()
	{ // перейти к первой записи
		$this->jmp_record(1); // абсолютный указатель записи 1...
	}

	public function MoveLast ()
	{ // получить последнюю запись
		$this->jmp_record($this->RecordCount);
	}

	public function MoveNext ()
	{ // получить след. запись
		$this->jmp_record($this->container['absoluteposition'] + 1); // внести  первую запись
	}

	public function MovePrevious ()
	{
		$this->jmp_record($this->container['absoluteposition'] - 1);
	}

	private function jmp_record ($NewAbsolutePosition)
	{ // переход на абсолютную запись с номером AbsolutePosition, 1.....
		/*
		 * на выходе $this->rez_array и результат работы процедуры
		 * $this->rez_array2Field а так же флаги начала/конца записи
		 */
		if ($this->columnCount==0) {return;}
		// проверим флаг изменения текущей записи и если не пакетный режим сразу
		// обновим в базе данных
		$this->Finding_record=0;//очистим номер записи на которой стоит RS после поиска
		if ($this->container['absoluteposition'] >=
				 $this->AbsolutePosition_min_max[0] &&
				 $this->container['absoluteposition'] <=
				 $this->AbsolutePosition_min_max[1] &&
				 $this->AbsolutePosition_min_max[0] > 0 &&
				 $this->AbsolutePosition_min_max[1] > 0 &&
				 $this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status']['flag_change'] &&
				 $this->container['locktype'] != adLockBatchOptimistic && // если
				! empty($this->stmt)
				)
				 { // echo
				 // $this->container['absoluteposition'].'->';print_r($this->AbsolutePosition_min_max);
				// echo 'Запись изменена';
				   $this->Update(); // внести изменения
				}
		// проверим по типу курсора, можно ли переходить назад
		if ($this->container['cursortype'] == adOpenForwardOnly &&  $NewAbsolutePosition < $this->container['absoluteposition'])
						throw new ADOException($this->ActiveConnect, 2,'RecordSet:' . $this->RecordSetName);
				
		 for ($i = 0; $i < $this->columnCount; $i ++) 
		 		{ // очистить поля
					$this->Fields->Item[$i]->set_value(NULL); // установить  в null  значения  полей
				}
				if ($this->RecordCount == 0) { // если записей 0, тогда выходим
					$this->BOF = true;
					$this->EOF = true;
					return; // выходим, т.к. нет записей
				}
				
				if ($NewAbsolutePosition < 1) 
				{
				$NewAbsolutePosition=1;//нижний предел
					$this->BOF = true;
					return;
				}
				if ($NewAbsolutePosition > $this->RecordCount)
					{ // $this->EOF=true;
					   $NewAbsolutePosition=$this->RecordCount;//верхний предел превышен, ставим метку конца и выходим
					$this->container['absoluteposition'] = $NewAbsolutePosition; // передвинуть указатель вперед
					$this->EOF = true;
					return; // выходим, т.к. мы вышли за пределы
					}
				
				
				/*if ($this->RecordSetName=='queue_message') 
						{
							echo "NewAbsolutePosition=$NewAbsolutePosition\n";
							print_r($this->rez_array);
							
						}*/
				// пересчитать по позиции номер страницы и номер записи в странице
				$this->container['absolutepage'] = floor(  ($NewAbsolutePosition - 1) /  $this->container['pagesize']) + 1; //проверяем кеш, попали в него или нет
				if (				// условие при котором мы попадем в кеш
				$NewAbsolutePosition >= $this->AbsolutePosition_min_max[0] && // проверим границы кеша касаемо  номера  новой  позиции
						$NewAbsolutePosition <=  $this->AbsolutePosition_min_max[1] &&
						 $this->AbsolutePosition_min_max[0] > 0 && // границы  кеша  касаемо  конечных  записей
						$this->AbsolutePosition_min_max[1] > 0 &&
						 (count($this->rez_array) <=
						 $this->container['maxrecords'] &&
						 $this->container['maxrecords'] > 0 ||
						 $this->container['maxrecords'] == 0) && // границы  кеша,  если  он  ограничен  касаемо  объема  его  и  кол-ва  записей  в  нем  уже  имеющихся
						isset( $this->rez_array[$NewAbsolutePosition -  $this->AbsolutePosition_min_max[0]]) &&		//эта запись существует к буфере
						!$this->rez_array[$NewAbsolutePosition -  $this->AbsolutePosition_min_max[0]]['status']['flag_deleting']		//запись не удалена физически в базе (этим RS)
					)   // если  добавили  новую  запись  исскусствено  расширяем  границу  кеша  но  реальной  записи  может  не  быть,  поэтому  если  ее  нет,  промах  кеша
					   
						{
						   // если в кеше есть такая запись, то просто  обработаем ее и все
							$this->rez_array2Field( $this->rez_array[$NewAbsolutePosition - $this->AbsolutePosition_min_max[0]]); // если  указатель  не  вначале  исполнить  запрос  и  перемотать  в  начало  print_r($this->rez_array);exit;
							$this->container['absoluteposition'] = $NewAbsolutePosition; // передвинуть указатель
							$this->set_status(); // установить статус в рекордсете
							if ($this->container['absoluteposition'] > $this->RecordCount)  $this->EOF = true; else  $this->EOF = false;
							if ($NewAbsolutePosition == 0)  $this->BOF = true;   else  $this->BOF = false;
							$this->records_in_buffer = count($this->rez_array); // записать реальное кол-во записей в буфере echo $this->records_in_buffer.' ';
							return;
						}
				// промах кеша, считаем записи
				// $this->rez_array=[];//сбросить
				
				if ($this->container['maxrecords'] > 0)  $Recorditem = $this->container['maxrecords']; // указан  максимум  записей  в  кеше
			   				 else   $Recorditem = $this->RecordCount; // по умолчанию грузим все проверим допустимость верхний предел
			   // скррректируем $Recorditem чтобы не выйти за пределы (краевые условия)  крайнее условие по максимальной записи при переходах
				if ($Recorditem > $this->RecordCount)   $Recorditem = $this->RecordCount;
					
			   
				// вперед
				if ($Recorditem + $NewAbsolutePosition > $this->RecordCount &&  $NewAbsolutePosition > $this->container['absoluteposition'])
							$Recorditem = $this->RecordCount - $NewAbsolutePosition + 1;

				// крайнее условие по минимальной записи при переходах назад
				if ($Recorditem > $NewAbsolutePosition &&  $NewAbsolutePosition < $this->container['absoluteposition']) $Recorditem = $NewAbsolutePosition;
					
					// переход вперед? ИЛИ текущая запись уже удалена в базе?
					//дополнительная проверка что бы не вылетели за пределы буфера isset($this->rez_array[$NewAbsolutePosition -  $this->AbsolutePosition_min_max[0]])
				if ($NewAbsolutePosition > $this->container['absoluteposition'] || (isset($this->rez_array[$NewAbsolutePosition -  $this->AbsolutePosition_min_max[0]]) && $this->rez_array[$NewAbsolutePosition -  $this->AbsolutePosition_min_max[0]]['status']['flag_deleting'])) 
						{ // считать  записи  в  кеш
						$rez_array = [];
						// передвинуть указатель вначале проверим, текущая запись была изменена или это новая запись?
						// проверяем все записи в памяти, т.к. полностью перегружаем кеш пробежим по буферу (кешу) и сохраним измененные записи
						if (! empty($this->stmt) && ! $this->add_new_metod) 
							{
								$this->container['source']->ActiveConnection->driver->stmt_data_seek( $this->stmt, $NewAbsolutePosition ); 
							}
						for ($i = 0; $i < $this->records_in_buffer; $i ++) 
								{ // echo $NewAbsolutePosition.':'.$i."\n";
								if (count( array_intersect( array('flag_change', 'flag_new', 'flag_delete', 'preserveptatus'),  array_keys( $this->rez_array[$i]['status'],  true))) > 0)
										 { // да,  была  модификация,  сохраним  во  временный  файл
											$file_name = md5($i . microtime()); // имя временного файла
											if (empty($this->old_rez_array[$i])) {$this->old_rez_array[$i] =$this->rez_array[$i];}//на всякий случай
											file_put_contents(sys_get_temp_dir() ."/". $file_name,  serialize( array($this->old_rez_array[$i], $this->rez_array[$i])));
											// индекс формируется номер_записи + номер_записи_в_начале_буфера -1
											$_SESSION['ADORecordSet'][$this->RecordSetId][$i + $this->AbsolutePosition_min_max[0] - 1] = $file_name;
										}
							
								} 
							  // непосредственно считать в кеш записи

	
							for ($i = 0; $i < $Recorditem; $i ++) 
								{
								// проверим есть ли во временных файлах данная
								// запись, если  есть, грузим из этого файла, иначе из БД
								if (isset($_SESSION['ADORecordSet'][$this->RecordSetId][$i +$NewAbsolutePosition - 1])) 
												{ // имеется,грузим ее
												$a = unserialize(file_get_contents(sys_get_temp_dir() .$_SESSION['ADORecordSet'][$this->RecordSetId][$i + $NewAbsolutePosition - 1]));
												$rez_array[$i] = $a[1];
												$this->old_rez_array[$i] = $a[0];
												// считали в память, временный файл уже не нужен,
												// удалим его
												unlink( sys_get_temp_dir() . $_SESSION['ADORecordSet'][$this->RecordSetId][$i + $NewAbsolutePosition - 1]);
												// удалим ссылку на этот файл из сесии
												unset( $_SESSION['ADORecordSet'][$this->RecordSetId][$i + $NewAbsolutePosition - 1]);
												} 
										else 
												{ // грузим из базы, и сбрасываем все флаги
												 // если загрузка не из базы, то обнуляем все, это нужно импорте из XML, например
												if (! empty($this->stmt) && ! $this->add_new_metod) 
															{
																//ловим исключение, если онор есть, значит вернуть нечего, получается что вставляли записи, а драйвер вернул кол-во затронутых затронутых записей)
																//но затронытые записи в БАЗЕ, реально данных нет!, поэтому прерываем загрузку данных и все сбрасываем!
																try
																	{
																		$rez_array[$i] = $this->container['source']->ActiveConnection->driver->fetchNext($this->stmt); // считать
																	// записи
																		$rez_array[$i]['status'] = array(
																								'flag_delete' => false, 
																								'flag_change' => false,
																								'flag_new' => false, 
																								'flag_canceled' => false,
																								'flag_delete' => false, 
																								'errors' => NULL,
																								'flag_deleting'=>false, 
																								'preserveptatus' => false,
																								'BookMark' => sprintf("%u", crc32(serialize($rez_array[$i])))
																								); // атрибуты  записей
																	}
																	catch (PDOException $e)
																		{
																			//прерываем цикл, делаем пустую запись
																		$Recorditem=1;
																			$this->RecordCount=0;
																			$rez_array[0]['status'] = array(
																											'flag_delete' => false, 
																											'flag_change' => false,
																											'flag_new' => false, 
																											'flag_canceled' => false,
																											'flag_delete' => false, 
																											'errors' => NULL,
																											'flag_deleting'=>false, 
																											'preserveptatus' => false,
																											'BookMark' =>NULL
																											); // атрибуты  записей
																			
																			}
																} 
												 // фактически добавление новой записи, добавляем новую
												// нулевую запись с флагом новая запись
											 		   else 
														{ // echo $i.' --'.$NewAbsolutePosition;
														$rez_array[$i] = array_fill(0, $this->columnCount, NULL);
														$rez_array[$i]['status'] = array(
																									'flag_delete' => false, 
																									'flag_change' => false,
																									'flag_new' => true, 
																									'flag_canceled' => false,
																									'flag_delete' => false, 
																									'errors' => NULL, 
																									'flag_deleting'=>false, 
																									'preserveptatus' => false, 
																									'BookMark' => sprintf("%u", crc32(md5($i . microtime())))
																									); // атрибуты  записей
				   										 }
				
												}
		  				 			 } // конец for ($i=0;$i<$Recorditem;$i++)
								$this->rez_array = $rez_array;
								// print_r($this->rez_array);
								// установим метки номеров записей в кеше
								$this->AbsolutePosition_min_max[0] = $NewAbsolutePosition; // вначале  запись $AbsolutePosition
								$this->AbsolutePosition_min_max[1] = $NewAbsolutePosition +   $Recorditem - 1; // в конце номер последней
					
								if (! $this->EOF)  $this->jmp_record($NewAbsolutePosition); // рекурсивно обратимся, т.к. кеш уже заполнен
								return;
			  	}// if ($NewAbsolutePosition > $this->container['absoluteposition']) 
				
				
				// переход назад?
				if ($NewAbsolutePosition < $this->container['absoluteposition'])
						 { 
							// считать  записи  в  кеш  print_r($_SESSION['ADORecordSet'][$this->RecordSetId]);
							  if (!empty($this->stmt) && !$this->add_new_metod)  
							  		{
										//echo "позиция:".$NewAbsolutePosition."; stmt->_row_number:".$this->stmt->_row_number."\n";
										$this->container['source']->ActiveConnection->driver->stmt_data_seek($this->stmt, $NewAbsolutePosition );
										
									}
							$rez_array = [];// новая  запись? проверяем все записи в памяти, т.к. полностью перегружаем кеш
							// foreach ($this->rez_array as $i=>$rez_array_)
							for ($i = 0; $i < $this->records_in_buffer; $i ++) 
							{ // echo $NewAbsolutePosition.':'.$i."\n";
									if (count( array_intersect( array('flag_change', 'flag_new',  'flag_delete', 'preserveptatus'), array_keys( $this->rez_array[$i]['status'],  true))) > 0) 
										{ // да,  была  модификация,  сохраним  во  временный  файл
											$file_name = md5($i . microtime()); // имя временного  файла
											file_put_contents(sys_get_temp_dir() . $file_name,  serialize( array($this->old_rez_array[$i], $this->rez_array[$i])));
											// echo '-';
											$_SESSION['ADORecordSet'][$this->RecordSetId][$i +  $this->AbsolutePosition_min_max[0] - 1] = $file_name;
										}
							}
							// перешли на запись  $NewAbsolutePosition-$Recorditem-1, теперь  считаем в кеш $Recorditem записей  
							//если мы будем считывать и самую первую запись,  тогда нужно это  учесть!!!!!
							for ($i = 0; $i < $Recorditem; $i ++) 
									{
									// проверим есть ли во временных файлах данная
									// запись, если
									// есть, грузим из этого файла, иначе из БД
								if (isset($_SESSION['ADORecordSet'][$this->RecordSetId][$i + $NewAbsolutePosition - 1]))
										 { // имеется, грузим ее
										$a = unserialize(file_get_contents(sys_get_temp_dir() . $_SESSION['ADORecordSet'][$this->RecordSetId][$i + $NewAbsolutePosition - 1]));
										$rez_array[$i] = $a[1];
										$this->old_rez_array[$i] = $a[0];
										unlink( sys_get_temp_dir() . $_SESSION['ADORecordSet'][$this->RecordSetId][$i + $NewAbsolutePosition - 1]);
										// удалим ссылку на этот файл из сесии
										unset( $_SESSION['ADORecordSet'][$this->RecordSetId][$i + $NewAbsolutePosition - 1]);
										 }
								 else 
								 		{ // грузим из базы, и сбрасываем все флаги
										//print_r($this->stmt->fetch ( PDO::FETCH_NUM ));
											 if (! empty($this->stmt)) $rez_array[$i] = $this->container['source']->ActiveConnection->driver->fetchNext( $this->stmt); // считать   запись
											$rez_array[$i]['status'] = array('flag_delete' => false, 
																						'flag_change' => false, 'flag_new' => false, 
																						'flag_canceled' => false, 'errors' => NULL, 
																						'preserveptatus' => false, 
																						'flag_deleting'=>false, 
																						'BookMark' => sprintf("%u", crc32(serialize($rez_array[$i])))
																					); // атрибуты
			  							  }
									}//  for ($i = 0; $i < $Recorditem; $i ++) 
		   					 $this->rez_array = $rez_array;
							// установим метки номеров записей в кеше
							$this->AbsolutePosition_min_max[0] = $NewAbsolutePosition ;//- 	$Recorditem + 1; // в начале запись $AbsolutePosition
							 $this->AbsolutePosition_min_max[1] = $NewAbsolutePosition+$Recorditem; // в  конце  номер  последней
							$this->container['absoluteposition']=$NewAbsolutePosition;
							if (! $this->BOF)  $this->jmp_record($NewAbsolutePosition); // рекурсивно обратимся,т.к. кеш уже заполнен
					return;
				}
			}

 public function AddNew ($Fields = NULL, $Values = NULL)
	{ 
	/*  начать  ввод  новой  записи,  предварительно  надо  сделать  запрос  для  получения  коллекции  полей  
	$Fields  - имя  поля или  массив  полей  $Values  -  значение  или  массив  значений,  
	если  есть  параме тры то  запись  производится  сразу,  иначе  запоминается 
/*
делаем точную копию для нового элемента $this->rez_array на основе текущего элмента, и все обнуляем там
*/
	if ($this->container['cursortype'] == adOpenForwardOnly) throw new ADOException($this->ActiveConnect, 3,  'RecordSet:' . $this->RecordSetName);
	// переходим в конец, что бы при добавлении записи  она стала последней
	$this->add_new_metod = true;
	// $this->MoveLast();
	// создаем структуру с колонками таблицы
	$this->RecordCount ++; // увеличить кол-во записей на 1
	$new = array_fill(0, $this->columnCount, NULL);
	// создаем флаги для данной записи
	 $new['status'] = array('flag_change' => false, 
	 									'flag_new' => true,
										'flag_canceled' => false, 
										'flag_delete' => false, 
										'preserveptatus' => false, 
										'errors' => NULL, 
										'flag_deleting'=>false,
										'BookMark' => sprintf("%u",  crc32($this->RecordCount . microtime()))
										);
	   // array_push ($this->old_rez_array,$new);
	   array_push($this->rez_array, $new);
		$this->Find_Criteria_hash=NULL;	//сбросить флаг поичка, что бы если что искать с самого начала м учитывать новую запись
	  $this->AbsolutePosition_min_max[1] ++;
	  // если это совсем первая запись, то надо имитировать верные краевые границы буфера обмена
	   if (empty($this->AbsolutePosition_min_max[0])) 
	   	{
			$this->EOF = false;
			$this->AbsolutePosition_min_max[0] = 1;
		}
	   $this->MoveLast(); // переместиться в конец, на новую запись

	  if (! empty($Fields)) 
	  		{ // задано поле/ля
				$f = $this->get_field_name_true; // получить  ассоциативный  массив с  именами полей  print_r($f);
				if (is_array($Fields)) 
					{ // массив полей print_r($Fields);
					foreach ($Fields as $k => $Field_) 
						{
							if (isset($Values[$k]))$this->Fields->Item[$f[$Field_]]->Value = $Values[$k];
									else  $this->Fields->Item[$f[$Field_]]->Value = NULL;
						}
					} 
					else  $this->Fields->Item[$f[$Fields]]->Value = $Values;
				   // удалим флаг изменения записи! этот флаг устанавливается т.к. меняем вроде существующую запись, но это не так!
			   $this->rez_array[$this->container['absoluteposition'] -
			   $this->AbsolutePosition_min_max[0]]['status']['flag_change'] = false;
			   $this->rez_array[$this->container['absoluteposition'] -$this->AbsolutePosition_min_max[0]]['status']['flag_new'] = true;
				if (! empty($this->stmt))  $this->Update(); // сразу  записать,  если  конечно  есть канал  для записи, если его  нет, тогда  игнорируем
			}//if (! empty($Fields)) 
 // если мы хотим пакетом менять, то  просто добавляем пустую запись и  все, метод UpdateBatch
	 $this->set_status();
	 // перепишем данные что бы при добавлении нового элемента сохранился  старый 
	 //$this->old_rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]=$this->rez_array[$this->container['absoluteposition']- $this->AbsolutePosition_min_max[0]];
	 $this->add_new_metod = false;
 }

 public function Update ($Fields = NULL, $Values = NULL)
{ // обновление текущей записи
  if (! empty($Fields)) 
  		{ // задано поле/ля
		 // if  ($this->container['cursortype']==adOpenForwardOnly)  throw new ADOException($this->ActiveConnect,5,'RecordSet:'.$this->RecordSetName);
	   $f = $this->get_field_name_true; // получить  ассоциативный массив с  именами полей
	   if (is_array($Fields)) 
	   				{ // массив полей
					 foreach ($Fields as $k => $Field_) 
					 		{
								if (isset($Values[$k])) $this->Fields->Item[$f[$Field_]]->Value = $Values[$k];
							}
					} 
				else   $this->Fields->Item[$f[$Fields]]->Value = $Values;
		  }
	if ($this->AbsolutePosition_min_max[0] == 0 ||  $this->AbsolutePosition_min_max[1] == 0) return;
   // проверим флаги модификации, если нет модификации, просто выходим
	if (count( array_intersect( array('flag_change', 'flag_new', 'flag_delete'),  array_keys($this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status'],  true))) == 0)  return;
	// print_r($this->rez_array);
	$new = $this->rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]];

	// когда создаем новую запись, то в $this->old_rez_array  ничего нет,  просто загрузим туда копию из $this->rez_array, типа новая запись это исключит предупреждение о
	// несуществующем массиве
   if (empty($this->old_rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]))
						   $old = $new;
				   else
						  $old = $this->old_rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]];
	// echo "\n\n\nСтатус записи, от нее зависит тип сгенерированного SQL! ";
  
   $status = $new['status']; // статус  записи (новая/измененная)
   unset($new['status']);
   unset($old['status']);
 

 $sql = $this->container['source']->ActiveConnection->driver->create_sql_update(
 																							 $this->stmt, array_combine( $this->get_field_name_false, $old), 
																								array_combine( $this->get_field_name_false, $new), $status); // сгенерировать
 $RecordsAffected = 0;
 try {
		$this->container['source']->ActiveConnection->Execute($sql['sql'], $RecordsAffected, adExecuteNoRecords, NULL); // просто исполинть  и все
		// в случае удачи очищаем флаги модификации
		
		
		if (! $this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status']['preserveptatus']) 
		 		{ // если установлен флаг запрета модификации флагов, то не изменять их, иначе заменить
						$this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status']['flag_change'] = false; // сбросить флагизменениязаписи
						$this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status']['flag_new'] = false; // сбросить флаг новой записи
						$this->rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]]['status']['flag_canceled'] = false; // флаг  отмены  записи
						$this->rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]]['status']['flag_delete'] = false; // флаг удаления
	
					//проверим удаляли-ли мы зяпись, если да, ставим специальный флаг что запись удалена в базе
					if ($this->rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]]['status']['flag_delete'])
										$this->rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]]['status']['flag_deleting']=true;

					//проверяем первичный ключ, если он есть, получаем последний ID записи и вставим в массив с полем первичного ключа
						//print_r($this->rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]]);
						if (!is_null( $sql['primary_key_number_field'])) 
							{
								//получить ID вставленой записи
								$id=$this->container['source']->ActiveConnection->get_last_insert_id();//echo '<pre>';print_r($sql);
								$this->rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]][$sql['primary_key_number_field']]=$id;
								$this->rez_array2Field( $this->rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]]);
							}
				}
	 } 
	 	catch (ADOException $e) 
				{ 
					// если  возникла  ошибка,  сохраним  ее  в  запись  RS  где  она  произошла  и  выполним  еще  раз  исключение  для  отлова  ее  дальше
					$this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status']['errors'] = $e;
					throw new ADOException($e);
				}
	 // удалить старое значение для текущей записи
	unset( $this->old_rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]]);
   // $sql=$this->container['source']->ActiveConnection->driver->create_sql_insert ($this->stmt,array_combine($this->get_field_name_false, $new));
   //сгенерировать sql для update echo $sql;
   $this->set_status();
	}


 public function UpdateBatch ($AffectRecords = adAffectAllChapters, $PreserveStatus = false,  $MultiInsert = false)
	  { 
	  	/*   пакетное  изменение  всех  новых  записей  
		новые  записи  хранятся  в  памяти  и  во  временных  файлах,  если  перематывали  
		рекордсет,  имена  файлов  -  массив  $_SESSION['ADORecordSet'][$this->RecordSetId]  $MultiInsert  -  false  -  
		просто  перебирает  записи  и  обновляет  по  одной,  
		true  -  для  вставки  генерируется  мульти  insert  (для  уменьшения  запросов  в  базу)  при  этом  
		ошибка  SQL  будет  только  одна,  она  выпадает  в  исключении  и  не  записывается  в  сами  записи  RS!  
		используются  константы  в  качестве  входных  параметров  
		const  adAffectCurrent=1;//удаление/обновление  текущей  записи  
		const  adAffectGroup=2;//удаление/обновление  все  записи  удовлетворяющие  фильтру  или  указаным  закладкам,  если  они  не  установлены,  тогда  ничего  не  удаляем  
		const  adAffectAll=3;//удаление/обновление  все  записи  удовлетворяющие  фильтру  (закладкам)  если  они  указаны,  если  ничего  не  указано,  обрабатывает  все  записи  
		adAffectAllChapters=4;//все */
	   $sql_s = []; // результат   обработкивпровайдере(массив массивов)
		if ($AffectRecords == adAffectCurrent) 
						{ // удаление  только  текущей  записи
							if (isset($this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status']))	 $this->rez_array[$this->container['absoluteposition'] -
							$this->AbsolutePosition_min_max[0]]['status']['preserveptatus'] = (boolean) $PreserveStatus;
							$this->Update(); // просто  обновим  текущую  запись  и  все
							return;
						   } // adAffectCurrent
		 if ($AffectRecords == adAffectGroup) 
		 				{ // такая  обработка  пока  доступна  когда  загружены  все  записи!!!!
							 if ($this->container['maxrecords'] > 0) 
							 				{
												throw new ADOException($this->ActiveConnect, 7,  'RecordSet:' . $this->RecordSetName,  array( 'RsFilter()'));
											 } // ошибка, т.к. не все записи загружены проерим наличие фильтра, если он пуст, тогда ничего не делаем, выходим
							 if (empty( $this->container['filter']))  return;
							 // фильтр не пуст, значит в буффере уже отобранные записи, их просто обрабатываем и все
						}
		 if ($AffectRecords == adAffectAll && $this->container['maxrecords'] >  0)
		 				 { /* такая обработка пока доступна когда загружены все записи!!!! 
						 проерим наличие фильтра, если он пуст, тогда удаляем все записи фильтр не пуст, 
						 значит в буффере уже отобранные записи, их просто обрабатываем и все*/
							throw new ADOException($this->ActiveConnect, 7, 'RecordSet:' .$this->RecordSetName, array('UpdateBatch()'));
							} 
							// ошибка,  т.к.  не  все  записи  загружены
	$this->MoveFirst();
	if (! $MultiInsert) 
			{ // стандартная  обработка,  обновление  метобом  перебора  всех  записей  в  RS
				$ADOException = NULL;
				while (! $this->EOF) 
						{ // обновляем,  если  ошибка  то  запишем  ееtry
							try {
									 if (isset( $this->rez_array[$this->container['absoluteposition'] -$this->AbsolutePosition_min_max[0]]['status']))
												$this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status']['preserveptatus'] = (boolean) $PreserveStatus;
								   $this->Update();
								  } catch (ADOException $e) 
								  				{ // если возникла ошибка, сохраним ее в запись RS где она произошла и выполним еще раз исключение 
												//для отлова ее дальше
												$this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status']['errors'] = $e;
													if (is_null(	$ADOException)) 
																{
																	$ADOException = $e; // первый элемнт будет основным и внутри собираем коллекцию
																	$ADOException->errors = new ADO_Collections(); // объект  -коллекция ошибок
																}
																 else 
																 {
																	 $ADOException->errors->add(array(
							 'number' => $e->getCode(), 
							 'description' => $e->getMessage(), 
							 'source' => "RecordSet")
																														);// $source
																	 }
													}
									$this->set_status();
									$this->MoveNext();
					}
				 // если  были  ошибки,  генерируем  исключение
	  		  if (is_object( $ADOException))  throw new ADOException($ADOException);
				return;
		}//if (! $MultiInsert) недокументированая обработка

		 foreach ($this->rez_array as $key => $rez_array) 
		 					{ 
								// пробегаем  по  всме  записям  в  базе  и  ищем  новые
							 if (($rez_array['status']['flag_new'] || $rez_array['status']['flag_change']) &&
							 		! $rez_array['status']['flag_canceled'] && ! $rez_array['status']['flag_delete']) 
									{
										// меняем флаг сохранения статуса
										$this->rez_array[$key]['status']['preserveptatus'] = (boolean) $PreserveStatus;
										 // обнаружена  новая  запись
										$new = $rez_array; // print_r($rez_array);
										$old = $this->old_rez_array[$key];
										$rez_array['status']['flag_change'] = false; // сбросить флаг изменения записи
										$rez_array['status']['flag_new'] = false; // сбросить  флаг  новой  записи  
										//echo  "\n\n\nПакетное  изменение  записей  SQL!  ";
										 $status = $new['status']; // статус  записи (новая/измененная)
										unset($new['status']);
										unset($old['status']);
										$new = array_combine($this->get_field_name_false, $new);
										$old = array_combine( $this->get_field_name_false, $old);
										$sql_s[] = $this->container['source']->ActiveConnection->driver->create_sql_update($this->stmt,  $old, $new,  $status); // сгенерировать  sql  для  update  $values[]=trim($sql[1]);
									} // просмотрим все на предмет удаления
							 if (($rez_array['status']['flag_delete']) && ! $rez_array['status']['flag_canceled'] &&  ! $rez_array['status']['flag_new']) 
							 				{
												$new = $rez_array; // print_r($rez_array);
												// $old=$this->old_rez_array[$key];
												$rez_array['status']['flag_change'] = false; // сбросить флаг изменения записи
												$rez_array['status']['flag_new'] = false; // сбросить  флаг  новой  записи
												$rez_array['status']['flag_delete'] = false; // сбросить флаг удаления echo "\n\n\nПакетное изменение записей SQL! ";
												$status = $new['status']; // статус  записи (новая/измененная)
												unset($new['status']); // unset($old['status']);
												$new = array_combine( $this->get_field_name_false,   $new);
												 // $old=array_combine($this->get_field_name_false,  $old);
												
												 $sql_s[] = $this->container['source']->ActiveConnection->driver->create_sql_update($this->stmt, $new,  $new, $status); // сгенерировать  sql  для  update  $values[]=trim($sql[1]);
												}
 
   	 				// теперь просматриваем временные файлы, в них хранятся только модифицированные записи, сразу стираем их и ссылки в сессии
					 if (isset( $_SESSION['ADORecordSet'][$this->RecordSetId])) 
					 			{ 
									// print_r($_SESSION['ADORecordSet'][$this->RecordSetId] // );
									 foreach ($_SESSION['ADORecordSet'][$this->RecordSetId] as $k => $f) 
									 				{
														$a = unserialize( file_get_contents(sys_get_temp_dir() . $f));
														$rez_array = $a[1];
														$old = $a[0];  // если статус записи нужно сохранить, тогда мы не удаляем временную запись!
													if ($PreserveStatus)
																{
																	$a[1]['status']['preserveptatus'] = (boolean) $PreserveStatus;
																	$a[0]['status']['preserveptatus'] = (boolean) $PreserveStatus;
																	file_put_contents( sys_get_temp_dir() . $f,  serialize( $a));
																 }
															 else 
															 		{ // обычная  обработка,  просто  удвалим  запись,  т.к.  уже  сохранили  в  базу
																		unlink(sys_get_temp_dir() . $_SESSION['ADORecordSet'][$this->RecordSetId][$k]); //удалим ссылку на этот файл из сесии
																		unset( $_SESSION['ADORecordSet'][$this->RecordSetId][$k]);
																	}
		 
													$new = $rez_array;
													// статус записи (новая/измененная) проверяем на абсурдность, если запись была модифицирована и одновременно удаляется, то SQL вообще нет смысла генерировать
													$status = $new['status']; 
													if ($new['status']['flag_delete'] &&  ($new['status']['flag_new'] ||  $new['status']['flag_change'])) continue;

													unset( $new['status']);
													unset($old['status']);
													$new = array_combine($this->get_field_name_false, $new);
													$old = array_combine($this->get_field_name_false, $old);
													$sql_s[] = $this->container['source']->ActiveConnection->driver->create_sql_update(
																																				$this->stmt, 
																																				$old, 
																																				$new, 
																																				$status); // сгенерировать sql для update
													}// foreach ($_SESSION['ADORecordSet'][$this->RecordSetId] as $k => $f) 
										}//if (isset( $_SESSION['ADORecordSet'][$this->RecordSetId])) 
								}// foreach ($this->rez_array as $key => $rez_array) 
		//выполяем итоговый SQL запрос
	 if (count($sql_s)) 
	 			{ 
					// новые  записи  были  готовим  SQL,  для  типа  inset  обработка  отличается,  там  накапливаем  что  бы  все  вместить  в  
					//одну  инструкцию  SQL
					$sql_rez = [];
					$sql_insert_start = NULL;
					$sql_insert_values = [];

					 foreach ($sql_s as $k => $v) 
					 				{
										switch ($v['type']) 
												{
													case 'update':
													case 'delete':
																			{
																				$sql_rez[] = $v['sql'];
																				break;
																				}
													 case 'insert':
																			{ // начальная конструкция для INSERT
																			 if (empty( $sql_insert_start))  $sql_insert_start = $v['sql1'];
																			 $sql_insert_values[] = $v['values'];
																			 break;
																			 }
												 }
									 }
 
						 // проверим были-ли инструкции INSERT, если да, то склеим так что бы получился SQL запрос
						  if (! empty($sql_insert_start)) 
						  				{
											$sql_rez[] = $sql_insert_start . implode( ",", $sql_insert_values);
										}
						// все склеим, разные инструкции делятся через
	
						$sql = implode(";\n", $sql_rez);
						$RecordsAffected = 0;
						
						 try 
						 	{
								$this->container['source']->ActiveConnection->Execute($sql, 
																													$RecordsAffected, 
																													adExecuteNoRecords, 
																													NULL); // просто исполинть и все
							}
							 catch (ADOException $e) 
							 	{ 
								// если  возникла  ошибка,  сохраним  ее  в  запись  RS  где  она  произошла  и  выполним  еще  раз  исключение  для  отлова  ее  дальше
								$this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status']['errors'] = $e;
									throw new ADOException( $e);
								}
				}// if (count($sql_s))
			$this->set_status();
}



public function CancelBatch ($AffectRecords = 4)
{ 
	/*отмена  изменений  в пакетном режиме
 используются  константы  в  качестве  входных параметров const
 adAffectCurrent=1;//удаление/обновление текущей записи

 const adAffectGroup=2;//удаление/обновление  все записи удовлетворяющие фильтру или указаным закладкам, если они
 не  установлены, тогда ничего не удаляем

 const adAffectAll=3;//удаление/обновление все записи удовлетворяющие фильтру (закладкам) если они указаны, если ничего
 не  указано, обрабатывает все записи
 
 adAffectAllChapters=4;//все
*/
		 
}

public function CancelUpdate ()
{ // отмена изменения  текущей  записи
	 if ($this->State && isset($this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status']))
				$this->rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]]['status']['flag_canceled'] = true;
				 else  return false;
$this->set_status();
 return true;
}

public function Close ()
{ // удаляет  объект  рекордсет, и  освобождает  память, в драйвере вызывается Close для освобождения, актуально для процедур в MySql
$this->State = 0;
$this->container['source']->ActiveConnection->driver->Close($this->stmt);
$this->stmt = NULL;
$this->rez_array = [];
$this->old_rez_array = [];
$this->temp_rez_array = array('sort' => [], 	'filter' => []);
}

 public function Delete ( $AffectRecords = adAffectCurrent)
{ // удаляет записи
	if ($this->container['cursortype'] == adOpenForwardOnly)  throw new ADOException( $this->ActiveConnect,  4, 'RecordSet:' . $this->RecordSetName);
	switch ($AffectRecords) 
	{
		case adAffectAll:
			{ // удаляем всезаписиудовлетворяющиефильтру(закладкам),еслионинеуказаны,удаляемвсезаписи
				$this->MoveFirst();
				 // пробежим по всем записям и пометим их
				while (! $this->EOF) 
						{ // рекурсивно  обратиться  к  этой  же  функции,  и  удалить
						$this->Delete( adAffectCurrent);
						 $this->MoveNext();
						}
					break;
			 }
		
		case adAffectGroup:
		   	 { // удаляем записи удовлетворяющие фильтру, если фильтр (закладки) не указаны, игнорируем удвление
				if (empty( $this->container['filter']))  return; // удалять нечего,  критерий  не задан
				$this->MoveFirst();
				// пробежим по всем записям и пометим их
				while (! $this->EOF) {  //рекурсивно  обратиться  к  этой  же  функции,  и  удалить
					$this->Delete(
							adAffectCurrent);
					$this->MoveNext();
				}
				break;
		   	 }
		default:
		   	 { // по умолчанию удаляем текущую запись
				//print_r($this->rez_array);
				if (!$this->EOF && isset( $this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]])) 
						 $this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status']['flag_delete'] = true;
				}
		}
}

 public function CompareBookmarks ()
 { //
 
 }

public function Find ($Criteria, $SkipRows = 0,  $SearchDirection = adSearchForward,  $Start = '')
 {
	 /*
 //поиск записей 
 $Criteria - строка поиска, аналогична SQL
 
 SkipRows - начало поиска производить сместившись от закладки
 $Start (если установлено) или от текущей строки
  
 $SearchDirection - направление поиска adSearchForward , adSearchBackward $Start - закладка
*/

$field_name = $this->get_field_name_false;
if ($SearchDirection == adSearchForward) {$record_count = 1;}
			 else  {$record_count = $this->RecordCount;}
$md5_criteria=md5($Criteria);
if ($Start) {$this->Move( $SkipRows,  $Start);} // перейти  к  закладке, т.к. она явно указана
		else 
			{
				 if (! $this->Finding_record && ! $this->Find_Criteria_hash || $md5_criteria != $this->Find_Criteria_hash)
				 			{$this->jmp_record($record_count + $SkipRows);} // ищем  с  самого  начала,  т.к.  это  первый  поиск
						else  {$this->jmp_record($this->Finding_record + $SkipRows);} // ищем  с текущего  положения
				 $this->Find_Criteria_hash = $md5_criteria;
			}

			 // сейчас рекордсет установлен и готов к проверке на критерий в зависимости от направления крутимся в разные стороны
			//$find = new FilterFind(); // объект  поиска поиск  вперед
			 
			if (!array_key_exists($md5_criteria,$this->_cache_where1))
				{
					$this->Parser=new Parser();
					$struct=$this->Parser->parse($Criteria);
					$this->_cache_where1[$md5_criteria]=$this->Parser->create($struct);
				}
			$___s___=$this->_cache_where1[$md5_criteria];
			 
			 if ($SearchDirection == adSearchForward) 
			 			{
							while (! $this->EOF) 
									{ // крутимся  пока  не  конец  записей
										$arr_item = $this->rez_array[$this->container['absoluteposition'] -
										$this->AbsolutePosition_min_max[0]]; // текущая запись
										
										unset($arr_item['status']); // удалим служебную информацию
										$rez_array = array_combine($field_name, $arr_item); // сделать  массив что  бы ключи были  не  числовые а  имена полей,  и  преобразовать  в  переменные
										extract($rez_array);
										if (eval($___s___.';'))
											 {
												 $this->Finding_record = $this->container['absoluteposition'];
												 return;
												}
										$this->MoveNext(); // след.  запись
								 	}
							}
	// поиск назад
	 if ($SearchDirection ==  adSearchBackward)
			 				{
								while (! $this->BOF)
										 { // крутимся  пока  не  конец  записей
											$arr_item = $this->rez_array[$this->container['absoluteposition'] -
											$this->AbsolutePosition_min_max[0]]; // текущая  запись
											 if ($find->Filter( $arr_item,   $field_name,  $Criteria)) 
											 		{
														$this->Finding_record = $this->container['absoluteposition'];
														return;
													}
												$this->MovePrevious(); // след. запись  (переходим  к  предыдущей
											}
								}
}

public function GetRows ( $Rows = adGetRowsRest,   $Start = NULL,  $Field = NULL)
{ // вернуть массив значений
/*

 $Rows  - как формировать массив
  adGetRowsRest - числовой индекс поля,
 adGetRowsArrType-имя поля
  $Start откуда искать (закладка)
 $Field - порядкоевые имена, имена полей (массив) которые нужно получить
 */
 if (empty($Rows)) $Rows = adGetRowsRest; // по умолчанию, если вдруг  будет  пусто
 if (empty($Field))$Field = [];

 if (! is_array( $Field))  $Field = array($Field);
 $f = $this->get_field_name_false; // получить  все поля
 if (count( $Field) > 0)  $f = array_intersect(  $f,  $Field); // оставить только те поля,который  мы указали в $Field
 if (count($f) == 0)  return []; // нет  полей  для  вборки,  выходим
 if ($Rows & adGetRowsRest &&  ! empty($Start)) 
 		{ 
			// перейти  на  указаную  запись,  и  от  нее  стартовать  выборку
			$this->find_book_mark($Start);
		}
	$rez = []; // выходной  массив
	//создаем пустые массивы, на тот случай если записей нет
	foreach ($f as $number => $name)
			{
				if ($Rows & adGetRowsArrType)
						 $rez[$name]=[];
					 else $rez[$number ]= [];
			}
	
	while (! $this->EOF) 
	{ // пробежим по всем записям
	foreach ($f as $number => $name)
			{
				if ($Rows & adGetRowsArrType)
						 $rez[$name][] = $this->Fields->Item[$number]->Value;
					 else $rez[$number ][] = $this->Fields->Item[$number]->Value;
			}
	$this->MoveNext(); 
	 }
 return $rez;
}


public function GetString ()
{ //
}


public function NextRecordset (&$RecordsAffected = 0)
{ // возврат рекордсета для следующего запроса, если был мультизапрос
	$this->Close(); // закроем его
	$this->Open(); // откроем,  внутренний  счетчик  в  объекте  Command  переместит  на  след.  SQL,  и  исполнит  его
}

public function Requery ($Options = adCmdText)
{ //
	$this->Close();
	$this->State = 1;
	$this->rez_array = []; // кеш результата
	$this->AbsolutePosition_min_max = array(0, 0); // верхний-нижний
   // номер AbsolutePosition (нумерация с 1, если 0, значит не определено
	$this->BOF = true;
	$this->EOF = true;
	$this->AbsolutePage = 1;
	$this->RecordCount = 0;
	$this->jmp_record(1);

}

public function WriteXml ($destination = NULL, $WriteMode = DiffGram)
{
	$x = new AdoXml();
	$io = new stdClass();
	$io->rs = $this;
	$io->destination = $destination;
	$io->WriteMode = $WriteMode;
	
	return $x->WriteXml($io);

}

public function GetXml ()
{ // получить  структуру  RecordSet  в  виде  XML  (без  схемы)
	$x =new AdoXml();
	$io = new stdClass();
	$io->rs = $this;
	$io->_create_xsdxml = $this->_create_xsdxml;
	$io->flag_create_xsdxml = $this->flag_create_xsdxml;
	return $x->GetXml($io);

}

public function ReadXmlSchema ($source = NULL)
{ // настраивает RS в соответсвии со схемой $source - либо строка с самой схемой либо имя файла
	if (empty(	$source))	return; // ничего  не  делаем
	$x =new AdoXml();
	$io = new stdClass();
	$io->rs = $this;
	// проверим,  где  хранится  схема  в  строке  (на  входе)  или  в  файле  (имя  на  входе),  критерием  проверки  будет  наличие  <
	if (strrpos($source, '<') !== false)	$io->source = $source;
			else 
				{ // исходные данные в файле, проверим его наличие, если нет, ошибка
					if (is_file($source) && ! is_dir($source))		$io->source = file_get_contents( $source);
						else
							throw new ADOException(  $this->ActiveConnect,    12,    'RecordSet:' .$this->RecordSetName,    array(  $source));
					}
					$io->columnCount = &$this->columnCount;
					return $x->ReadXmlSchema($io);
				}


public function GetXmlSchema ()
{
$x =new AdoXml();
$io = new stdClass();
$io->rs = $this;
$io->_create_xsdxml = $this->_create_xsdxml;
$io->flag_create_xsdxml = $this->flag_create_xsdxml;
return $x->GetXmlSchema(	$io);
}


public function ReadXml ($source = NULL, $ReadMode = ADO::DiffGram)
{
	if (empty(	$source))	throw new ADOException(	$this->ActiveConnect, 	10, 'RecordSet:' .$this->RecordSetName); // неоткуда считывать данные
	$x =new AdoXml();
	$io = new stdClass();
	$io->rs = $this;
	$io->source = $source;
	$io->ReadMode = $ReadMode;
	$io->rez_array = $this->rez_array;
	$io->old_rez_array = $this->old_rez_array;
	$io->get_field_name_false = &$this->get_field_name_false;
	$io->get_field_name_true = &$this->get_field_name_true;
	$io->AbsolutePosition_min_max = $this->AbsolutePosition_min_max;
	$io->columnCount = &$this->columnCount;
	$io->flag_create_xsdxml = $this->flag_create_xsdxml;
	return $x->ReadXml($io);
}

public function WriteXmlSchema ($destination = NULL)
{ // аналогично GetXmlSchema(), но выводит в destination
$xsd = $this->GetXmlSchema();
if (is_string($destination))	file_put_contents(	$destination, $xsd);
					else	return $xsd;
}


public function Supports ()
{ //
throw new ADOException($this->ActiveConnect, 1, 'RecordSet:' .	$this->RecordSetName,   array(  '*'));
}
				
// ---------------------------
private function rez_array2Field ($rez)
{
/*
 *  для  внктренних  целей,  вносит  данные  из  массива  данных  в  объект  Field  (коллекции  Fields)
 */
$this->container['bookmark'] = $rez['status']['BookMark'];
$i = 0;
if (is_array($rez))
				foreach ($rez as $v) 
						{
							if (! is_array( $v)) 
										{
										$this->Fields->Item[$i]->set_value( $v);
										$i ++;
										}
						}
}

private function RsFilter ()
{ /*
 фильтрация  объектов  Field  по  условию  модифицирует  массив  $this->rez_array,  заодно  корректирует  кол-во  записей, 
 страниц  работает  только  при  ксловии  что  загружено  все,  т.е.  $this->_MaxRecords=0  иначе  фильтрация  просто  игнорируется */
if ($this->container['maxrecords'] > 0) 
		{
			$this->temp_rez_array['filter'] = [];
			throw new ADOException(  $this->ActiveConnect,  7,   'RecordSet:' .	$this->RecordSetName,    array(  'RsFilter()'));
		} // ошибка,  т.к.  не  все  записи  загружены  восстановить  кеш  в  полном  объеме,  что  бы  вновь  начать  фильтрацию
if (count( $this->temp_rez_array['filter']))
								{$this->rez_array = $this->temp_rez_array['filter'];}
							else
								{$this->temp_rez_array['filter'] = $this->rez_array;}
// отмена фильтрации?
if (count(  $this->temp_rez_array['filter']) &&! $this->container['filter']) 
		{
		   $this->rez_array = $this->temp_rez_array['filter'];
		   $this->RecordCount = count( $this->rez_array);
		   // предельные границы
		   $this->AbsolutePosition_min_max[0] = 1; // в начале  запись $AbsolutePosition
		   $this->AbsolutePosition_min_max[1] = $this->RecordCount -1; // в конце номер последней
		   $this->temp_rez_array['filter'] = []; // освободим память
		   $this->jmp_record( 1);
		   return;
		   }
		   
		   // загрузить  объект  для  поиска/фильтрации  и  выполнить  фильтрацию,
			// если условие в виде строки, тогда ищем в отдельном объекте, иначе внутри рекордсета
 if (is_string($this->container['filter']))
		 {
			$rez = []; // очистить выходной буфер
			$h=md5($this->container['filter']);
			if (!array_key_exists($h,$this->_cache_where))
				{
					$this->Parser=new Parser();
					$struct=$this->Parser->parse($this->container['filter']);
					$this->_cache_where[$h]=$this->Parser->create($struct);
				}
			$___s___=$this->_cache_where[$h];
			foreach ($this->rez_array as $rez_array) 
				{
					$rez_array_ = $rez_array;
					unset($rez_array_['status']); // удалим служебные флаги
					$rez_array_ = array_combine($this->get_field_name_false, $rez_array_); // сделать  массив что бы ключи были не числовые а имена полей, и преобразовать в переменные
					extract($rez_array_);
					if (eval($___s___.';')) {$rez[]=$rez_array;}
				}
				$this->rez_array= $rez;
		 }
				 else 
				 		{ // поиск закладок, они заданы в виде массива
						 if (! is_array( $this->container['filter'])) throw new ADOException( $this->ActiveConnect,  'RecordSet:' .  $this->RecordSetName);
						 $rez = [];
						 foreach ($this->rez_array as $rez_array) 
						 		{ // пробежим  по всем записям
									 $a = array_search( $rez_array['status']['BookMark'],  $this->container['filter']); // ищем
									 if ($a !==  false)  $rez[] = $rez_array; // найдено
					 			 }
						 $this->rez_array = $rez;
						 }
						 // кол-во  записей
$this->RecordCount = count( $this->rez_array);
// предельные границы
$this->AbsolutePosition_min_max[0] = 1; // в начале запись $AbsolutePosition
$this->AbsolutePosition_min_max[1] = $this->RecordCount; // в конце номер последней
$this->jmp_record( 1);
}

private function RsSort ()
{ /*
* сортировка объектов Field по условию модифицирует массив $this->rez_array, заодно корректирует кол-во
* записей, страниц работает только при ксловии что загружено все, т.е. $this->_MaxRecords=0 иначе фильтрация просто игнорируется
*/
if ($this->container['maxrecords'] > 0) 
	{
	  $this->temp_rez_array['sort'] = [];
	  throw new ADOException( $this->ActiveConnect,  7,  'RecordSet:' .$this->RecordSetName,  array('RsSort()'));
	} // игнорировать  фильтр,  т.к.  не  все  записи  загружены

$s1 = explode(',', str_replace("  ", " ", $this->container['sort'])); // отдельные  элменты  для  сортировки  +  удалим  лишние  пробелы
 $sort = [];
 $i = 0;
 foreach ($s1 as $s) 
 	{
	  $sort[$i] = explode(' ',  trim( $s));
	  if (! isset( $sort[$i][1])) $sort[$i][1] = 'asc'; // если не задан пераметр, значит по умолчанию это asc
	  $i ++;
  }
  
// восстановить  кеш  в  полном  объеме,  что  бы  вновь  начать  сортировку
  if (count( $this->temp_rez_array['sort']))
	  $this->rez_array = $this->temp_rez_array['sort'];
  else
	  $this->temp_rez_array['sort'] = $this->rez_array;
  
  if (count($this->temp_rez_array['sort']) &&  ! $this->container['sort']) 
  		{
			$this->rez_array = $this->temp_rez_array['sort']; // вернуть как было до сортировок
		  $this->temp_rez_array['sort'] = []; // освободим память
		  $this->jmp_record(1); // перейти на первую запись
		  return;
		  }
	  
$i = 0;
$sort_ = new Sort(); // экземпляр объекта сортировки
$sort_->setArray($this->rez_array);
$field_name = $this->get_field_name_true; // имена  полей  добавим  то  что  будем  сортировать
foreach ($sort as $v)
			  $sort_->addColumn( $field_name[$v[0]],  strtoupper( $v[1]));
$this->rez_array = $sort_->sort();
// print_r($this->rez_array);
$this->jmp_record(1);
 }
	  



/*
ОБНОВЛЕНИЕ из заполненной сущности в базу
$entity - экземпляр сущности с заполненными данными
$do_update - флаг немедленного внесения в базу (true)
			false - записывается в буфер, update производится отдельно (программист заботится!), полезно для пакетной обработки
*/
public function persist($entity,$do_update=true)
{
	if (!is_object($entity)) {throw new ADOException(NULL, 26,NULL, [gettype($entity)] );}//не допустимый тип
	$r=$this->getRepository(get_class($entity))->persist($entity);
	if ($do_update) {$this->Update();}
}


/*
Гидратация данных в виде массива объектов
$entityName - Имя объекта куда будет все грузиться с позиции на которую указывается в RS
возвращает массив этих объектов
*/
public function FetchEntityAll($entityName)
{
	return $this->getRepository($entityName)->FetchEntityAll();
}

/*
Гидратация данных в виде объекта
$entityName - Имя объекта куда будет все грузиться
возвращает заполненный объект, данными на который указывает в RS
*/
public function FetchEntity($entityName)
{
	return $this->getRepository($entityName)->FetchEntity();
}



// *******************
// служебные



/*
получить экземпляр репозитарий по имени
по именам эти экземпляры кешируются
*/
private function getRepository($entityName)
{
	if (!is_string($entityName)) {throw new ADOException(NULL, 20 );}//не допустимый тип
    if (isset($this->repositoryList[$entityName])) {
            return $this->repositoryList[$entityName];
        }
	$this->repositoryList[$entityName] =new EntityRepository($this,$entityName);
	return $this->repositoryList[$entityName];
}



private function find_book_mark ($BookMark = '')
{ // поиск по закладке
if (! $BookMark) return;
$absolite_position = $this->container['absoluteposition']; // сохраним  позицию,  если  не  найдем,  то  перейдем  к  ней
$this->jmp_record(1); // перейдем в начало
while (! $this->EOF)		   // пройдемся по всем записям
		  {
			  if ($this->container['bookmark'] ==  $BookMark)   {  return; } // нашли, в рекордсете текущая запись точо соотвествует закладке
			  $this->MoveNext();
		  }
 $this->jmp_record($absolite_position); // ничего не нашли, верем обратно запись
}


private function get_field_name ($type = false)
{ // получить  массив  имен  полей  $type-  тип  выхода  false:  array(0=>имя,....),  иначе  array(имя=>номер  по  порядку)
if (! isset( $this->rez_array[0])) return []; // если рекордсет пустой, то выход
$i = 0;
$field_name = [];
if ($type) 
		{
		  foreach ($this->rez_array[0] as $v) 
		  			{ // получить  имена  полей  чтобы  по  имени  поля  получить  его  номер  в  массиве
					  if (! is_array( $v)) 
					  			{
								  $field_name[$this->Fields->Item[$i]->Name] = $i;
								  $i ++;
								  }
				  }
	  } 
	  else 
	  {
		 foreach ($this->rez_array[0] as $v) 
		 	{
			 if (! is_array( $v)) 
			 			{
							  $field_name[$i] = $this->Fields->Item[$i]->Name;
							  $i ++;
						}
			  }
	  }
return $field_name;
}

private function change_value (Field $field_obj)
{ // вызывается  когда  меняем  сво-во  Value  в  объекте  Field,  на  входе  экземпляр  объекта  Field
 if ($this->container['cursortype'] == adOpenForwardOnly)throw new ADOException( $this->ActiveConnect, 5, 'RecordSet:' . $this->RecordSetName);
$number = $this->get_field_name_true; // получить имена

// получить  ключ  в  массиве  $this->rez_array  который  будем  модифицировать  сохраним  старое  значение,  что  бы  можно  было  
//найти  старую  запись  в  базе,либо  откатить  измеенения
$key = $number[$field_obj->Name]; 

//проверим, если новое значение отлично от старого, тогда меняем, если тоже самое, тогда нет! 
if ($field_obj->Value ===$this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]][$key]) {return;}


if (! isset($this->old_rez_array[$this->container['absoluteposition'] -$this->AbsolutePosition_min_max[0]]))
					  $this->old_rez_array[$this->container['absoluteposition'] -$this->AbsolutePosition_min_max[0]] = $this->rez_array[$this->container['absoluteposition'] -
					   $this->AbsolutePosition_min_max[0]];

$this->old_rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]][$key] = $this->rez_array[$this->container['absoluteposition'] -$this->AbsolutePosition_min_max[0]][$key];
// сохраним  старое знаечние (оригинальное)
$field_obj->OriginalValue = $this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]][$key];

$this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]][$key] = $field_obj->Value; 
// присвоить  новое  значение  изменим  статус  записи  на  "модифицированная"  только  в  том  случае,  если  это  не  новая  запись
 if (! $this->rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]]['status']['flag_new'])
									  $this->rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]]['status']['flag_change'] = true;
}

private function set_status ()
{ // установка  кодов  статуса  исходя  из  внутреннего  массива  статусов
$this->Status = 0;
if (! isset( $this->rez_array[$this->container['absoluteposition'] -  $this->AbsolutePosition_min_max[0]])) return;
$status = $this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]['status'];
// нужно менять статус записи или нет?
if ($status['preserveptatus'])  return;

if ($status['flag_new']) {$this->Status += adRecNew;$this->EditMode=adEditAdd;}
if ($status['flag_change'])  {$this->Status += adRecModified;$this->EditMode=adEditInProgress;}
if ($status['flag_canceled']) {$this->Status += adRecCanceled;$this->EditMode=adEditNone;}
if ($status['flag_delete']) {$this->Status += adRecDeleted;$this->EditMode=adEditDelete;}
}



function __clone()
    {
        // Принудительно копируем this->Fields, иначе
        // он будет указывать на один и тот же объект.
        $this->Fields = clone $this->Fields;
		//$this->RecordSetId = md5(microtime()); // всегда уникальный
		$this->container['source'] =clone $this->container['source'];
		//$this->stmt=clone $this->stmt;
		$this->DataColumns=clone $this->DataColumns;
		//if ($this->Parser) {$this->Parser=clone $this->Parser;}
    }




public function __set ($var, $value)
{
$var = strtolower($var);
switch ($var) 
	{
	  case 'pagesize':
						  {
						 	$value = (int) $value; // расчитать  кол-во  страниц  при  указаном  кол-ве  записей
							if ($value > 0) 
								{
									$this->container['pagesize'] = $value; // проверим на допустимость
									$this->PageCount = ceil($this->RecordCount /  $this->container['pagesize']);
									$this->container['absolutepage'] = floor( ($this->container['absoluteposition'] - 1) / $this->container['pagesize']) + 1; // пересчитать номер страницы  при  изменении размера страницы
								}
								break;
							}
	  case 'absolutepage':
						  { // проверить верность на пределы вычислить положение указателя на новую запись, в соответсвии с данными номера страницы
								if ($value <=  $this->PageCount &&   $value >   0) 
										{
											$this->container['absolutepage'] = $value;
											$this->jmp_record( ($this->container['absolutepage'] -1) *$this->container['pagesize'] + 1); // считать первую запись в странице
										}
								  break;
						  }
							  
	  case 'absoluteposition':
					  { // проверить  верность  на  пределы  вычислить  положение  указателя  на  новую  запись,  в  соответсвии  с  данными  номера  страницы
									  if ($value <=  $this->RecordCount &&   $value >0) 
									  		{
												$this->jmp_record( $value); // считать первую запись в странице
											  }
							break;
						}

	  case 'filter':
					  {
							if (is_string($value)) $this->container['filter'] = trim( $value); // если это строка, удалим возможные пробелы
									  else
										  $this->container['filter'] = $value; // если это массив закладок, оставимкак есть
							$this->RsFilter();
						  break;
					  } // фильтрация  рекордсета
	  case 'maxrecords':
					  {
						  if ($this->State >  0) throw new ADOException( $this->ActiveConnect,  11,  'RecordSet:' .  $this->RecordSetName .  " [$var]",  array( $var)); 
						  // если уже открыто, тогда выход установить объем кеша, но торлько в том случае, если курсор на стороне сервера if ($this->container['cursortype']!=adUseClient)
							$this->container['maxrecords'] = $value; // установить объем кеша
						  break;
					  }
	  case 'sort':
				  { // фильтрация  рекордсета
					  $this->container['sort'] = $value;
					  $this->RsSort();
					  break;
				}

	  case 'bookmark':
			  {
				  $this->container['bookmark'] = $value;
				  $this->find_book_mark( $value); // попробуем найти
				  break;
			  } // фильтрация  рекордсета

	  case 'cursorlocation':
			  {
								if ($this->State ==  0)  $this->container['cursorlocation'] = $value; // менять эту переменную можно только при закрытом рекордсете
							  if ($value == adUseClient)  $this->container['maxrecords'] = 0; // все записывать в память
									  else $this->container['maxrecords'] = 10; // если на сервере все, тогда максимум 10 записей храним
				  break;
		  } // расположение данных на сервере/клиенте

	  case 'source':
			  {
				  if ($this->State ==  0)  $this->container['source'] = $value;
			  break;
			  }

	  case 'cursortype':
			  {
							if ($this->State == 0) $this->container['cursortype'] = $value; // менять эту переменную можно только при закрытом рекордсете
									  else  throw new ADOException( $this->ActiveConnect,  11, 'RecordSet:' .$this->RecordSetName ." [$var]",  array($var));
			  break;
			  } // тип курсора

	  case 'locktype':
			  {
						if ($this->State == 0)  $this->container['locktype'] = $value; // менять эту переменную можно  только при  закрытом рекордсете
									  else  throw new ADOException($this->ActiveConnect, 11, 'RecordSet:' .	$this->RecordSetName ." [$var]",    array($var));
			  break;
			  } // блокировки рекордсета
	  
	  
	  
	  
	  }
}

public function &__get ($var)
 {
	$var = strtolower($var);
	if (array_key_exists( $var, $this->container)) {return $this->container[$var];}
	$arr = debug_backtrace();
	trigger_error("Undefined property: RecordSet::\$$var in " . $arr[0]['file'] . " on line " . $arr[0]['line'], E_USER_WARNING);
	  return $this->container['absolutepage'];
  }

public function __call ( $name,  $var)
{ // диспетчер служебных функций
 if ($name =='change_value') 
 		{
			$this->change_value($var[0]);  return;
		  }
 throw new ADOException($this->ActiveConnect,   6, 'RecordSet:' . $this->RecordSetName);
 }

public function _get_rec_error ()
{
if (isset($this->rez_array[$this->container['absoluteposition'] - $this->AbsolutePosition_min_max[0]]))
	return $this->rez_array[$this->container['absoluteposition'] -$this->AbsolutePosition_min_max[0]]['status']['errors'];
	  else  return NULL;
}

public function __destruct ()
{
	if (isset($_SESSION['ADORecordSet'][$this->RecordSetId]) &&   is_array( $_SESSION['ADORecordSet'][$this->RecordSetId])) 
		{
			foreach ($_SESSION['ADORecordSet'][$this->RecordSetId] as $f) 
				{
					unlink(sys_get_temp_dir() . $f);/*echo sys_get_temp_dir().$f.' ' ;*/
				}
		}
	unset($_SESSION['ADORecordSet'][$this->RecordSetId]);
}


}