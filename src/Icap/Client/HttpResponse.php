<?php

namespace BaseFrame\Icap\Client;

/**
 * Объект http ответа
 */
class HttpResponse {

	private int    $status; // статус ответа
	private string $reason; // причина ответа
	private array  $headers = []; // хедеры ответа
	private mixed  $body_stream; // тело ответа

	/**
	 * Конструктор
	 *
	 * @param int    $status
	 * @param string $reason
	 * @param array  $headers
	 * @param        $body_stream
	 */
	public function __construct(int $status, string $reason, array $headers = [], $body_stream = null) {

		$this->status      = $status;
		$this->reason      = $reason;
		$this->headers     = $headers;
		$this->body_stream = $body_stream;
	}

	/**
	 * Размер хедера
	 *
	 * @return int
	 */
	public function headersLength():int {

		$lines = "HTTP/1.1 {$this->status} {$this->reason}\r\n";
		foreach ($this->headers as $name => $value) {
			$lines .= "$name: $value\r\n";
		}
		return strlen($lines . "\r\n");
	}

	/**
	 * Есть ли тело у ответа
	 *
	 * @return bool
	 */
	public function hasBody():bool {

		return $this->body_stream !== null;
	}

	/**
	 * Разбить ответ по чанкам
	 *
	 * @return iterable
	 */
	public function toChunks():iterable {

		if (isset($this->headers["Content-Length"])) {
			return $this->withFullBody();
		}

		return $this->withChunkedBody();
	}

	/**
	 * Генератор тела ответа, разбитого по чанкам
	 *
	 * @return iterable
	 */
	public function withChunkedBody():iterable {

		yield "HTTP/1.1 {$this->status} {$this->reason}\r\n";
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
	 * Генератор тела ответа
	 *
	 * @return iterable
	 */
	public function withFullBody():iterable {

		yield "HTTP/1.1 {$this->status} {$this->reason}\r\n";
		foreach ($this->headers as $name => $value) {
			yield "$name: $value\r\n";
		}
		yield "\r\n";

		if ($this->body_stream) {
			yield stream_get_contents($this->body_stream);;
		}
	}
}
