<?php

namespace BaseFrame\Icap\Client;

use BaseFrame\Exception\Domain\ParseFatalException;
use BaseFrame\Exception\Domain\ReturnFatalException;
use ShardingGateway;

/**
 * Класс ICAP мок клиента
 */
class IcapMockClient extends IcapClient
{
	// ключ от мемкеша
	private const _MCACHE_KEY = "icap_mock_client";

	// ICAP ответ с разерешением
	private const _ICAP_APPROVE_RESPONSE  = "ICAP/1.0 204 No Content\r\nServer: ICAP-Server/2.1\r\nDate: Tue, 11 Aug 2025 14:30:22 GMT\r\nConnection: close\r\n\r\n";
	private const _ICAP_RESTIRCT_RESPONSE = "ICAP/1.0 200 OK\r\nServer: ICAP-Server/2.1\r\nDate: Tue, 11 Aug 2025 14:31:15 GMT\r\nEncapsulated: req-hdr=0, req-body=312\r\n\r\nConnection: close\r\n\r\nGET /blocked.html HTTP/1.1\r\nHost: example.org\r\nUser-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept: */*\r\nAccept-Language: en-US,en;q=0.5\r\nAccept-Encoding: gzip, deflate\r\nConnection: keep-alive\r\nX-ICAP-Blocked-Reason: malicious file detected\r\nContent-Type: text/html\r\nContent-Length: 187\r\n\r\n<html>\r\n<head><title>Blocked</title></head>\r\n<body>\r\n<h1>403 Forbidden</h1>\r\n<p>Your request was blocked by content filtering.</p>\r\n</body>\r\n</html>\r\n";

	private static ?IcapMockClient $_instance = null;

	private string $_cache_key;

	/** @var class-string<ShardingGateway> */
	private string $_sharding_gateway_class;

	/**
	 * Конструктор
	 *
	 * @param class-string<ShardingGateway>
	 *
	 * @throws ParseFatalException
	 */
	private function __construct(string $sharding_gateway_class, string $postfix)
	{

		$icap_uri                      = "icap://test.mock:1433";
		$this->_sharding_gateway_class = $sharding_gateway_class;
		$this->_cache_key              = self::_MCACHE_KEY . "_$postfix";

		parent::__construct($icap_uri, null);
	}

	/**
	 * Получить инстанс мок клиента
	 *
	 * @param class-string<ShardingGateway>
	 */
	public static function instance(string $sharding_gateway_class, string $postfix): static
	{

		if (!is_null(static::$_instance)) {
			return static::$_instance;
		}

		return static::$_instance = new static($sharding_gateway_class, $postfix);
	}

	/**
	 * Установить флаг блокировки
	 */
	public function setNeedBlockRequest(bool $need_block_request): self
	{

		// обновляем и в memcache, чтобы данные можно было подтянуть с другого процесса php
		$this->_sharding_gateway_class::cache()->set($this->_cache_key, ["need_block_request" => $need_block_request]);

		return $this;
	}

	/**
	 * Получить флаг блокировки
	 */
	public function getNeedBlockRequest(): bool
	{

		// иначе идем в memcache, получаем значение и устанавливаем в класс
		$mock_config = $this->_sharding_gateway_class::cache()->get($this->_cache_key, []);

		return $mock_config["need_block_request"] ?? false;
	}

	/**
	 * Отправить запрос
	 *
	 *
	 * @throws ReturnFatalException
	 */
	protected function send(IcapRequest $request): IcapResponse
	{
		return $this->getNeedBlockRequest()
		? IcapResponse::fromStream($this->_getResponseStream(self::_ICAP_RESTIRCT_RESPONSE))
		: IcapResponse::fromStream($this->_getResponseStream(self::_ICAP_APPROVE_RESPONSE));

	}

	/**
	 * Получить поток из строки
	 */
	private function _getResponseStream(string $response_string): mixed
	{

		$stream = fopen('php://temp', 'r+');
		fwrite($stream, $response_string);
		rewind($stream);

		return $stream;
	}
}
