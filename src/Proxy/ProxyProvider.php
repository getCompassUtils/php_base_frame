<?php

namespace BaseFrame\Proxy;

/**
 * Класс-обертка для работы с прокси
 */
class ProxyProvider
{
	/**
	 * Закрываем конструктор.
	 */
	protected function __construct()
	{

	}

	/**
	 * получаем protocol
	 */
	public static function protocol(): string
	{

		return ProxyHandler::instance()->protocol();
	}

	/**
	 * получаем host
	 */
	public static function host(): string
	{

		return ProxyHandler::instance()->host();
	}

	/**
	 * получаем port
	 */
	public static function port(): int
	{

		return ProxyHandler::instance()->port();
	}

	/**
	 * получаем username
	 */
	public static function username(): ?string
	{

		return ProxyHandler::instance()->username();
	}

	/**
	 * получаем password
	 */
	public static function password(): ?string
	{

		return ProxyHandler::instance()->password();
	}
}
