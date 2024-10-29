<?php

namespace BaseFrame\Database;

use BaseFrame\Database\PDODriver\DebugMode;
use BaseFrame\Exception\Gateway\QueryFatalException;

/**
 * Класс для работы с базой данных SQL.
 *
 * Подстановки, использующиеся в запросе:
 *  ?s ("string")  - strings (also DATE, FLOAT and DECIMAL)
 *  ?i ("integer") - the name says it all
 *  ?a ("array")   - complex placeholder for IN() operator (substituted with string of 'a','b','c' format, without parentesis)
 *  ?u ("update")  - понимает такие вещи как ["value"=>"value + 1"] в этом случае идет инкремент, если же просто ["value"=>"3"];
 *  ?p ("parsed")  - special type placeholder, for inserting already parsed statements without any processing, to avoid double parsing
 */
class PDODriver extends \PDO {

	public const ISOLATION_READ_UNCOMMITTED = "READ UNCOMMITTED";
	public const ISOLATION_REPEATABLE_READ  = "REPEATABLE READ";
	public const ISOLATION_READ_COMMITTED   = "READ COMMITTED";
	public const ISOLATION_SERIALIZABLE     = "SERIALIZABLE";

	protected array       $_hooks              = [];
	protected string      $_database           = "";
	protected DebugMode   $_debug_mode         = DebugMode::NONE;

	/**
	 * Статический конструктор из конфигурационного файла.
	 */
	public static function instance(Config\Connection $conn_conf, Config\Query $query_conf):static {

		$instance = new static($conn_conf->getDSN(), $conn_conf->user, $conn_conf->password, $conn_conf->options);

		$instance->_database           = $conn_conf->db_name;
		$instance->_debug_mode         = $query_conf->debug_mode;

		/** @var \BaseFrame\Database\Hook $hook */
		foreach ($query_conf->hooks as $hook) {
			$instance->_hooks[$hook->getDb()][$hook->getTable()][$hook->getAction()][$hook->getColumn()][] = $hook;
		}

		return $instance;
	}

	/**
	 * Класс для работы с базой данных SQL.
	 */
	public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null) {

		parent::__construct($dsn, $username, $password, $options);
	}

	/**
	 * @inheritDoc
	 */
	public function query(mixed ...$args):\PDOStatement|false {

		return parent::query(...$args);
		// return new \PDOStatement();
	}

	/**
	 * Устанавливаем уровень транзакции.
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function setTransactionIsolationLevel(string $isolation_level):bool {

		$isolation_level_list = [
			static::ISOLATION_READ_UNCOMMITTED,
			static::ISOLATION_REPEATABLE_READ,
			static::ISOLATION_READ_COMMITTED,
			static::ISOLATION_SERIALIZABLE,
		];

		if (!in_array($isolation_level, $isolation_level_list)) {
			throw new QueryFatalException("Unknown isolation level = '{$isolation_level}', please use one of myPDObasic::ISOLATION_ constants");
		}

		$query  = "SET TRANSACTION ISOLATION LEVEL {$isolation_level};";
		$result = $this->query($query);

		return $result->errorCode() == \PDO::ERR_NONE;
	}

	/**
	 * Начинаем транзакцию.
	 */
	public function beginTransaction():bool {

		$this->_debug("BEGIN");
		return parent::beginTransaction();
	}

	/**
	 * Коммитим транзакцию (может быть удачно|нет)
	 */
	public function commit():bool {

		$this->_debug("COMMIT");
		return parent::commit();
	}

	/**
	 * Коммитим транзакцию (бросаем исключение если не вышло)
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function forceCommit():void {

		if ($this->commit() === false) {
			throw new QueryFatalException("Transaction commit failed");
		}
	}

	/**
	 * Отменяем транзакцию.
	 */
	public function rollback():bool {

		$this->_debug("ROLLBACK");
		return parent::rollBack();
	}

	/**
	 * Добавляет строку в таблицу, возвращает lastInsertId(), если вставка была успешной.
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function insert(string $table, array $insert, bool $is_ignore = true):false|string {

		if (count($insert) < 1) {
			throw new QueryFatalException("INSERT DATA is empty!");
		}

		$query = $this->_formatInsertQuery($table, [$insert], $is_ignore);

		$this->_debug($query);
		$this->query($query);

		return $this->lastInsertId();
	}

	/**
	 * Вставляет массив значений в таблицу (возвращает количество вставленных строк).
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function insertArray(string $table, array $insert):int {

		if (count($insert) < 1) {
			throw new QueryFatalException("INSERT DATA is empty!");
		}

		$query = $this->_formatInsertQuery($table, $insert);

		$this->_debug($query);
		return $this->query($query)->rowCount();
	}

	/**
	 * Пытаемся вставить строку в таблицу, если есть пересечение по constraint
	 * обновляет имеющуюся строку. Возвращает непонятное число.
	 *
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function insertOrUpdate(string $table, array $insert, array $update = null):int {

		if (count($insert) < 1) {
			throw new QueryFatalException("INSERT DATA is empty!");
		}

		if ($update === null) {
			$update = $insert;
		}

		$set   = $this->_formatUpdateArguments($table, $update);
		$query = $this->_formatInsertQuery($table, [$insert]);
		$query .= "ON DUPLICATE KEY UPDATE {$set}";

		$this->_debug($query);
		return $this->query($query)->rowCount();
	}

	/**
	 * Пытаемся вставить данные в таблицу, если есть пересечение по constraint обновляет их.
	 * Возвращает непонятное число, которым лучше не пользоваться.
	 *
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function insertArrayOrUpdate(string $table, array $insert, array $update):int {

		if (count($insert) < 1) {
			throw new QueryFatalException("INSERT DATA is empty!");
		}

		$set   = $this->_formatUpdateArguments($table, $update);
		$query = $this->_formatInsertQuery($table, $insert);
		$query .= "ON DUPLICATE KEY UPDATE {$set}";

		$this->_debug($query);
		return $this->query($query)->rowCount();
	}

	/**
	 * Обновляет записи в БД.
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function update():int {

		// подготавливаем запрос (очищаем его)
		[$query] = $this->_formatRawQuery(func_get_args());

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			throw new QueryFatalException("WHERE or LIMIT not found on SQL: {$query}");
		}

		$this->_debug($query);
		return $this->query($query)->rowCount();
	}

	/**
	 * Удаляет записи из базы.
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function delete():int {

		// подготавливаем запрос (очищаем его)
		[$query] = $this->_formatRawQuery(func_get_args());

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			throw new QueryFatalException("WHERE or LIMIT not found on SQL: {$query}");
		}

		$this->_debug($query);
		return $this->query($query)->rowCount();
	}

	/**
	 * Достает одну запись из таблицы. Если запись не найдена, вернет пустой массив.
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function getOne():array {

		// подготавливаем запрос (очищаем его)
		[$query, $table] = $this->_formatRawQuery(func_get_args());

		// если нет лимита
		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			throw new QueryFatalException("WHERE or LIMIT not found on SQL: {$query}");
		}

		$this->_debug($query);
		$result = $this->query($query)->fetch();

		if (!is_array($result)) {
			return [];
		}

		[$result] = $this->_afterRead($table, [$result]);
		return $result;
	}

	/**
	 * Достает из базы несколько записей и возвращает их как массивы строк.
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function getAll():array {

		// подготавливаем запрос (очищаем его)
		[$query, $table] = $this->_formatRawQuery(func_get_args());

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			throw new QueryFatalException("WHERE or LIMIT not found on SQL: {$query}");
		}

		$this->_debug($query);
		$result = $this->query($query)->fetchAll();

		if (!is_array($result)) {
			return [];
		}

		return $this->_afterRead($table, $result);
	}

	/**
	 * Выполняет запрос и возвращает объект переданного в параметр класса
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function getOneObject(string $class_name, ...$args) {

		if (!class_exists($class_name)) {
			throw new QueryFatalException("passed incorrect class name");
		}

		$raw_result = $this->getOne(...$args);

		if (count($raw_result) === 0) {
			return [];
		}

		$instance = new $class_name();

		foreach ($raw_result as $column => $value) {
			$instance->$column = $value;
		}

		return $instance;
	}

	/**
	 * Выполняет запрос и из полученных данных формирует массив объектов указанного типа.
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function getAllObjects(string $class_name, ...$args):array {

		if (!class_exists($class_name)) {
			throw new QueryFatalException("passed incorrect class name");
		}

		// PDO::FETCH_CLASS не используем,
		// итерировать объекты потом не хочется для поиска данных для перезаписи
		$raw_result = $this->getAll(...$args);
		$result     = [];

		foreach ($raw_result as $row) {

			$instance = new $class_name();

			foreach ($row as $column => $value) {
				$instance->$column = $value;
			}

			$result[] = $instance;
		}

		return $result;
	}

	/**
	 * Возвращает массив значений первого столбца.
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function getAllColumn(...$args):array {

		// подготавливаем запрос (очищаем его)
		[$query, $table] = $this->_formatRawQuery($args);

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			throw new QueryFatalException("WHERE or LIMIT not found on SQL: {$query}");
		}

		// вытащим список колонок из запроса
		$selected_columns = $this->_parseSelectStatementColumns($query);

		if (count($selected_columns) !== 1) {
			throw new QueryFatalException("Expect 1 argument, " . count($selected_columns) . " passed");
		}

		$this->_debug($query);
		$result = $this->query($query)->fetchAll();

		if (!is_array($result)) {
			return [];
		}

		return array_column($this->_afterRead($table, $result), $selected_columns[0]);
	}

	/**
	 * Возвращает <b>одномерный</b> ассоциативный массив записей или пустой массив
	 * В запросе ожидается ровно два столбца. Индексы массива берутся из первого столбца,
	 * значения берутся из второго
	 *
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function getAllKeyPair(...$args):array {

		// подготавливаем запрос (очищаем его)
		[$query, $table] = $this->_formatRawQuery($args);

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			throw new QueryFatalException("WHERE or LIMIT not found on SQL: {$query}");
		}

		// вытащим список колонок из запроса
		$selected_columns = $this->_parseSelectStatementColumns($query);

		if (count($selected_columns) !== 2) {
			throw new QueryFatalException("Expect 2 arguments, " . count($selected_columns) . " passed");
		}

		$this->_debug($query);
		$result = $this->query($query)->fetchAll();

		if (!is_array($result)) {
			return [];
		}

		$result = $this->_afterRead($table, $result);
		return array_combine(array_column($result, $selected_columns[0]), array_column($result, $selected_columns[1]));
	}

	/**
	 * Выполняет запрос и возвращает массив классов <b>индексированный значением первого столбца</b>
	 * возвращаются только <b>уникальные</b> значения по первому столбцу (чаще всего - PK)
	 *
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	public function getAllObjectsUnique(string $class_name, ...$args):array {

		if (!class_exists($class_name)) {
			throw new QueryFatalException("passed incorrect class name");
		}

		// подготавливаем запрос (очищаем его)
		[$query, $table] = $this->_formatRawQuery($args);

		if (!inHtml(strtolower($query), "limit") || !inHtml(strtolower($query), "where")) {
			throw new QueryFatalException("WHERE or LIMIT not found on SQL: {$query}");
		}

		$this->_debug($query);
		$raw_result = $this->query($query)->fetchAll();

		if (!is_array($raw_result)) {
			return [];
		}

		$raw_result = $this->_afterRead($table, $raw_result);

		$key_column = array_key_first($raw_result[0]);
		$result     = [];

		foreach ($raw_result as $row) {

			if (isset($result[$row[$key_column]])) {
				continue;
			}

			$instance = new $class_name();

			foreach ($row as $column => $value) {
				$instance->$column = $value;
			}

			$result[] = $instance;
		}

		return $result;
	}

	/**
	 * Вызывает все хук-обработчики после чтения данных.
	 */
	protected function _afterRead(string $table, array $rows):array {

		// если хуков для чтения для таблицы нет, то ничего не делаем
		if (!isset($this->_hooks[$this->_database][$table][Hook\Action::READ->value])) {
			return $rows;
		}

		return $this->_callHooks($this->_hooks[$this->_database][$table][Hook\Action::READ->value], $rows);
	}

	/**
	 * Вызывает все хук-обработчики перед записью данных.
	 */
	protected function _beforeWrite(string $table, array $rows):array {

		// если хуков для записи для таблицы нет, то ничего не делаем
		if (!isset($this->_hooks[$this->_database][$table][Hook\Action::WRITE->value])) {
			return $rows;
		}

		return $this->_callHooks($this->_hooks[$this->_database][$table][Hook\Action::WRITE->value], $rows);
	}

	/**
	 * Вызывает все хук-обработчики для массива строк.
	 * @throws
	 */
	protected function _callHooks(array $table_hooks, array $rows):array {

		// ожидаем, что хуков в БД меньше, чем полей в таблице, что логично
		// поэтому проход начинаем от хуков, а не от строк, так первые два
		// цикла не выполнятся больше одного раза в большинстве случаев
		foreach ($table_hooks as $columns_hooks) {

			/** @var Hook $column_hook */
			foreach ($columns_hooks as $column_hook) {

				$column_name = $column_hook->getColumn();

				foreach ($rows as $i => $row) {

					// возможно тут можно как-то еще срезать и не ходить лишний раз,
					// вряд ли строки будут различаться набором колонок, но пока
					// пусть проверяет все строки для надежности
					if (!isset($row[$column_name])) {
						continue;
					}

					// перезаписываем значение в колонке функцией хука
					try {
						$rows[$i][$column_name] = $column_hook->exec($row[$column_name]);
					} catch (\Throwable $e) {
						$rows[$i][$column_name] = $column_hook->recover($row[$column_name], $e);
					}
				}
			}
		}

		return $rows;
	}

	/**
	 * Вытаскивает из select запроса имена запрошенных полей.
	 */
	protected function _parseSelectStatementColumns(string $raw_query):array {

		$matches = [];
		preg_match("#SELECT(.*?)FROM#ism", $raw_query, $matches);

		$chunks = explode(",", trim($matches[1]));
		$names  = [];

		foreach ($chunks as $chunk) {

			$exploded = preg_split("#\s+#", trim($chunk));
			$names[]  = trim($exploded[0], "`");
		}

		return $names;
	}

	/**
	 * Подготавливает insert запрос.
	 */
	protected function _formatInsertQuery(string $table, array $ar_set, bool $is_ignore = true, bool $is_delayed = false):string {

		$ins_key = true;
		$keys    = "";
		$values  = "";
		$qq      = "";

		// выполняем преобразование перед записью данных
		$ar_set = $this->_beforeWrite($table, $ar_set);

		foreach ($ar_set as $ar_query) {

			foreach ($ar_query as $key => $value) {

				if ($ins_key) {
					$keys .= "`" . $this->_clearColumnQuotes($key) . "`,";
				}

				if (is_array($value)) {
					$value = toJson($value);
				}

				if ($value instanceof \PdoFuncValue) {

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

	/**
	 * Формирует запрос из raw-строки запроса и списка аргументов.
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	protected function _formatRawQuery(array $args):array {

		$result_query = "";
		$table        = "";

		$raw_query        = array_shift($args);
		$raw_query_chunks = preg_split("~(\?[siuap])~u", $raw_query, -1, PREG_SPLIT_DELIM_CAPTURE);

		if (count($args) !== (int) floor(count($raw_query_chunks) / 2)) {
			throw new QueryFatalException("Number of args doesn't match number of placeholders in [$raw_query]");
		}

		if (
			preg_match_all("#SELECT.*?FROM(.+?) #ism", $raw_query, $matches) ||
			preg_match_all("#DELETE[ ]+FROM(.+?) #ism", $raw_query, $matches) ||
			preg_match_all("#UPDATE (.+?) #ism", $raw_query, $matches) ||
			preg_match_all("#INSERT.+?INTO (.+?) #ism", $raw_query, $matches)
		) {
			$table = trim($matches[1][0]);
		}

		// проверяем что имя таблицы обернуто в косые кавычки `
		if (!str_starts_with($table, "`") || !str_ends_with($table, "`")) {
			throw new QueryFatalException("Название таблицы не обернуто в косые кавычки -> ` <-, запрос: {$raw_query}");
		}

		// проверяем, что нигде в запросе не переданы числа (а только все через подготовленные выражения)
		if (preg_match("#[0-9]+#ism", preg_replace("#`.+?`#ism", "", $raw_query))) {
			throw new QueryFatalException("В запросе присутствуют цифры, которые не являются частью названия таблицы или полей!\nПРОВЕРЬТЕ: Все названия полей должны быть в косых кавычках, а любые значения только через переданные параметры.\n{$raw_query}");
		}

		// имя таблицы, оно должно быть передано в списке аргументов
		$table_name = "";

		// перебираем куски запроса, четные элементы будут плейсхолдерами
		// для подстановок, нечетные просто частями запроса
		foreach ($raw_query_chunks as $i => $chunk) {

			if (($i % 2) == 0) {

				$result_query .= $chunk;
				continue;
			}

			// достаем следующий аргумент из списка
			$value = array_shift($args);

			// пытаемся поймать имя таблицы, по идее оно всегда должно быть первым с подстановкой ?p
			if ($table_name === "" && $chunk === "?p") {

				$table_name = $value;
			}

			// формируем значение для выражения в запросе
			$result_query .= match ($chunk) {
				"?s"    => $this->_escapeString($value),
				"?i"    => $this->_escapeInt($value),
				"?a"    => $this->_createIN($value),
				"?u"    => $this->_formatUpdateArguments($table_name, $value),
				"?p"    => $value,
				default => throw new QueryFatalException("запрос содержит неизвестную подстановку $chunk"),
			};
		}

		return [$result_query, $table_name];
	}

	/**
	 * Форматирует выражение с ключами и значениями для update запроса.
	 * Понимает конструкции вида ["value"=>"3"] и ["value"=>"value + 1"].
	 */
	protected function _formatUpdateArguments(string $table, array $set):string {

		$result = [];
		[$set] = $this->_beforeWrite($table, [$set]);

		foreach ($set as $k => $v) {

			// чистим название ключа
			$k = $this->_clearColumnQuotes($k);

			if (is_array($v)) {

				$v = toJson($v);
			} elseif ($v instanceof \PdoFuncValue) {

				$param_list = array_map([$this, "_escapeString"], $v->param_list);
				$param_str  = implode(",", $param_list);
				$result[]   = "`{$k}` = " . "{$v->function_name}({$param_str})";

				continue;
			} elseif ((inHtml($v, "-") || inHtml($v, "+")) && inHtml($v, $k)) {

				// если это конструкция инкремента / декремента вида value = value + 1
				$gg = str_replace($k, "", $v);
				$gg = str_replace("-", "", $gg);
				$gg = (int) trim(str_replace("+", "", $gg));

				// если инкремент/декремент больше 0
				if ($gg > 0) {

					if (inHtml($v, "-")) {
						$result[] = "`{$k}` = `{$k}` - {$gg}";
					} else {
						$result[] = "`{$k}` = `{$k}` + {$gg}";
					}

					continue;
				}
			}

			$result[] = "`{$k}` = " . $this->_escapeString($v);
		}

		return implode(", ", $result);
	}

	/**
	 * Экранирует строковый параметр.
	 */
	protected function _escapeString(string $value = null):string {

		if ($value === null) {
			return "NULL";
		}

		return $this->quote($value);
	}

	/**
	 * Экранирует целочисленное значение.
	 * @throws \BaseFrame\Exception\Gateway\QueryFatalException
	 */
	protected function _escapeInt($value):int {

		if ($value === null) {
			return "NULL";
		}

		if (!is_numeric($value)) {
			throw new QueryFatalException("Integer (?i) placeholder expects numeric value, " . gettype($value) . " given");
		}

		if (is_float($value)) {
			return number_format($value, 0, ".", "");
		}

		return (int) $value;
	}

	/**
	 * Удаляет все кавычки из строки.
	 */
	protected function _clearColumnQuotes(string $value):string {

		return trim($value, "\"'`");
	}

	/**
	 * Создает часть запроса из IN значений.
	 */
	protected function _createIN(array $data):string {

		// шляпа какая-то, но раз есть, то трогать не буду
		if (!$data) {
			return "NULL";
		}

		$query = "";
		$comma = "";

		foreach ($data as $value) {

			$query .= $comma . $this->_escapeString($value);
			$comma = ",";
		}

		return $query;
	}

	/**
	 * Пишем данные для отладки.
	 */
	protected function _debug(string $query):void {

		if ($this->_debug_mode === DebugMode::CLI) {
			console($query);
		}

		if ($this->_debug_mode === DebugMode::FILE) {
			debug($query);
		}
	}
}
