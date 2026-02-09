<?php

namespace BaseFrame\Router\Middleware;

use BaseFrame\ApiGateway\GatewayData;
use BaseFrame\Exception\Request\EndpointAccessDeniedException;
use BaseFrame\Router\Request;

/**
 * Мидлвар авторизации через api gateway
 */
class GatewayAuthorization implements Main
{
	/**
	 * Получаем информацию об авторизованном пользователе из гейтвея
	 */
	public static function handle(Request $request): Request
	{

		$auth_header = \BaseFrame\Http\Header\Authorization::parse();

		// заголовка авторизации в запросе нет
		if ($auth_header === false
		|| $auth_header->isNone()
		|| !$auth_header->isCorrect()
		|| $auth_header->getType() !== \BaseFrame\Http\Header\Authorization::AUTH_TYPE_BEARER
		) {
			throw new EndpointAccessDeniedException("invalid gateway data");
		}

		$gateway_data = GatewayData::fromJwt($auth_header->getToken());

		if (is_null($gateway_data)) {
			throw new EndpointAccessDeniedException("invalid gateway data");
		}

		$request->user_id               = $gateway_data->user_id;
		$request->extra["gateway_data"] = $gateway_data;
		return $request;
	}
}
