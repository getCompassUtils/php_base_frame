<?php

namespace BaseFrame\Icap\Client;

use BaseFrame\Exception\Domain\ParseFatalException;

/**
 * Класс ICAP ответа
 */
class IcapResponse {

	private int    $status_code; // статус
	private string $status_message; // причина
	private array  $headers      = []; // хедеры
	private array  $encapsulated = []; // хедер encapsulated

	/**
	 * Собрать ответ сервера из потока сокета
	 * Не добавляется тело из-за его бесполезности
	 *
	 * @param $fp
	 *
	 * @return self
	 * @throws ParseFatalException
	 */
	public static function fromStream($fp):self {

		$response    = new self();
		$status_line = fgets($fp);
		if (!preg_match("#^ICAP/\d+\.\d+\s+(\d+)\s+(.*)$#", $status_line, $matches)) {
			throw new ParseFatalException("Invalid ICAP response status line");
		}
		$response->status_code    = (int) $matches[1];
		$response->status_message = trim($matches[2]);

		while (($line = fgets($fp)) !== false && trim($line) !== "") {

			[$name, $value] = explode(":", $line, 2);
			$response->headers[trim($name)] = trim($value);
		}

		if (isset($response->headers["Encapsulated"])) {

			$parts = explode(",", $response->headers["Encapsulated"]);
			foreach ($parts as $part) {

				[$key, $val] = explode("=", trim($part));
				$response->encapsulated[$key] = (int) $val;
			}
		}

		return $response;
	}

	/**
	 * Получить код
	 *
	 * @return int
	 */
	public function getStatusCode():int {

		return $this->status_code;
	}

	/**
	 * Получить причину
	 * @return string
	 */
	public function getStatusMessage():string {

		return $this->status_message;
	}

	/**
	 * Получить хедеры
	 *
	 * @return array
	 */
	public function getHeaders():array {

		return $this->headers;
	}

	/**
	 * Получить разобранный хедер encapsulated
	 *
	 * @return array
	 */
	public function getEncapsulatedMap():array {

		return $this->encapsulated;
	}

	/**
	 * Проверить, изменен ли отправленный на ICAP сервер запрос
	 *
	 * @param IcapRequest $request
	 *
	 * @return bool
	 */
	public function isRequestModified(IcapRequest $request):bool {

		// если ICAP сервер поддерживает код 204, и его вернул, дальше смотреть и нечего
		// searchinform поддерживает
		if ($this->status_code === 204) {
			return false;
		}

		return $request->getEncapsulatedMap() !== $this->getEncapsulatedMap();
	}
}
