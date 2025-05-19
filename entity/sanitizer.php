<?php

/**
 * Класс для очистки данных
 */
class Entity_Sanitizer {

	/**
	 * Удалить все utf8mb4 символы из строки перед отправкой запроса в БД
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public static function sanitizeUtf8Query(string $text):string {

		return preg_replace("/[\xF0-\xF7].../s", "", $text);
	}
}