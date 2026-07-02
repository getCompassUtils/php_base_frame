<?php

declare(strict_types=1);

namespace BaseFrame\EventBroker\Kafka;

use BaseFrame\Exception\Gateway\KafkaClientFatalException;
use BaseFrame\Exception\Gateway\KafkaDeliveryFatalException;
use BaseFrame\Socket\SocketProvider;

/**
 * Продюсер kafka
 */
class Producer
{
	// продюсер
	private \RdKafka\Conf $_producer_conf;

	private \RdKafka\Producer $_producer;

	/** @var array<string, \RdKafka\ProducerTopic> */
	private array $_registered_producer_topics = [];

	/**
	 * Конструктор
	 */
	private function __construct(string $broker_host, ?string $username = null, ?string $password = null, bool $need_ssl = false)
	{

		$common_conf = [
			"metadata.broker.list" => $broker_host,
		];

		// если включен ssl, добавляем корневой сертификат
		if ($need_ssl) {
			$common_conf["ssl.ca.pem"] = SocketProvider::caCertificate();
		}

		// если указан пользователь и пароль, устанавливаем
		if (!is_null($username)) {

			$common_conf["security.protocol"]                    = $need_ssl ? "sasl_ssl" : "sasl_plaintext";
			$common_conf["sasl.mechanism"]                       = "SCRAM-SHA-256";
			$common_conf["sasl.username"]                        = $username;
			!is_null($password) && $common_conf["sasl.password"] = $password;
		}

		// настройка продюсера
		$producer_conf = [];
		$producer_conf = array_merge($common_conf, $producer_conf);

		$this->_producer_conf = new \RdKafka\Conf();

		foreach ($producer_conf as $key => $value) {
			$this->_producer_conf->set($key, $value);
		}

		// настроить обработчики ошибок
		$this->setExceptionHandlers();

		$this->_producer = new \RdKafka\Producer($this->_producer_conf);
	}

	/**
	 * Настроить обработчики ошибок
	 */
	public function setExceptionHandlers(): void
	{

		// обработчик ошибок клиента или соединения
		$this->_producer_conf->setErrorCb(
			
			function (\RdKafka\Producer $_, int $err, string $reason) {
				throw new KafkaClientFatalException("Kafka producer error: ERROR: $err, REASON: $reason");
			}
		);

		// обработчик отчетов о доставке сообщения
		$this->_producer_conf->setDrMsgCb(function (\RdKafka\Producer $_, \RdKafka\Message $message) {

			if ($message->err !== RD_KAFKA_RESP_ERR_NO_ERROR) {
				throw new KafkaDeliveryFatalException("Kafka producer delivery error: " . $message->errstr());
			}
		});
	}

	/**
	 * Иинициализировать продюсер
	 */
	public static function init(string $broker_host, ?string $username = null, ?string $password = null, bool $need_ssl = false): self
	{

		return new self($broker_host, $username, $password, $need_ssl);
	}

	/**
	 * Отправить сообщение
	 */
	public function sendMessage(Message $message): void
	{

		$topic = $this->_getProducerTopic($message->getTopic());
		$topic->produce(RD_KAFKA_PARTITION_UA, 0, toJson($message->getPayload()), $message->getKey());
		$this->_producer->poll(100);
	}

	/**
	 * Попытаться завершить открытые инстансы kafka
	 * Используется, когда срабатывает shutdown handler
	 * В рамках функции закрываем consumer и досылаем сообщения от продюсера, если такие остались
	 */
	public function end(): void
	{

		// если очередь продюсера пуста — сообщений не отправляли
		if ($this->_producer->getOutQLen() === 0) {
			return;
		}

		// подавляем ошибки, чтобы не вылетело приложение
		try {
			$this->_producer->flush(1_000);
		} catch (\Throwable $e) {
			// на этапе завершения ошибки досылки игнорируем
		}
	}

	/**
	 * Получить топик для продюсера
	 */
	private function _getProducerTopic(string $topic_name): \RdKafka\ProducerTopic
	{

		// если топик уже зарегистрирован, ничего не делаем
		if (isset($this->_registered_producer_topics[$topic_name])) {
			return $this->_registered_producer_topics[$topic_name];
		}

		return $this->_registered_producer_topics[$topic_name] = $this->_producer->newTopic($topic_name);
	}
}
