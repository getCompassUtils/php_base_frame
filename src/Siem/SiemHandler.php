<?php

declare(strict_types=1);

namespace BaseFrame\Siem;

use BaseFrame\Exception\Domain\ParseFatalException;
use BaseFrame\Exception\Domain\ReturnFatalException;
use BaseFrame\Siem\Driver\KafkaDriver;
use BaseFrame\Siem\Driver\NoneDriver;
use ShardingGateway;

/**
 * Класс для работы с siem
 */
class SiemHandler
{
	public const KAFKA_DRIVER = "kafka";
	public const NONE_DRIVER  = "none";

	/** @var self[] $_instance_list */
	private static array $_instance_list = [];

	private ShardingGateway $_sharding_gateway;

	private ?DriverInterface $_driver = null;

	/**
	 * Module constructor.
	 *
	 * @throws ReturnFatalException
	 */
	private function __construct(ShardingGateway $sharding_gateway)
	{

		$this->_sharding_gateway = $sharding_gateway;
	}

	/**
	 * Возвращает экземпляр класса.
	 */
	public static function instance(string $key): static
	{

		if (!isset(static::$_instance_list[$key])) {
			throw new ParseFatalException("need init siem handler before");
		}

		return static::$_instance_list[$key];
	}

	/**
	 * Возвращает экземпляр класса.
	 */
	public static function init(ShardingGateway $sharding_gateway, string $key): static
	{

		return static::$_instance_list[$key] = new static($sharding_gateway);
	}

	/**
	 * Установить драйвер
	 */
	public function setDriver(string $driver_name): self
	{

		$this->_driver = match ($driver_name) {

			self::KAFKA_DRIVER => new KafkaDriver($this->_sharding_gateway->msgBroker()->producer()),
			self::NONE_DRIVER  => new NoneDriver(),
			default            => throw new ParseFatalException("unknown driver"),
		};
		return $this;
	}

	/**
	 * Получить драйвер
	 */
	public function driver(): DriverInterface
	{

		if (is_null($this->_driver)) {
			throw new ParseFatalException("siem handler has no driver");
		}

		return $this->_driver;
	}
}
