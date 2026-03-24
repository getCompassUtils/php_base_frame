<?php

namespace BaseFrame\Jwt;

/**
 * Класс для работы с JWT
 */
class JwtToken
{

	/**
	 * Сгенерировать jwt токен
	 */
	public static function generate(string $secret, array $payload, ?array $header = []): string
	{

		if ($header === []) {
			$header = [
				"alg" => "HS256",
				"typ" => "JWT",
			];
		}

		$encoded_header  = static::_base64EncodeUrl(toJson($header));
		$encoded_payload = static::_base64EncodeUrl(toJson($payload));

		$signature = hash_hmac("sha256", $encoded_header . "." . $encoded_payload, $secret, true);

		return $encoded_header . "." . $encoded_payload . "." . static::_base64EncodeUrl($signature);
	}

	/**
	 * Провести валидацию jwt токена
	 */
	public static function validate(string $token, string $secret): bool
	{

		[$encoded_header, $encoded_payload, $signature] = explode(".", $token);

		return $signature == static::_base64EncodeUrl(hash_hmac("sha256", $encoded_header . "." . $encoded_payload, $secret, true));
	}

	/**
	 * Получить тело токена
	 */
	public static function getPayloadFromToken(string $token): array
	{

		$parts = explode(".", $token);

		if (!isset($parts[1])) {
			return [];
		}

		return fromJson(self::_base64DecodeUrl($parts[1]));
	}

	/**
	 * Провести энкод в base64 в url кодировке
	 * 
	 * @return string|string[]
	 */
	protected static function _base64EncodeUrl(string $string): string | array
	{

		return str_replace(["+", "/", "="], ["-", "_", ""], base64_encode($string));
	}

	/**
	 * Провести декод base64 из url кодировки
	 */
	protected static function _base64DecodeUrl(string $string): string
	{

		return base64_decode(str_replace(["-", "_"], ["+", "/"], $string));
	}
}
