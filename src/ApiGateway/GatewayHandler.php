<?php

namespace BaseFrame\ApiGateway;

use BaseFrame\Exception\Domain\ReturnFatalException;

/**
 * Класс для работы с путями
 */
class GatewayHandler
{
	// допускаемый издатель jwt токена
	private const _JWT_TOKEN_ISSUER = "api_gw";

	private static GatewayHandler | null $_instance = null;

	private string $_jwt_token_issuer;

	private string $_gateway_secret_key;

	/**
	 * Socket constructor.
	 *
	 * @throws ReturnFatalException
	 */
	private function __construct(string $gateway_secret_key_b64, string $jwt_token_issuer)
	{

		$this->_jwt_token_issuer   = $jwt_token_issuer;
		$this->_gateway_secret_key = base64_decode($gateway_secret_key_b64);
	}

	/**
	 * инициализируем синглтон
	 */
	public static function init(string $gateway_secret_key, string $jwt_token_issuer = self::_JWT_TOKEN_ISSUER): static
	{

		if (!is_null(static::$_instance)) {
			return static::$_instance;
		}

		return static::$_instance = new static($gateway_secret_key, $jwt_token_issuer);
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
	 * получить имя доверенного издателя токена
	 */
	public function jwtTokenIssuer(): string
	{

		return $this->_jwt_token_issuer;
	}

	/**
	 * получить публичный ключ гейтвея
	 */
	public function gatewaySecretKey(): string
	{

		return $this->_gateway_secret_key;
	}
}
