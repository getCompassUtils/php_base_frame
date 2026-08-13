<?php

declare(strict_types=1);

namespace BaseFrame\EventBroker\Kafka;

/**
 * Класс сообщения для Kafka
 */
class Message
{
	private string $_key;

	private array $_payload;

	private int $_timestamp;

	private string $_topic;

	/**
	 * Конструктор
	 */
	private function __construct(string $key, array $payload, string $topic, int $timestamp = -1)
	{
		$this->_key       = $key;
		$this->_payload   = $payload;
		$this->_topic     = $topic;
		$this->_timestamp = $timestamp;
	}

	/**
	 * Создать сообщение
	 */
	public static function make(string $key, array $payload, string $topic): static
	{

		return new static($key, $payload, $topic);

	}

	/**
	 * Получить объект из сообщения брокера
	 */
	public static function fromBroker(array $broker_message): static
	{

		return new static(
			$broker_message["key"],
			fromJson($broker_message["payload"]),
			$broker_message["topic_name"],
			$broker_message["timestamp"]
		);
	}

	/**
	 * Получить ключ сообщения
	 */
	public function getKey(): string
	{
		return $this->_key;
	}

	/**
	 * Получить полезную нагрузку сообщения
	 */
	public function getPayload(): array
	{
		return $this->_payload;
	}

	/**
	 * Получить топик, которому предназначено сообщение
	 */
	public function getTopic(): string
	{
		return $this->_topic;
	}

	/**
	 * Получить метку времени сообщения
	 */
	public function getTimestamp(): int
	{
		return $this->_timestamp;
	}
}
