<?php

declare(strict_types=1);

namespace BaseFrame\Siem\Driver;

use BaseFrame\EventBroker\Kafka\Message;
use BaseFrame\EventBroker\Kafka\Producer;
use BaseFrame\Siem\DriverInterface;
use ShardingGateway;

/**
 * Драйвер для kafka
 */
class KafkaDriver implements DriverInterface
{
	private const _SOURCE_TYPE       = "app"; // тип источника лога
	private const _BROKER_TOPIC_NAME = "raw.events"; // топик, в котором публикуем сообщение

	private Producer $_kafka_producer; // класс гейтвея, через который происходит взаимодействие с kafka

	/**
	 * Конструктор
	 */
	public function __construct(Producer $kafka_producer)
	{

		$this->_kafka_producer = $kafka_producer;
	}

	/**
	 * Отправить сообщение для SIEM
	 */
	public function send(string $source_id, string $event_type, array $event_data): void
	{

		$this->_kafka_producer->sendMessage($this->_prepare($source_id, $event_type, $event_data));
	}

	/**
	 * Подготовить сообщение к отправке
	 */
	protected function _prepare(string $source_id, string $event_type, array $event_data): Message
	{

		$payload = [
			"event_id"        => generateUUID(),
			"event_type"      => $event_type,
			"event_timestamp" => time(),
			"source_type"     => self::_SOURCE_TYPE,
			"source_id"       => $source_id,
			"event_data"      => $event_data,
		];

		return Message::make(self::_SOURCE_TYPE, $payload, self::_BROKER_TOPIC_NAME);
	}
}
