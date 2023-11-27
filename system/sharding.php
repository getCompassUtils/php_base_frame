<?php

use BaseFrame\Exception\Domain\ReturnFatalException;
use BaseFrame\Exception\Gateway\QueryFatalException;
use BaseFrame\Conf\ConfProvider;

/**
 * класс управления базами данными и подключениями
 * служит, что через него удобно шардить подключения и некоторые базы данных
 */
class sharding {

	/**
	 * Пока такое решение для объединенных модулей.
	 *
	 * @return myPDObasic
	 */
	public static function configuredPDO(array $conf):myPDObasic {

		if (!isset($GLOBALS["pdo_driver"][$conf["db"]])) {

			$GLOBALS["pdo_driver"][$conf["db"]] = self::pdoConnect(
				$conf["mysql"]["host"],
				$conf["mysql"]["user"],
				$conf["mysql"]["pass"],
				$conf["mysql"]["ssl"],
				$conf["db"]
			);
		}

		return $GLOBALS["pdo_driver"][$conf["db"]];
	}

	// адаптер mysql для работы с уонкретной базой
	// список баз задается в конфиге sharding.php
	public static function pdo(string $db):myPDObasic {

		if (!isset($GLOBALS["pdo_driver"][$db])) {

			// если нет вообще массива
			if (!isset($GLOBALS["pdo_driver"])) {
				$GLOBALS["pdo_driver"] = [];
			}

			// получаем sharding конфиг
			$conf = ConfProvider::shardingMysql()[$db];

			// создаем соединение
			$GLOBALS["pdo_driver"][$db] = self::pdoConnect(
				$conf["mysql"]["host"],
				$conf["mysql"]["user"],
				$conf["mysql"]["pass"],
				$conf["mysql"]["ssl"],
				$conf["db"]
			);
		}

		return $GLOBALS["pdo_driver"][$db];
	}

	// для негифрованного подключения к sphinx
	public static function sphinx(string $sharding_key):myPDObasic {

		if (!isset($GLOBALS["pdo_driver_sphinx"][$sharding_key])) {

			// если нет вообще массива
			if (!isset($GLOBALS["pdo_driver_sphinx"])) {
				$GLOBALS["pdo_driver_sphinx"] = [];
			}

			// получаем sharding конфиг
			$conf = ConfProvider::shardingSphinx()[$sharding_key];

			// устанавливаем соединение
			$GLOBALS["pdo_driver_sphinx"][$sharding_key] = self::pdoConnect(
				$conf["mysql"]["host"],
				$conf["mysql"]["user"],
				$conf["mysql"]["pass"],
				false,
				$conf["db"]
			);
		}

		return $GLOBALS["pdo_driver_sphinx"][$sharding_key];
	}

	// функция для создания соединения с MySQL сервером
	public static function pdoConnect(string $host, string $user, string $password, bool $ssl, string $db = null):myPDObasic {

		// опции подключения
		$opt = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => true,  // ! Важно чтобы было TRUE
			PDO::ATTR_STATEMENT_CLASS    => ["myPDOStatement"],
		];

		// если подключение зашифровано
		if ($ssl == true) {

			$opt[PDO::MYSQL_ATTR_SSL_CIPHER]             = "DHE-RSA-AES256-SHA:AES128-SHA";
			$opt[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
		}

		// собираем DSN строку подключения
		$dsn = "mysql:host={$host};";
		if (!is_null($dsn)) {
			$dsn .= "dbname={$db};";
		}
		$dsn .= "charset=utf8mb4;";

		return new myPDObasic($dsn, $user, $password, $opt);
	}

	// для подключени к rabbit
	public static function configuredRabbit(array $conf, string $key):Rabbit {

		if (!isset($GLOBALS["rabbit_driver"][$key])) {

			// создаем новое подключение к rabbit
			$GLOBALS["rabbit_driver"][$key] = new Rabbit($conf["host"], $conf["port"], $conf["user"], $conf["pass"]);
		}

		return $GLOBALS["rabbit_driver"][$key];
	}

	// для подключени к rabbit
	public static function rabbit(string $key):Rabbit {

		if (!isset($GLOBALS["rabbit_driver"][$key])) {

			// если нет вообще массива
			if (!isset($GLOBALS["rabbit_driver"])) {
				$GLOBALS["rabbit_driver"] = [];
			}

			// получаем sharding конфиг
			$conf = ConfProvider::shardingRabbit()[$key];

			$GLOBALS["rabbit_driver"][$key] = new Rabbit($conf["host"], $conf["port"], $conf["user"], $conf["pass"]);
		}

		return $GLOBALS["rabbit_driver"][$key];
	}

	// отключает и закрывает все соединения и статистические методы
	public static function end(bool $close_rabbit = true):bool {

		// не разрывать подключение с RabbitMQ
		// используется в одном месте в проекте - Cron_Default
		// когда крон слишком долго спал и нужно очистить подключения ПЕРЕД doWork
		// т.е. подключение с rabbit гарантированно не протухло, т/к крон только что получил из него задачу
		// ВО ВСЕХ ОСТАЛЬНЫХ СЛУЧАЯХ ИСПОЛЬЗОВАТЬ СТРОГО-НАСТРОГО ЗАПРЕЩЕНО
		if ($close_rabbit == true) {

			// разрываем подключение с RabbitMQ
			console("RABBIT sharding::end()...");
			self::_endRabbit();
		}

		Mcache::end();
		Bus::end();

		// удаляем соединения с MySQL
		self::_endMySql();

		// удаляем соединения с Sphinx
		self::_endSphinx();

		console("sharding::end()...");
		return true;
	}

	// разрываем подключение с RabbitMQ
	protected static function _endRabbit():void {

		if (isset($GLOBALS["rabbit_driver"])) {

			foreach ($GLOBALS["rabbit_driver"] as $key => $value) {

				// удаляем связь
				$GLOBALS["rabbit_driver"][$key]->closeAll();
				unset($GLOBALS["rabbit_driver"][$key]);
			}

			unset($GLOBALS["rabbit_driver"]);
		}
	}

	// удаляем соединения с MySQL
	protected static function _endMySql():void {

		if (!isset($GLOBALS["pdo_driver"])) {

			return;
		}

		foreach ($GLOBALS["pdo_driver"] as $key => $value) {

			// удаляем связь
			$GLOBALS["pdo_driver"][$key] = null;
			unset($GLOBALS["pdo_driver"][$key]);
		}

		unset($GLOBALS["pdo_driver"]);
	}

	// удаляем соединения с Sphinx
	protected static function _endSphinx():void {

		if (!isset($GLOBALS["pdo_driver_sphinx"])) {

			return;
		}

		foreach ($GLOBALS["pdo_driver_sphinx"] as $key => $value) {

			// удаляем связь
			$GLOBALS["pdo_driver_sphinx"][$key] = null;
			unset($GLOBALS["pdo_driver_sphinx"][$key]);
		}

		unset($GLOBALS["pdo_driver_sphinx"]);
	}
}

/**
 * класс для расширения и удобства работы с базой данных через PDO
 */
class myPDObasic extends PDO {

	public const ISOLATION_READ_UNCOMMITTED = "READ UNCOMMITTED";
	public const ISOLATION_REPEATABLE_READ  = "REPEATABLE READ";
	public const ISOLATION_READ_COMMITTED   = "READ COMMITTED";
	public const ISOLATION_SERIALIZABLE     = "SERIALIZABLE";

	// устанавливаем уровень транзакции
	public function setTransactionIsolationLevel(string $isolation_level):bool {

		// проверяем что не прислали левачок
		$isolation_level_list = [
			self::ISOLATION_READ_UNCOMMITTED,
			self::ISOLATION_REPEATABLE_READ,
			self::ISOLATION_READ_COMMITTED,
			self::ISOLATION_SERIALIZABLE,
		];
		if (!in_array($isolation_level, $isolation_level_list)) {
			throw new QueryFatalException("Unknown isolation level = '{$isolation_level}', please use one of myPDObasic::ISOLATION_ constants");
		}
		$query  = "SET TRANSACTION ISOLATION LEVEL {$isolation_level};";
		$result = $this->query($query);
		return $result->errorCode() == PDO::ERR_NONE;
	}

	// начинаем транзакцию
	public function beginTransaction():bool {

		$this->_showDebugIfNeed("BEGIN");
		return parent::beginTransaction();
	}

	// коммитим транзакцию (может быть удачно|нет)
	public function commit():bool {

		$this->_showDebugIfNeed("COMMIT");
		return parent::commit();
	}

	// коммитим транзакцию (бросаем исключение если не вышло)
	public function forceCommit():void {

		$result = self::commit();
		if ($result != true) {
			throw new ReturnFatalException("Transaction commit failed");
		}
	}

	// rollback транзакции
	public function rollback():bool {

		$this->_showDebugIfNeed("ROLLBACK");
		return parent::rollBack();
	}

	// возвращает одну запись или пустой массив если не найден
	public function getOne():array {

		// подготавливаем запрос (очищаем его)
		$query = $this->_prepareQuery(func_get_args());

		// если нет лимита
		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			$this->_throwError("WHERE or LIMIT not found on SQL: {$query}");
		}

		//
		$this->_showDebugIfNeed($query);
		$result = $this->query($query)->fetch();
		return is_array($result) ? $result : [];
	}

	// возвращает множество записей или пустой массив если не найден
	public function getAll():array {

		// подготавливаем запрос (очищаем его)
		$query = $this->_prepareQuery(func_get_args());

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			$this->_throwError("WHERE or LIMIT not found on SQL: {$query}");
		}

		$this->_showDebugIfNeed($query);
		$result = $this->query($query)->fetchAll();
		return is_array($result) ? $result : [];
	}

	// обновляем значения в базе
	public function update():int {

		// подготавливаем запрос (очищаем его)
		$query = $this->_prepareQuery(func_get_args());
		//

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			$this->_throwError("WHERE or LIMIT not found on SQL: {$query}");
		}

		//
		$this->_showDebugIfNeed($query);
		$result = $this->query($query);
		return $result->rowCount();
	}

	// удаляем значение из базы
	public function delete():int {

		// подготавливаем запрос (очищаем его)
		$query = $this->_prepareQuery(func_get_args());
		//

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			$this->_throwError("WHERE or LIMIT not found on SQL: {$query}");
		}

		//
		$this->_showDebugIfNeed($query);
		$result = $this->query($query);
		return $result->rowCount();
	}

	// делаем explain
	// @mixed - для удобства
	public function explain() {

		if (!isCLi()) {
			throw new parseException("Explain not in CLI MODE!!!");
		}

		if (!isTestServer()) {
			throw new parseException("Explain not in test-server!!!");
		}

		// подготавливаем запрос (очищаем его)
		$query = $this->_prepareQuery(func_get_args());
		//

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			return $this->_throwError("WHERE or LIMIT not found on SQL: {$query}");
		}

		//
		console(yellowText($query));
		$result = $this->query("explain format=json $query")->fetch();
		console($result["EXPLAIN"]);
		die; // чтобы не выполнялось ничего в explain
	}

	// пытаемся вставить данные в таблицу, если есть - изменяем
	public function insertOrUpdate(string $table, array $insert, array $update = null):int {

		if (!is_array($insert) || count($insert) < 1) {
			$this->_throwError("INSERT DATA is empty!");
		}

		if ($update === null) {
			$update = $insert;
		}
		$set   = $this->_makeQuery($update);
		$query = $this->_formatArray($table, [$insert]);
		$query .= "on duplicate key update
			{$set}
		";

		$this->_showDebugIfNeed($query);
		$result = $this->query($query);
		return $result->rowCount();
	}

	/**
	 * вставить значения в таблицу (возвращает lastInsertId() - если надо)
	 *
	 * @param string $table
	 * @param array  $insert
	 * @param bool   $is_ignore
	 *
	 * @return string|void
	 * @throws queryException
	 * @mixed - хз что вернет
	 */
	public function insert(string $table, array $insert, bool $is_ignore = true) {

		if (!is_array($insert) || count($insert) < 1) {
			return $this->_throwError("INSERT DATA is empty!");
		}

		$query = $this->_formatArray($table, [$insert], $is_ignore);
		$this->_showDebugIfNeed($query);
		$this->query($query);
		return $this->lastInsertId();
	}

	// вставить массив значенй в таблицу (возвращает количество вставленных строк)
	public function insertArray(string $table, array $list):int {

		if (!is_array($list) || count($list) < 1) {
			$this->_throwError("INSERT DATA is empty!");
		}

		$query = $this->_formatArray($table, $list);
		$this->_showDebugIfNeed($query);
		$result = $this->query($query);
		return $result->rowCount();
	}

	/**
	 * пытаемся вставить список записей в таблицу, но для всех имеющихся записей с таким PRIMARY KEY
	 * будет произведено их обновление
	 *
	 * @return int
	 */
	public function insertArrayOrUpdate(string $table, array $list, array $update):int {

		$set   = $this->_makeQuery($update);
		$query = $this->_formatArray($table, $list);
		$query .= "on duplicate key update
			{$set}
		";

		$this->_showDebugIfNeed($query);
		$result = $this->query($query);
		return $result->rowCount();
	}

	// возвращает массив значений первого столбца или пустой массив, если записи не найдены
	public function getAllColumn(...$args):array {

		// подготавливаем запрос (очищаем его)
		$query = $this->_prepareQuery($args);

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			$this->_throwError("WHERE or LIMIT not found on SQL: {$query}");
		}

		$this->_showDebugIfNeed($query);
		$result = $this->query($query)->fetchAll(PDO::FETCH_COLUMN);
		return is_array($result) ? $result : [];
	}

	/**
	 * Возвращает <b>одномерный</b> ассоциативный массив записей или пустой массив
	 * В запросе ожидается ровно два стобца
	 * Индексы массива беруться из первого столбца, значения берутся из второго
	 *
	 * @param mixed ...$args
	 *
	 * @return array
	 * @throws queryException
	 */
	public function getAllKeyPair(...$args):array {

		// подготавливаем запрос (очищаем его)
		$query = $this->_prepareQuery($args);

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			$this->_throwError("WHERE or LIMIT not found on SQL: {$query}");
		}

		$this->_showDebugIfNeed($query);
		$result = $this->query($query)->fetchAll(PDO::FETCH_KEY_PAIR);
		return is_array($result) ? $result : [];
	}

	/**
	 * Выпроняет запрос и возвращает массив классов
	 *
	 * Параметры использующиеся в запросе:
	 *
	 * ?s ("string") - strings (also DATE, FLOAT and DECIMAL)
	 *
	 * ?i ("integer") - the name says it all
	 *
	 * ?a ("array") - complex placeholder for IN() operator (substituted with string of 'a','b','c' format, without
	 * parentesis)
	 *
	 * ?u ("update") - понимает такие вещи как ["value"=>"value + 1"] в этом случае идет инкремент, если же просто
	 * ["value"=>"3"];
	 *
	 * ?p ("parsed") - special type placeholder, for inserting already parsed statements without any processing, to
	 * avoid double parsing
	 *
	 * @param string                 $class_name имя класса которые будут получены в массиве
	 * @param string|int|float|array ...$args    запрос и параметры выполнения запроса
	 *
	 * @return object[] массив элементов класса $class_name
	 * @throws queryException
	 */
	public function getAllObjects(string $class_name, ...$args):array {

		if ($class_name === "") {
			$this->_throwError("class name should not be empty");
		}

		// подготавливаем запрос (очищаем его)
		$query = $this->_prepareQuery($args);

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			$this->_throwError("WHERE or LIMIT not found on SQL: {$query}");
		}

		$this->_showDebugIfNeed($query);
		$result = $this->query($query)->fetchAll(PDO::FETCH_CLASS, $class_name);
		return is_array($result) ? $result : [];
	}

	/**
	 * Выпроняет запрос и возвращает массив классов <b>индексированный значением первого столбца</b>
	 * возвращаются только <b>уникальные</b> значения по первому столбцу (чаще всего - PK)
	 * Параметры использующиеся в запросе:
	 *
	 * ?s ("string") - strings (also DATE, FLOAT and DECIMAL)
	 *
	 * ?i ("integer") - the name says it all
	 *
	 * ?a ("array") - complex placeholder for IN() operator (substituted with string of 'a','b','c' format, without
	 * parentesis)
	 *
	 * ?u ("update") - понимает такие вещи как ["value"=>"value + 1"] в этом случае идет инкремент, если же просто
	 * ["value"=>"3"];
	 *
	 * ?p ("parsed") - special type placeholder, for inserting already parsed statements without any processing, to
	 * avoid double parsing
	 *
	 * @param string                 $class_name имя класса которые будут получены в массиве
	 * @param string|int|float|array ...$args    запрос и параметры выполнения запроса
	 *
	 * @return object[] массив элементов класса $class_name
	 * @throws queryException
	 */
	public function getAllObjectsUnique(string $class_name, ...$args):array {

		if ($class_name === "") {
			$this->_throwError("class name should not be empty");
		}
		// подготавливаем запрос (очищаем его)
		$query = $this->_prepareQuery($args);

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			$this->_throwError("WHERE or LIMIT not found on SQL: {$query}");
		}

		$this->_showDebugIfNeed($query);
		$result = $this->query($query)->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_CLASS, $class_name);
		return is_array($result) ? $result : [];
	}

	/**
	 * Выпроняет запрос и возвращает объект переданного в параметр класса
	 *
	 * Параметры использующиеся в запросе:
	 *
	 * ?s ("string") - strings (also DATE, FLOAT and DECIMAL)
	 *
	 * ?i ("integer") - the name says it all
	 *
	 * ?a ("array") - complex placeholder for IN() operator (substituted with string of 'a','b','c' format, without
	 * parentesis)
	 *
	 * ?u ("update") - понимает такие вещи как ["value"=>"value + 1"] в этом случае идет инкремент, если же просто
	 * ["value"=>"3"];
	 *
	 * ?p ("parsed") - special type placeholder, for inserting already parsed statements without any processing, to
	 * avoid double parsing
	 *
	 * @param string                 $class_name имя класса которые будут получены в массиве
	 * @param string|int|float|array ...$args    запрос и параметры выполнения запроса
	 *
	 * @return mixed объект класса $class_name или FALSE если запрос не вернул строк
	 * @noinspection Helper
	 * @throws queryException
	 */
	public function getOneObject(string $class_name, ...$args) {

		if ($class_name === "") {
			$this->_throwError("class name should not be empty");
		}

		// подготавливаем запрос (очищаем его)
		$query = $this->_prepareQuery($args);

		// если нет лимита
		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			$this->_throwError("WHERE or LIMIT not found on SQL: {$query}");
		}

		$this->_showDebugIfNeed($query);
		return $this->query($query)->fetchObject($class_name);
	}

	// -----------------------------------------------------------------
	// PROTECTED
	// -----------------------------------------------------------------

	// ескейпим строку
	protected function _escapeString(string $value = null):string {

		if ($value === null) {
			return "NULL";
		}

		return $this->quote($value);
	}

	// удаляем кавычки из текста (нужно для названия столбцов)
	protected function _removeQuote(string $value):string {

		$value = str_replace("\"", "", $value);
		$value = str_replace("'", "", $value);
		$value = str_replace("`", "", $value);
		return $value;
	}

	// создает часть запроса из IN значений
	protected function _createIN(array $data):string {

		if (!is_array($data)) {
			$this->_throwError("Value for IN (?a) placeholder should be array");
		}
		if (!$data) {
			return "NULL";
		}
		$query = $comma = "";
		foreach ($data as $value) {

			$query .= $comma . $this->_escapeString($value);
			$comma = ",";
		}

		return $query;
	}

	/**
	 * ескейпим int
	 *
	 * @param $value
	 *
	 * @return int|string
	 * @throws queryException
	 * @mixed тут что угодно
	 */
	protected function _escapeInt($value) {

		if ($value === null) {
			return "NULL";
		}

		if (!is_numeric($value)) {
			$this->_throwError("Integer (?i) placeholder expects numeric value, " . gettype($value) . " given");
		}

		if (is_float($value)) {

			$value = number_format($value, 0, ".", ""); // may lose precision on big numbers
			return $value;
		}

		return intval($value);
	}

	// форматируем array для вставки
	// @long
	protected function _formatArray(string $table, array $ar_set, bool $is_ignore = true, bool $is_delayed = false):string {

		$ins_key = true;
		$keys    = "";
		$values  = "";
		$qq      = "";
		foreach ($ar_set as $ar_query) {

			foreach ($ar_query as $key => $value) {

				if ($ins_key) {
					$keys .= "`" . $this->_removeQuote($key) . "`,";
				}

				if (is_array($value)) {
					$value = toJson($value);
				}

				if ($value instanceof PdoFuncValue) {

					$param_list = array_map([$this, "_escapeString"], $value->param_list);
					$param_str  = implode(",", $param_list);
					$values     .= "{$value->function_name}({$param_str}),";
					continue;
				}

				$values .= $this->_escapeString($value) . ",";
			}
			$values  = substr($values, 0, -1);
			$ins_key = false;
			$qq      .= "($values),";
			$values  = "";
		}
		$keys = substr($keys, 0, -1);

		$table   = strpos($table, ".") === false ? "`$table`" : $table;
		$extra   = $is_ignore ? "IGNORE" : "";
		$delayed = $is_delayed ? "delayed" : "";
		return "INSERT $delayed $extra INTO $table ($keys)  \n VALUES \n" . substr($qq, 0, -1);
	}

	// понимает такие вещи как ["value"=>"value + 1"] в этом случае идет инкремент, если же просто ["value"=>"3"];
	// @long
	protected function _makeQuery(array $set):string {

		$temp = [];
		foreach ($set as $k => $v) {

			// чистим название ключа
			$k = $this->_removeQuote($k);

			//
			if (is_array($v)) {

				// если массив джейсоним ))
				$v = toJson($v);
			} elseif ($v instanceof PdoFuncValue) {

				$param_list = array_map([$this, "_escapeString"], $v->param_list);
				$param_str  = implode(",", $param_list);
				$temp[]     = "`{$k}` = " . "{$v->function_name}({$param_str})";
				continue;
			} elseif ((inHtml($v, "-") || inHtml($v, "+")) && inHtml($v, $k)) {

				// если это контрукция инкремента / декремента вида value = value + 1
				$gg = str_replace($k, "", $v);
				$gg = str_replace("-", "", $gg);
				$gg = intval(trim(str_replace("+", "", $gg)));

				// если инкремент декремент больше 0
				if ($gg > 0) {

					if (inHtml($v, "-")) {

						$temp[] = "`{$k}` = `{$k}` - {$gg}";
						continue;
					} else {

						$temp[] = "`{$k}` = `{$k}` + {$gg}";
						continue;
					}
				}
			}

			//
			$temp[] = "`{$k}` = " . $this->_escapeString($v);
		}

		return implode(", ", $temp);
	}

	// вовзвращает корректный sql запрос
	// @long
	protected function _prepareQuery(array $args):string {

		$query = "";
		$raw   = array_shift($args);
		$array = preg_split("~(\?[siuap])~u", $raw, -1, PREG_SPLIT_DELIM_CAPTURE);
		$anum  = count($args);
		$pnum  = floor(count($array) / 2);
		if ($pnum != $anum) {
			$this->_throwError("Number of args ($anum) doesn\"t match number of placeholders ($pnum) in [$raw]");
		}

		// проверяем что имя таблицы обернуто в косые кавычки `
		$table = "";
		if (
			preg_match_all("#SELECT.*?FROM(.+?) #ism", $raw, $matches) ||
			preg_match_all("#DELETE[ ]+FROM(.+?) #ism", $raw, $matches) ||
			preg_match_all("#UPDATE (.+?) #ism", $raw, $matches) ||
			preg_match_all("#INSERT.+?INTO (.+?) #ism", $raw, $matches)
		) {
			$table = trim($matches[1][0]);
		}

		if (substr($table, 0, 1) != "`" || substr($table, -1) != "`") {
			$this->_throwError("Название таблицы не обернуто в косые кавычки -> ` <-, запрос: {$raw}");
		}

		// проверяем, что нигде в запросе не переданы числа (а только все через подготовленные выражения)
		if (preg_match("#[0-9]+#ism", preg_replace("#`.+?`#ism", "", $raw))) {
			$this->_throwError("В запросе присуствуют цифры, которые не являются частью названия таблицы или полей!\nПРОВЕРЬТЕ: Все названия полей должны быть в косых ковычках, а любые значения только через переданные параметры.\n{$raw}");
		}

		//
		foreach ($array as $i => $part) {

			if (($i % 2) == 0) {

				$query .= $part;
				continue;
			}

			$value = array_shift($args);
			switch ($part) {

				case "?s":
					$part = $this->_escapeString($value);
					break;
				case "?i":
					$part = $this->_escapeInt($value);
					break;
				case "?a":
					$part = $this->_createIN($value);
					break;
				case "?u":
					$part = $this->_makeQuery($value);
					break;
				case "?p":
					$part = $value;
					break;
			}
			$query .= $part;
		}

		return $query;
	}

	/**
	 * кидает исключение
	 *
	 * @param string $message
	 *
	 * @throws queryException
	 * @mixed
	 */
	protected function _throwError(string $message) {

		throw new QueryFatalException($message);
	}

	// показываем debug в запросе если надо
	protected function _showDebugIfNeed(string $query):void {

		if (!defined("DEBUG_MYSQL")) {
			return;
		}
		if (DEBUG_MYSQL != true) {
			return;
		}

		console(blueText($query));
	}

}

/**
 * служебный класс для работы PDO его смысл в изменении работы функции execute
 */
class myPDOStatement extends PDOStatement {

	/**
	 * вызов запроса
	 *
	 * @param array $data
	 *
	 * @return $this
	 * @mixed
	 */
	public function execute($data = []):bool {

		parent::execute($data);
		return $this;
	}
}

/**
 * класс содержит вспомогательные функции для описания параметров подключения к mysql в конфиг-файле
 * api/conf/sharding.php
 */
class shardingConf {

	// существующие разновидности шардирования
	public const SHARDING_TYPE_NONE  = "none";
	public const SHARDING_TYPE_INT   = "int";
	public const SHARDING_TYPE_HEX   = "hex";
	public const SHARDING_TYPE_MONTH = "month";

	/**
	 * сформировать информацию, для описания шардирования типа int
	 *
	 * @param int $from
	 * @param int $to
	 *
	 * @return int[]
	 */
	public static function makeDataForIntShardingType(int $from, int $to):array {

		return [
			"from" => (int) $from,
			"to"   => (int) $to,
		];
	}

	/**
	 * сформировать информацию, для описания шардирования типа hex
	 *
	 * @param string $max_hex
	 *
	 * @return array
	 */
	public static function makeDataForHexShardingType(string $max_hex):array {

		return [
			"max_hex" => (string) $max_hex,
		];
	}

	/**
	 * сформировать информацию, для описания шардирования типа month
	 *
	 * @param string $month_sharding например: 2018_6
	 *
	 * @return array
	 */
	public static function makeDataForMonthShardingType(string $month_sharding):array {

		return [
			"month_sharding" => (string) $month_sharding,
		];
	}

	/**
	 * генерирует поле schemas для одного месяца
	 *
	 * @param string $db_postfix
	 * @param array  $table_list
	 * @param array  $extra_merge_list
	 *
	 * @return array
	 */
	public static function makeMonthShardingSchemas(string $db_postfix, array $table_list, array $extra_merge_list = []):array {

		// разбиваем 2019_6 на 2019 и 6
		[$year, $month] = explode("_", $db_postfix);

		$day_max = cal_days_in_month(CAL_GREGORIAN, $month, $year); // получаем количество дней в месяце

		// бежим по каждой таблице
		$output = [];
		foreach ($table_list as $k1 => $v1) {

			// заполняем от 1 дня месяца до последнего
			for ($i = 1; $i <= $day_max; $i++) {

				$postfix_table_name          = "{$k1}_{$i}"; // имя таблицы с префиксом (dynamic_14)
				$output[$postfix_table_name] = $v1;
			}
		}

		// добавляем к ответу extra
		return array_merge($output, $extra_merge_list);
	}

	/**
	 * генерирует поле schemas для таблиц от 0 до hex (например ff)
	 *
	 * @param string $max_hex
	 * @param array  $table_list
	 * @param array  $extra_merge_list
	 *
	 * @return array
	 */
	public static function makeHexShardingSchemas(string $max_hex, array $table_list, array $extra_merge_list = []):array {

		$hex_len     = strlen($max_hex); // чтобы все хексы получались одной длины
		$dec_max_hex = hexdec($max_hex); // переводим макс hex в int чтобы пробежать циклом

		// бежим по всем табличкам
		$output = [];
		foreach ($table_list as $k1 => $v1) {

			// заполняем от 0 до hex
			for ($i = 0; $i <= $dec_max_hex; $i++) {

				$postfix                     = sprintf("%0{$hex_len}s", dechex($i));
				$postfix_table_name          = "{$k1}_{$postfix}"; // имя таблицы с префиксом (blacklist_01)
				$output[$postfix_table_name] = $v1;
			}
		}

		// добавляем к ответу extra
		return array_merge($output, $extra_merge_list);
	}

	/**
	 * генерирует поле schemas для таблиц от 0 до int
	 *
	 * @param int   $from
	 * @param int   $to
	 * @param array $table_list
	 * @param array $extra_merge_list
	 *
	 * @return array
	 */
	public static function makeIntShardingSchemas(int $from, int $to, array $table_list, array $extra_merge_list = []):array {

		$output = [];
		foreach ($table_list as $k1 => $v1) {

			// заполняем от 0 до hex
			for ($i = $from; $i <= $to; $i++) {

				$postfix_table_name          = "{$k1}_{$i}"; // имя таблицы с постфиксом (left_menu_1)
				$output[$postfix_table_name] = $v1;
			}
		}

		// добавляем к ответу extra
		return array_merge($output, $extra_merge_list);
	}
}

/**
 * класс для обертки значения в функцию
 *
 * Class PdoFuncValue
 */
class PdoFuncValue {

	private function __construct(
		public string $function_name,
		public array  $param_list,
	) {

	}

	// init
	public static function init(string $function_name, ...$param):self {

		return new self($function_name, $param);
	}
}