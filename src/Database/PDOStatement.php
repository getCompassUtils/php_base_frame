<?php

namespace BaseFrame\Database;

/**
 * Класс для агрегации ответа от PDO.
 */
class PDOStatement extends \PDOStatement {

	/**
	 * @inheritDoc
	 * @return bool
	 */
	#[\ReturnTypeWillChange]
	public function execute(array|null $params = []):mixed {

		parent::execute($params);
		return $this;
	}
}