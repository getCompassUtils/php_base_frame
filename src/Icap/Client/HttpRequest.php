<?php

namespace BaseFrame\Icap\Client;

/**
 * Класс http реквеств
 */
class HttpRequest {

	private string $method; // метод реквеста (GET, POST)
	private string $url; // url реквеста
	private array  $headers = []; // хедеры реквеста
	private mixed  $body_stream; // тело запроса

	/**
	 * Конструктор
	 *
	 * @param string $method
	 * @param string $url
	 * @param array  $headers
	 * @param null   $body_stream
	 */
	public function __construct(string $method, string $url, array $headers = [], $body_stream = null) {

		$this->method      = strtoupper($method);
		$this->url         = $url;
		$this->headers     = $headers;
		$this->body_stream = $body_stream;
	}

	/**
	 * Размер хедера
	 *
	 * @return int
	 */
	public function headersLength():int {

		$lines = "{$this->method} {$this->url} HTTP/1.1\r\n";
		foreach ($this->headers as $name => $value) {
			$lines .= "$name: $value\r\n";
		}
		return strlen($lines . "\r\n");
	}

	/**
	 * Есть ли тело у запроса
	 *
	 * @return bool
	 */
	public function hasBody():bool {

		return $this->body_stream !== null;
	}

	/**
	 * Разбить реквест по чанкам
	 *
	 * @return iterable
	 */
	public function toChunks():iterable {

		// если в хедере задан размер контента, отдаем все сразу
		if (isset($this->headers["Content-Length"])) {
			return $this->withFullBody();
		}

		// иначе возвращаем по частям
		return $this->withChunkedBody();
	}

	/**
	 * Отдать http хедер в виде чанков
	 *
	 * @return iterable
	 */
	public function headerToChunks():iterable {

		yield "{$this->method} {$this->url} HTTP/1.1\r\n";
		foreach ($this->headers as $name => $value) {
			yield "$name: $value\r\n";
		}
		yield "\r\n";
	}

	/**
	 * Отдать http тело в виде чанков
	 *
	 * @param int $preview_size
	 *
	 * @return iterable
	 */
	public function bodyToChunks(int $preview_size = 0):iterable {

		if ($this->body_stream) {

			if ($preview_size !== 0 && !feof($this->body_stream)) {

				$chunk = fread($this->body_stream, $preview_size);
				yield dechex(strlen($chunk)) . "\r\n" . $chunk . "\r\n";
			}

			while (!feof($this->body_stream)) {

				$chunk = fread($this->body_stream, 8192);
				yield dechex(strlen($chunk)) . "\r\n";
				yield $chunk . "\r\n";
			}
			yield "0\r\n\r\n";
		}
	}

	/**
	 * Генератор тела запроса, разбитого по частям
	 *
	 * @return iterable
	 */
	public function withChunkedBody():iterable {

		yield "{$this->method} {$this->url} HTTP/1.1\r\n";
		foreach ($this->headers as $name => $value) {
			yield "$name: $value\r\n";
		}
		yield "\r\n";

		if ($this->body_stream) {

			while (!feof($this->body_stream)) {

				$chunk = fread($this->body_stream, 8192);
				yield dechex(strlen($chunk)) . "\r\n";
				yield $chunk . "\r\n";
			}
			yield "0\r\n\r\n";
		}
	}

	/**
	 * Генератор тела запроса
	 *
	 * @return iterable
	 */
	public function withFullBody():iterable {

		yield "{$this->method} {$this->url} HTTP/1.1\r\n";
		foreach ($this->headers as $name => $value) {
			yield "$name: $value\r\n";
		}
		yield "\r\n";

		if ($this->body_stream) {

			$body = stream_get_contents($this->body_stream);
			yield dechex(strlen($body)) . "\r\n";
			yield $body . "\r\n";
		}
		yield "0\r\n\r\n";
	}
}
