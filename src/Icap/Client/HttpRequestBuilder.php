<?php

namespace BaseFrame\Icap\Client;

/**
 * Билдер http запроса
 */
class HttpRequestBuilder {

	private string $method      = "GET"; // метод http запроса
	private string $url         = "/"; // url запроса
	private array  $headers     = []; // хедеры запроса
	private mixed  $body_stream = null; // тело запроса

	/**
	 * Установить метод
	 *
	 * @param string $method
	 *
	 * @return $this
	 */
	public function method(string $method):self {

		$this->method = strtoupper($method);
		return $this;
	}

	/**
	 * Установить url
	 *
	 * @param string $url
	 *
	 * @return $this
	 */
	public function url(string $url):self {

		$this->url = $url;
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
	 * Сформировать тело в виде файла
	 *
	 * @param string $filePath
	 *
	 * @return $this
	 */
	public function bodyFromFile(string $filePath):self {

		$this->body_stream                   = fopen($filePath, "rb");
		$this->headers["Content-Type"]      = "application/octet-stream";
		$this->headers["Transfer-Encoding"] = "chunked";
		return $this;
	}

	/**
	 * Сформировать тело в виде формы
	 *
	 * @param array $fields
	 *
	 * @return $this
	 */
	public function bodyFromForm(array $fields):self {

		$encoded          = json_encode($fields, JSON_UNESCAPED_UNICODE);
		$this->body_stream = fopen("php://temp", "r+");
		fwrite($this->body_stream, $encoded);
		rewind($this->body_stream);
		$this->headers["Content-Type"]   = "application/json";
		$this->headers["Content-Length"] = strlen($encoded);
		return $this;
	}

	/**
	 * Сформировать тело в виде multipart формы
	 *
	 * @param string      $fieldName
	 * @param string      $filePath
	 * @param string|null $fileName
	 *
	 * @return $this
	 * @throws \Random\RandomException
	 */
	public function bodyFromMultipartFile(string $fieldName, string $filePath, ?string $fileName = null):self {

		$boundary                           = "----CompassFormBoundary" . bin2hex(random_bytes(16));
		$this->headers["Content-Type"]      = "multipart/form-data; boundary=" . $boundary;
		$this->headers["Transfer-Encoding"] = "chunked";

		$fileName = $fileName ?? basename($filePath);
		$stream   = fopen("php://temp", "r+");

		fwrite($stream, "--{$boundary}\r\n");
		fwrite($stream, "Content-Disposition: form-data; name=\"{$fieldName}\"; filename=\"{$fileName}\"\r\n");
		fwrite($stream, "Content-Type: application/octet-stream\r\n\r\n");

		$fileStream = fopen($filePath, "rb");
		stream_copy_to_stream($fileStream, $stream);
		fclose($fileStream);

		fwrite($stream, "\r\n--{$boundary}--\r\n");
		rewind($stream);

		$this->body_stream = $stream;

		return $this;
	}

	/**
	 * Сформировать объект http запроса
	 *
	 * @return HttpRequest
	 */
	public function build():HttpRequest {

		return new HttpRequest($this->method, $this->url, $this->headers, $this->body_stream);
	}
}
