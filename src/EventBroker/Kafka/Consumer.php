<?php

declare(strict_types=1);

namespace BaseFrame\EventBroker\Kafka;

use BaseFrame\Exception\Domain\ReturnFatalException;
use BaseFrame\Exception\Gateway\KafkaClientFatalException;
use BaseFrame\Module\ModuleProvider;
use BaseFrame\Socket\SocketProvider;

/**
 * Консамер kafka
 */
class Consumer
{
	// консамер
	private \RdKafka\Conf $_consumer_conf;

	private \RdKafka\KafkaConsumer $_consumer;

	/** @var array<string, callable> */
	private array $_registered_consumer_topics = [];

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

		// настройка консамера
		$consumer_conf = [
			"group.id"          => ModuleProvider::current(),
			"auto.offset.reset" => "earliest",
		];

		$consumer_conf = array_merge($common_conf, $consumer_conf);

		$this->_consumer_conf = new \RdKafka\Conf();

		foreach ($consumer_conf as $key => $value) {
			$this->_consumer_conf->set($key, $value);
		}

		// настроить обработчики ошибок
		$this->setExceptionHandlers();

		$this->_consumer = new \RdKafka\KafkaConsumer($this->_consumer_conf);
	}

	/**
	 * Настроить обработчики ошибок
	 */
	public function setExceptionHandlers(): void
	{

		// обработчик ошибок клиента или соединения
		$this->_consumer_conf->setErrorCb(
			
			function (\RdKafka\KafkaConsumer $_, int $err, string $reason) {
				throw new KafkaClientFatalException("Kafka consumer error: ERROR: $err, REASON: $reason");
			}
		);

	}

	/**
	 * Инициализировать консамер
	 */
	public static function init(string $broker_host, ?string $username = null, ?string $password = null, bool $need_ssl = false): self
	{

		return new self($broker_host, $username, $password, $need_ssl);

	}

	/**
	 * Потреблять сообщения kafka пока не случится таймаут
	 */
	public function consume(string $topic_name, callable $callback, int $timeout_ms): void
	{

		// если не были подписаны на топик, подписываемся
		if (!isset($this->_registered_consumer_topics[$topic_name])) {
			$this->_subscribe($topic_name, $callback);
		}

		// потребляем сообщения до таймаута
		while (true) {

			$broker_message = $this->_consumer->consume($timeout_ms);
			switch ($broker_message->err) {

				// если не ошибка, обрабатываем payload
				case RD_KAFKA_RESP_ERR_NO_ERROR:

					$message = Message::fromBroker((array) $broker_message);
					$this->_registered_consumer_topics[$topic_name]($message);
					break;

					// если случился таймаут, завершаем выполнение
				case RD_KAFKA_RESP_ERR__TIMED_OUT:
					return;

					// если мы не знаем, что это такое, выбрасываем исключение
				default:
					throw new ReturnFatalException("unknown error in kafka consumer");
			}
		}

	}

	/**
	 * Попытаться завершить открытые инстансы kafka
	 * Используется, когда срабатывает shutdown handler
	 * В рамках функции закрываем consumer и досылаем сообщения от продюсера, если такие остались
	 */
	public function end(): void
	{

		$this->_consumer->close();
	}

	/**
	 * Подписаться на новый топик
	 */
	private function _subscribe(string $topic_name, callable $callback): void
	{

		$this->_registered_consumer_topics[$topic_name] = $callback;
		$this->_consumer->subscribe(array_keys($this->_registered_consumer_topics));
	}

	/**
	 * Отписаться от топика
	 */
	private function _unsubscribe(string $topic_name): void
	{

		if (!isset($this->_registered_consumer_topics[$topic_name])) {
			return;
		}

		unset($this->_registered_consumer_topics[$topic_name]);
		$this->_consumer->subscribe(array_keys($this->_registered_consumer_topics));
	}
}
