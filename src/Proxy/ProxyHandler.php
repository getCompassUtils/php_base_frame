<?php

namespace BaseFrame\Proxy;

use BaseFrame\Exception\Domain\ParseFatalException;
use BaseFrame\Exception\Domain\ReturnFatalException;
use Curl;

/**
 * Класс для работы с прокси
 */
class ProxyHandler
{
	private static ProxyHandler | null $_instance = null;

	private string $_protocol;

	private string $_host;

	private int $_port;

	private ?string $_username;

	private ?string $_password;

	// поддерживаемые протоколы прокси
	private const _ALLOWED_PROXY_PROTOCOLS = [
		Curl::PROXY_PROTOCOL_HTTP,
		Curl::PROXY_PROTOCOL_HTTPS,
		Curl::PROXY_PROTOCOL_SOCKS5,
		Curl::PROXY_PROTOCOL_SOCKS5H
	];

	/**
	 * Proxy constructor.
	 *
	 * @throws ReturnFatalException
	 */
	private function __construct(string $proxy_protocol, string $proxy_host, int $proxy_port, ?string $proxy_username, ?string $proxy_password)
	{

		if ($proxy_protocol !== "" && !in_array($proxy_protocol, self::_ALLOWED_PROXY_PROTOCOLS)) {
			throw new ParseFatalException("unknown proxy protocol passed");
		}

		$this->_protocol = $proxy_protocol;
		$this->_host     = $proxy_host;
		$this->_port     = $proxy_port;
		$this->_username = $proxy_username;
		$this->_password = $proxy_password;
	}

	/**
	 * инициализируем синглтон
	 */
	public static function init(string $proxy_protocol, string $proxy_host, int $proxy_port, string $proxy_username, string $proxy_password): static
	{

		if (!is_null(static::$_instance)) {
			return static::$_instance;
		}

		if ($proxy_username == "") {
			$proxy_username = null;
			$proxy_password = null;
		}

		return static::$_instance = new static($proxy_protocol, $proxy_host, $proxy_port, $proxy_username, $proxy_password);
	}

	/**
	 * Возвращает экземпляр класса.
	 */
	public static function instance(): static
	{

		if (is_null(static::$_instance)) {
			throw new ReturnFatalException("need to initialized before using");
		}

		return static::$_instance;
	}

	/**
	 * получаем protocol
	 */
	public function protocol(): string
	{

		return $this->_protocol;
	}

	/**
	 * получаем host
	 */
	public function host(): string
	{

		return $this->_host;
	}

	/**
	 * получаем port
	 */
	public function port(): string
	{

		return $this->_port;
	}

	/**
	 * получаем username
	 */
	public function username(): ?string
	{

		return $this->_username;
	}

	/**
	 * получаем password
	 */
	public function password(): ?string
	{

		return $this->_password;
	}
}
