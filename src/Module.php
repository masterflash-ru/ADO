<?php
/**
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ADO;
use Zend\Mvc\MvcEvent;

class Module
{

public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

   // Метод "init" вызывается при запуске приложения и  
    // позволяет зарегистрировать обработчик событий.
    public function init( $manager)
    {
	define("adStateClosed", 0); // по умолчанию
	define("adStateOpen", 1);

	// параметр $Mode
	define("adModeUnknown", 0); // по умолчанию
	define("adModeRead", 1);
	define("adModeWrite", 2);
	define("adModeReadWrite", 3);

	// константы для метода Execute, определяют тип команды
	define("adCmdText", 1); // текстовое определение команды/процедуры
	define("adCmdTable", 2); // создать SQL запрос, который вернет все строки указанной таблицы
	define("adCmdStoredProc", 4); // хранимая процедура
	define("adExecuteNoRecords", 128); // не возвращать строки, просто исполнить и все 
	define("adExecuteNoRecordsMulti",256);//служебная константа, аналогична adExecuteNoRecords,		 // только для мультизапросов
	define("adExecuteNoCreateRecordSet",8);//служебная, НЕ создавать на выходе метода Execute объект RecordSet
	
	

	// константы для события открытия
	define("adStatusOK", 1); // все ОК
	define("adStatusErrorsOccurred", 2); // операция претерпела неудачу

	// константы для обхекта parameter
	define("adParamSigned", 16); // - параметр принимает значения со знаком.
	define("adParamNullable", 64); // - параметр принимает пустые значения.
	define("adParamLong", 128); // - параметр принимает двоичные данные.  это параметр Direction объекта command  /parameter
	define("adParamUnknown", 0); // - направление параметра неизвестно.
	define("adParamInput", 1); // - по умолчанию, входной параметр.
	define("adParamOutput", 2); // - выходной параметр.
	define("adParamInputOutput", 3); // - параметр представляет собой и входной, и  выходной параметр
	define("adParamReturnValue",4);// - параметр представляет собой возвращаемое значение.

	// типы данных
	define("adEmpty", 0); // - значение не задано.
	define("adSmallInt", 2); // - двухбайтное целое со знаком.
	define("adInteger", 3); // - четырёхбайтное целое со знаком.
	define("adSingle", 4); // - число с плавающей запятой с одинарной точностью.
	define("adDouble", 5); // - число с плавающей запятой с двойной точностью.
	define("adCurrency", 6); // - денежная сумма с фиксированной точкой с четырьмя цифрами справа от десятичной точки восьмибайтное целое число со знаком);//.
	define("adError", 170); // - 32-битный код ошибки.
	define("adBoolean", 11); // - булево значение.
	define("adDecimal", 14); // - числовое значение с фиксированной точностью и масштабом.
	define("adTinyInt", 16); // - однобайтное целое со знаком.
	define("adUnsignedTinyInt", 17); // - однобайтное целое без знака.
	define("adUnsignedSmallInt", 18); // - двухбайтное целое без знака.
	define("adUnsignedInt", 19); // - четырёхбайтное целое без знака.
	define("adBigInt", 20); // - восьмибайтное целое со знаком.
	define("adUnsignedBigInt", 21); // - восьмибайтное целое без знака.
	define("adBinary", 128); // - двоичное значение.
	define("adChar", 129); // - строковое значение.
	define("adUserDefined", 132); // - определяемая пользователем переменная.
	define("adDBDate", 133); // - дата формата yyyymmdd.
	define("adDBTime", 134); // - время формата hhmmss.
	define("adDBTimeStamp", 135); // - дата и время формата yyyymmddhhmmss плюс  тысячные доли секунды.

	// типы перемоток в рекордсете метода Move (от чего вести отсчет)
	define("adBookmarkCurrent", 0); // от текущей записи (по умолчанию)
	define("adBookmarkFirst", 1); // от первой записи
	define("adBookmarkLast", 2); // от последней

	// направление поиска в методе Find Recordset-a
	define("adSearchBackward", - 1); // поиск назад
	define("adSearchForward", 1); // поиск веперд

	// положение курсора (пока нигде не используется)
	define("adUseServer", 2); // на стороне сервера
	define("adUseClient", 3); // на стороне клиента

	// тип курсора
	define("adOpenForwardOnly", 0); // можно прокручивать только вперед и один раз
	define("adOpenKeyset", 1); // аналогичен статическому курсору, но запрещает  пакетную обработку
	define("adOpenStatic", 3); // статический курсор, т.е. в рекордсете копия  данных,  изменение в базе другими не видно, так же  включает  пакетный режим работы

	// Тип блокировок (как таковые блокировки не производятся пока!)
	define("adLockReadOnly", 1); // только для чтения, данные нельзя менять, можно только считывать
	define("adLockOptimistic", 3); // стандарное чтение/запись
	define("adLockBatchOptimistic", 4); // стандарное чтение/запись для пакетной обработки

	// статус записи в RecordSet
	define("adRecCanceled", 256); // запись отменена
	define("adRecNew", 1); // новая запись
	define("adRecModified", 2); // запись модифицирована, но пока не записана
	define("adRecOK", 0); // нормальное состояние
	define("adRecDeleted", 4); // запись была удалена


	// варианты удаления записей или обновления в пакете
	define("adAffectCurrent", 1); // удаление/обновление текущей записи
	define("adAffectGroup", 2); // удаление/обновление все записи удовлетворяющие  фильтру или указаным закладкам, если они не  установлены, тогда ничего не удаляем
	define("adAffectAll", 3); // удаление/обновление все записи удовлетворяющие  фильтру (закладкам) если они указаны, если ничего  не  указано, обрабатывает все записи
	define("adAffectAllChapters", 4); // удалаяет/обновление все записи независимо от фильтра

	// варианты типов возврата для GetRows объекта RecordSet
	define("adGetRowsRest", 1); // возвращает все записи от текущей или от закладки
	define("adGetRowsArrType", 2); // тип массива на выходе, если adGetRowsArrType - ассоциативный, иначе числовой

	// формат генерации XML для текущей колонки, эти константы записываются в сво-во объекта DataColumn
	define("MappingTypeElement", 1); // стандартный, в виде значения элемента-узла
	define("MappingTypeAttribute", 2); // в виде атрибута
	define("MappingTypeHidden", 3); // скрытый, запрещенный
	define("MappingTypeSimpleContent",4);//непонятен формат вывода, поэтому пока не реализован

	// формат записи в формате XML в методе writexml рекордсета
	define("WriteSchema", 1); // сохраняет схему непосредственно в выходной файл
	define("IgnoreSchema", 2); // опускает схему вообще
	define("DiffGram", 3); // в специальном формате пригодном для загрузки в рекордсет

	//Состояние редактирования recordSet записи
	define("adEditNone",0);	//редактирование не проводилось
	define("adEditInProgress",1);	//редактирвоание проводилось, но изменения не сохранены
	define("adEditAdd",2);	//текущая запись была добавлена методом AddNew
	define("adEditDelete",4);	//текущая запись была удалена

        define("ADO_LOCALE","ru_RU");
    }

}
