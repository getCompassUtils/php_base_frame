<?php

namespace BaseFrame\Http\Header;

/**
 * Заголовок источника данных авторизации
 */
class XAuthSrc extends Header
{
	protected const _HEADER_KEY          = "X_AUTH_SRC"; // ключ хедера
	public const string AUTH_SRC_CLIENT  = "client"; // клиент предоставил данные
	public const string AUTH_SRC_GATEWAY = "gateway"; // данные предоставил гейтвей

	// известные источники данных
	public const _KNOWN_AUTH_SRC_LIST = [
		self::AUTH_SRC_CLIENT,
		self::AUTH_SRC_GATEWAY
	];
}
