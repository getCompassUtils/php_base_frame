<?php

namespace BaseFrame\System;

use BaseFrame\Exception\Domain\InvalidMail;

/**
 * Класс для работы с электронными почтовыми адресами
 */
class Mail {

	private string $_mail; // значение почты

	/**
	 * @param string $mail
	 *
	 * @throws InvalidMail
	 */
	public function __construct(string $mail) {

		$this->_mail = $this->_validate($mail);
	}

	/**
	 * Валидируем почту
	 *
	 * @param string $mail
	 *
	 * @return string
	 * @throws InvalidMail
	 */
	private function _validate(string $mail):string {

		// нормализуем почту
		$mail = $this->_normalize($mail);

		if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
			throw new InvalidMail("invalid mail");
		}

		return $mail;
	}

	/**
	 * Приводим почту в нормальный вид
	 *
	 * @param string $mail
	 *
	 * @return string
	 */
	private function _normalize(string $mail):string {

		return strtolower(trim($mail));
	}

	/**
	 * Вернуть нормализованную почту
	 *
	 * @return string
	 */
	public function mail():string {

		return $this->_mail;
	}

	/**
	 * Вернуть часть с доменом из почты
	 *
	 * @return string
	 */
	public function getDomain():string {

		return substr($this->_mail, strpos($this->_mail, "@") + 1);
	}
}