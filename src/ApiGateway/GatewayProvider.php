<?php

namespace BaseFrame\ApiGateway;

/**
 * Класс для работы с путями
 */
class GatewayProvider
{
	/**
	 * получить имя доверенного издателя токена
	 */
	public static function jwtTokenIssuer(): string
	{

		return GatewayHandler::instance()->jwtTokenIssuer();
	}

	/**
	 * получить публичный ключ гейтвея
	 */
	public static function gatewaySecretKey(): string
	{

		return GatewayHandler::instance()->gatewaySecretKey();
	}
}
