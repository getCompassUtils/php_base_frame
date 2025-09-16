<?php

namespace BaseFrame\Icap\Client;

use BaseFrame\Exception\Domain\ParseFatalException;
use BaseFrame\Exception\Domain\ReturnFatalException;

/**
 * Класс ICAP клиента
 */
class IcapClient {

	protected string      $icap_server_host; // хост
	protected int         $icap_server_port; // порт
	protected string      $icap_service; // сервис

	protected ?int $preview_size; // размер превью
	protected IcapRequest $last_request; // последний icap запрос на сервер

	/**
	 * Конструктор
	 *
	 * @param string $icap_uri
	 *
	 * @throws ParseFatalException
	 */
	public function __construct(string $icap_uri, ?int $preview_size = null) {

		$parsed = parse_url($icap_uri);
		if ($parsed["scheme"] !== "icap") {
			throw new ParseFatalException("Invalid ICAP URI scheme");
		}
		$this->icap_server_host = $parsed["host"];
		$this->icap_server_port = $parsed["port"] ?? 1344;
		$this->icap_service     = $parsed["path"] ?? "/";
		$this->preview_size     = $preview_size;
	}

	/**
	 * Получить последний запрос на icap сервер
	 *
	 * @return IcapRequest
	 */
	public function getLastRequest():IcapRequest {

		return $this->last_request;
	}

	/**
	 * OPTIONS запрос
	 *
	 * @return IcapResponse
	 * @throws ReturnFatalException
	 */
	public function options():IcapResponse {

		$icap_request = IcapRequest::options($this->icap_server_host, $this->icap_server_port, $this->icap_service);
		return $this->send($icap_request);
	}

	/**
	 * REQMOD запрос
	 *
	 * @param HttpRequest $httpRequest
	 *
	 * @return IcapResponse
	 * @throws ReturnFatalException
	 */
	public function reqmod(HttpRequest $httpRequest):IcapResponse {

		$icap_request = IcapRequest::reqmod($this->icap_server_host, $this->icap_server_port, $this->icap_service, $httpRequest);
		$this->preview_size !== null && $icap_request->setPreviewSize($this->preview_size);

		$this->last_request = $icap_request;
		return $this->send($icap_request);
	}

	/**
	 * RESPMOD запрос
	 *
	 * @param HttpRequest  $httpRequest
	 * @param HttpResponse $httpResponse
	 *
	 * @return IcapResponse
	 * @throws ReturnFatalException
	 */
	public function respmod(HttpRequest $httpRequest, HttpResponse $httpResponse):IcapResponse {

		$icap_request       = IcapRequest::respmod($this->icap_server_host, $this->icap_server_port, $this->icap_service, $httpRequest, $httpResponse);
		$this->last_request = $icap_request;
		return $this->send($icap_request);
	}

	/**
	 * Отправить запрос
	 *
	 * @param IcapRequest $request
	 *
	 * @return IcapResponse
	 * @throws ReturnFatalException
	 */
	protected function send(IcapRequest $request):IcapResponse {

		$fp = stream_socket_client($this->icap_server_host . ":" . $this->icap_server_port, $errno, $errstr, 2);
		if (!$fp) {
			throw new ReturnFatalException("Connection failed: $errstr ($errno)");
		}

		$isPreview = $request->getPreviewSize() !== null;

		if ($isPreview) {

			foreach ($request->previewToChunks() as $chunk) {
				fwrite($fp, $chunk);
			}

			fflush($fp);
			$response = IcapResponse::fromStream($fp);

			if ($response->getStatusCode() !== 100) {

				fclose($fp);
				return $response;
			}
		}

		$chunks = $isPreview ? $request->afterPreviewToChunks() : $request->toChunks();

		foreach ($chunks as $chunk) {
			fwrite($fp, $chunk);
		}

		fflush($fp);
		$response = IcapResponse::fromStream($fp);
		fclose($fp);
		return $response;
	}
}