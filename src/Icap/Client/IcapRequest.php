<?php

namespace BaseFrame\Icap\Client;

/**
 * Класс ICAP реквеста
 */
class IcapRequest {

	private string $method; // метод запроса (REQMOD, RESPMOD, OPTIONS)

	private string        $host; // хост icap сервера
	private int           $port; // порт icap сервера
	private string        $service; // сервис icap сервера
	private array         $headers       = []; // хедеры
	private ?HttpRequest  $http_request  = null; // отправляемый http запрос
	private ?HttpResponse $http_response = null; // отправляемый http ответ
	private array         $encapsulated  = []; // данные Encapsulated хедера
	private ?int          $preview_size  = null; // размер предпросмотра в байтах

	/**
	 * Конструктор
	 *
	 * @param string $method
	 * @param string $host
	 * @param string $port
	 * @param string $service
	 */
	private function __construct(string $method, string $host, string $port, string $service) {

		$this->method  = $method;
		$this->host    = $host;
		$this->port    = $port;
		$this->service = $service;
	}

	/**
	 * Установить размер Preview
	 *
	 * @param int $size
	 *
	 * @return self
	 */
	public function setPreviewSize(int $size):self {

		$this->preview_size = $size;
		return $this;
	}

	public function getPreviewSize():?int {

		return $this->preview_size;
	}

	/**
	 * Сформировать OPTIONS запрос
	 *
	 * @param string $host
	 * @param string $port
	 * @param string $service
	 *
	 * @return self
	 */
	public static function options(string $host, string $port, string $service):self {

		$req                          = new self("OPTIONS", $host, $port, $service);
		$req->headers["Encapsulated"] = "null-body=0";
		return $req;
	}

	/**
	 * Сформировать REQMOD запрос
	 *
	 * @param string      $host
	 * @param string      $port
	 * @param string      $service
	 * @param HttpRequest $http_request
	 *
	 * @return self
	 */
	public static function reqmod(string $host, string $port, string $service, HttpRequest $http_request):self {

		$req                          = new self("REQMOD", $host, $port, $service);
		$req->http_request            = $http_request;
		$offset                       = 0;
		$req->encapsulated["req-hdr"] = $offset;
		$offset                       += $http_request->headersLength();

		if ($http_request->hasBody()) {
			$req->encapsulated["req-body"] = $offset;
		} else {
			$req->encapsulated["null-body"] = $offset;
		}

		$req->headers["Preview"]      = null;
		$req->headers["Encapsulated"] = $req->buildEncapsulatedHeader();
		$req->headers["Allow"]        = 204;
		$req->headers["Host"]         = $host;
		return $req;
	}

	/**
	 * Сформировать RESPMOD запрос
	 *
	 * @param string       $host
	 * @param string       $port
	 * @param string       $service
	 * @param HttpRequest  $http_request
	 * @param HttpResponse $http_response
	 *
	 * @return self
	 */
	public static function respmod(string $host, string $port, string $service, HttpRequest $http_request, HttpResponse $http_response):self {

		$req                          = new self("RESPMOD", $host, $port, $service);
		$req->http_request            = $http_request;
		$req->http_response           = $http_response;
		$offset                       = 0;
		$req->encapsulated["req-hdr"] = $offset;
		$offset                       += $http_request->headersLength();
		$req->encapsulated["res-hdr"] = $offset;
		$offset                       += $http_response->headersLength();

		if ($http_response->hasBody()) {
			$req->encapsulated["res-body"] = $offset;
		} else {
			$req->encapsulated["null-body"] = $offset;
		}

		$req->headers["Preview"]      = null;
		$req->headers["Encapsulated"] = $req->buildEncapsulatedHeader();
		$req->headers["Allow"]        = 204;
		$req->headers["Host"]         = $host;
		return $req;
	}

	/**
	 * Сформировать хедер Encapsulated
	 *
	 * @return string
	 */
	private function buildEncapsulatedHeader():string {

		$parts = [];
		foreach ($this->encapsulated as $key => $offset) {
			$parts[] = "$key=$offset";
		}
		return implode(", ", $parts);
	}

	/**
	 * Получить разобранный хедер Encapsulated
	 *
	 * @return array
	 */
	public function getEncapsulatedMap():array {

		return $this->encapsulated;
	}

	public function getHeaders():array {

		return $this->headers;
	}

	/**
	 * Разбить запрос на чанки
	 *
	 * @return iterable
	 */
	public function toChunks():iterable {

		yield strtoupper($this->method) . " icap://{$this->host}:{$this->port}{$this->service} ICAP/1.0\r\n";
		foreach ($this->headers as $name => $value) {
			yield "$name: $value\r\n";
		}
		yield "\r\n";

		if ($this->http_request) {
			yield from $this->http_request->toChunks();
		}

		if ($this->http_response) {
			yield from $this->http_response->toChunks();
		}
	}

	/**
	 * Отдаем превью в чанках
	 * @return iterable
	 */
	public function previewToChunks():iterable {

		$icap_line          = sprintf("%s icap://%s:%d%s ICAP/1.0\r\n", $this->method, $this->host, $this->port, $this->service);
		$headers            = $this->headers;
		$headers["Preview"] = $this->preview_size;

		$header_lines = [];
		foreach ($headers as $name => $value) {
			$header_lines[] = $name . ": " . $value;
		}

		yield $icap_line . implode("\r\n", $header_lines) . "\r\n\r\n";

		$header_chunks = $this->http_request->headerToChunks();
		$body_chunks   = $this->http_request->bodyToChunks($this->preview_size);

		foreach ($header_chunks as $chunk) {
			yield $chunk;
		}

		if ($this->preview_size === 0) {

			yield "0\r\n\r\n";
			return;
		}

		foreach ($body_chunks as $index => $chunk) {

			if ($index == 0) {
				yield $chunk;
			}

			if ($index == 1) {

				if (!str_starts_with($chunk, "0\r\n\r\n")) {
					yield "0\r\n\r\n";
				}

				yield "0; ieof\r\n\r\n";
			}

			if ($index > 1) {
				break;
			}
		}
	}

	/**
	 * Отдаем оставшуюся часть в чанках
	 *
	 * @return iterable
	 */
	public function afterPreviewToChunks():iterable {

		$body_chunks = $this->http_request->bodyToChunks($this->preview_size);

		foreach ($body_chunks as $index => $chunk) {

			if ($index <= 1 && $this->preview_size > 0) {
				continue;
			}

			yield $chunk;
		}

		yield "0\r\n\r\n";
	}
}
