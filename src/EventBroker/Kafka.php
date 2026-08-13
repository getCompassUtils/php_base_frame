<?php

declare(strict_types=1);

namespace BaseFrame\EventBroker;

use BaseFrame\EventBroker\Kafka\Consumer;
use BaseFrame\EventBroker\Kafka\Producer;
use BaseFrame\Exception\Domain\ParseFatalException;

class Kafka
{
	/** @var array<string, self> */
	private static array $_instances = [];

	private string $_broker_host;

	private ?string $_username;

	private ?string $_password;

	private bool $_need_ssl;

	private ?Consumer $_consumer = null;

	private ?Producer $_producer = null;

	/**
	 * Конструктор
	 */
	private function __construct(string $broker_host, ?string $username = null, ?string $password = null, bool $need_ssl = false)
	{
		$this->_broker_host = $broker_host;
		$this->_username    = $username;
		$this->_password    = $password;
		$this->_need_ssl    = $need_ssl;
	}

	/**
	 * Инициализировать драйвер из конфигурации
	 */
	public static function fromConf(array $conf, string $key = "default"): self
	{

		if (isset(self::$_instances[$key])) {
			return self::$_instances[$key];
		}

		if (!isset($conf[$key])) {
			throw new ParseFatalException("cant find config with passed key");
		}

		$conf = $conf[$key];

		self::$_instances[$key] = new self("{$conf["host"]}:{$conf["port"]}", $conf["user"] ?? null, $conf["pass"] ?? null, $conf["need_ssl"] ?? false);
		return self::$_instances[$key];
	}

	/**
	 * Вернуть консамер
	 */
	public function consumer(): Consumer
	{

		if (!is_null($this->_consumer)) {
			return $this->_consumer;
		}

		return $this->_consumer = Consumer::init($this->_broker_host, $this->_username, $this->_password, $this->_need_ssl);
	}

	/**
	 * Вернуть продюсер
	 */
	public function producer(): Producer
	{

		if (!is_null($this->_producer)) {
			return $this->_producer;
		}

		return $this->_producer = Producer::init($this->_broker_host, $this->_username, $this->_password, $this->_need_ssl);
	}

	/**
	 * Завершаем все инстансы
	 */
	public static function end(): void
	{

		foreach (self::$_instances as $instance) {

			!is_null($instance->_consumer) && @$instance->_consumer->end();
			!is_null($instance->_producer) && @$instance->_producer->end();
		}
	}
}
