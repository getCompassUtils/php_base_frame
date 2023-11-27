<?php

/**
 * класс для валидации данных
 */
class Entity_Validator {

	/**
	 * валидация имени профиля
	 *
	 * @param string $name
	 *
	 * @throws cs_InvalidProfileName
	 */
	public static function assertValidProfileName(string $name):void {

		if ($name === "") {
			throw new cs_InvalidProfileName();
		}
	}
}
