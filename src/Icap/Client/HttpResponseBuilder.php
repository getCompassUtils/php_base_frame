<?php

namespace BaseFrame\Icap\Client;

/**
 * Билдер http ответа
 */
class HttpResponseBuilder {

	private int    $status      = 200; // статус ответа
	private string $reason      = "OK"; // причина ответа
	private array  $headers     = []; // хедеры ответа
	private mixed  $body_stream = null; // тело ответа

	/**
	 * Установить статус
	 *
	 * @param int $code
	 *
	 * @return $this
	 */
	public function status(int $code):self {

		$this->status = $code;
		return $this;
	}

	/**
	 * Установить причину
	 *
	 * @param string $reason
	 *
	 * @return $this
	 */
	public function reason(string $reason):self {

		$this->reason = $reason;
		return $this;
	}

	/**
	 * Добавить хедер
	 *
	 * @param string $name
	 * @param string $value
	 *
	 * @return $this
	 */
	public function addHeader(string $name, string $value):self {

		$this->headers[$name] = $value;
		return $this;
	}

	/**
	 * Добавить тело
	 *
	 * @param $stream
	 *
	 * @return $this
	 */
	public function bodyFromStream($stream):self {

		$this->body_stream = $stream;
		return $this;
	}

	/**
	 * Сформировать объект ответа
	 *
	 * @return HttpResponse
	 */
	public function build():HttpResponse {

		return new HttpResponse($this->status, $this->reason, $this->headers, $this->body_stream);
	}
}
