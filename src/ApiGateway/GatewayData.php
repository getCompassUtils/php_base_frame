<?php

namespace BaseFrame\ApiGateway;

use BaseFrame\Jwt\JwtToken;

/**
 * Класс объекта данных гейтвея
 */
readonly class GatewayData
{
	private function __construct(
		public int $user_id,
		public array $scope_permissions,
		public int $issued_at,
	) {
	}

	/**
	 * Создать объект из jwt токена
	 */
	public static function fromJwt(string $jwt_token): ?self
	{

		// валидируем токен
		$is_valid = JwtToken::validate($jwt_token, GatewayProvider::gatewaySecretKey());

		if (!$is_valid) {
			return null;
		}

		// получаем тело jwt токена
		$payload = JwtToken::getPayloadFromToken($jwt_token);

		// проверяем, что токен выпустил gateway
		if (!isset($payload["iss"]) || $payload["iss"] !== GatewayProvider::jwtTokenIssuer()) {
			return null;
		}

		return new self(
			$payload["uid"],
			$payload["perms"],
			$payload["iat"]
		);
	}
}
