<?php

namespace BaseFrame\Icap;

/**
 * Класс для работы с icap конфигом
 */
abstract class IcapConfig
{
	// ключ для Mcache для мока
	protected const _MCACHE_MOCK_KEY = "icap_mock_config";

	// типы контролируемых сущностей
	protected const _ENTITY_TYPE_TEXT = "text";
	protected const _ENTITY_TYPE_FILE = "file";

	/** @var string $_host хост сервера */
	protected readonly string $_host;

	/** @var int $_port порт сервера */
	protected readonly int $_port;

	/** @var string $_service путь до сервиса */
	protected readonly string $_service;

	/** @var bool $_is_enabled включен ли icap клиент */
	protected readonly bool $_is_enabled;

	/** @var bool $_is_enabled включен ли мок */
	protected readonly bool $_is_mock;

	/** @var array $_control_entity_list контролируемые сущности */
	protected readonly array $_control_entity_list;

	/** @var array $_file_extension_list расширения файлов */
	protected readonly array $_file_extension_list;

	/**
	 * Конструктор
	 */
	public function __construct(array $config)
	{

		$this->_is_mock    = isset($config["is_mock"]) ? (bool) $config["is_mock"] : false;
		$this->_is_enabled = isset($config["is_enabled"]) ? (bool) $config["is_enabled"] : false;

		$this->_host    = isset($config["host"]) ? $config["host"] : "";
		$this->_port    = isset($config["port"]) ? $config["port"] : 0;
		$this->_service = isset($config["service"]) ? $config["service"] : "";

		$this->_control_entity_list = isset($config["control_entity_list"]) ? $config["control_entity_list"] : [];
		$this->_file_extension_list = isset($config["file_extension_list"]) ? $config["file_extension_list"] : [];

		return $this;
	}

	/**
	 * Включен ли ICAP
	 */
	public function isEnabled(): bool
	{

		return $this->_is_enabled;
	}

	/**
	 * Включен ли мок
	 */
	public function isMock(): bool
	{

		return $this->_is_mock;
	}

	/**
	 * Хост
	 */
	public function host(): string
	{

		return $this->_host;
	}

	/**
	 * Порт
	 */
	public function port(): int
	{

		return $this->_port;
	}

	/**
	 * Сервис
	 */
	public function service(): string
	{

		return $this->_service;
	}

	/**
	 * Контролируется ли текст
	 */
	public function isTextControlled(): bool
	{

		if (in_array(self::_ENTITY_TYPE_TEXT, $this->_control_entity_list)) {
			return true;
		}

		return false;
	}

	/**
	 * Контролируются ли файлы
	 */
	public function isFileControlled(): bool
	{

		if (in_array(self::_ENTITY_TYPE_FILE, $this->_control_entity_list)) {
			return true;
		}

		return false;
	}

	/**
	 * Контролируется ли расширение файла
	 */
	public function isFileExtensionControlled(string $file_extension): bool
	{

		if ($this->_file_extension_list === [] || in_array($file_extension, $this->_file_extension_list)) {
			return true;
		}

		return false;
	}

	/**
	 * Получить ключ mcache для мока
	 */
	protected static function _getMcacheMockKey(string $module): string
	{

		return self::_MCACHE_MOCK_KEY . "_$module";
	}
}
