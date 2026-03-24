<?php

namespace BaseFrame\Controller;

use BaseFrame\ApiGateway\ScopePermission;
use BaseFrame\Exception\Request\ControllerMethodNotFoundException;
use BaseFrame\Exception\Request\EndpointAccessDeniedException;

/**
 * хендлер мидлвейров
 */
abstract class Api extends Base
{
	// кастомные поля апи
	public string $session_uniq = "";

	public int $role = 0;

	public int $permissions = 0;

	// поля для доступа по api ключу
	public const API_SCOPE         = ScopePermission::SCOPE_UNDEFINED;
	public const READ_METHOD_LIST  = [];
	public const WRITE_METHOD_LIST = [];

	/* @var Action $action */
	public Action $action;

	/**
	 * Выполняем метод из контроллера
	 *
	 * @throws ControllerMethodNotFoundException
	 */
	public function work(string $method_name, int $method_version, array $post_data, int $user_id, array $extra): array
	{

		// присваиваем post-данные
		$this->_post_data = $post_data;

		// устанавливаем версию метода
		$this->method_version = $method_version;

		// назначаем переменную пользователя (инициализируем пользователя уже повторно)
		$this->user_id = $user_id;

		$this->extra["gateway_data"] = $extra["gateway_data"] ?? null;

		// если в extra передали данные с гейтвея, проверяем, что метод можно вызвать
		if (!is_null($this->extra["gateway_data"]) && !$this->_isAllowedForApiKey($user_id, $method_name)) {
			throw new EndpointAccessDeniedException("access denied for api key");
		}

		// назначаем переменную $session_uniq
		// если session_uniq нет - значит вход производится по API ключу
		$this->session_uniq = $extra["user"]["session_uniq"] ?? "";

		$this->action = new $extra["action"]($this->user_id);

		$this->extra["space"]["is_restricted_access"] = $extra["space"]["is_restricted_access"] ?? false;

		if (isset($extra["user"]["role"])) {

			// назначаем переменную $role
			$this->role = $extra["user"]["role"];

			// назначаем переменную $permissions
			$this->permissions = $extra["user"]["permissions"];
		}

		// выбрасываем ошибку, если метод не доступен
		if (!$this->_isHasMethod($method_name)) {
			throw new ControllerMethodNotFoundException("METHOD in controller is not available.");
		}
		$response = $this->$method_name();

		// добавляем actions к основному response
		if (isset($this->action)) {

			$actions = $this->action->getActions();
			if (count($actions) > 0) {
				$response["actions"] = array_merge($response["actions"] ?? [], $actions);
			}
		}

		return $response;
	}

	/**
	 * Разрешен ли метод для апи ключа
	 */
	private function _isAllowedForApiKey(int $user_id, string $method_name): bool
	{

		/** @var \BaseFrame\ApiGateway\GatewayData $gateway_data */
		$gateway_data = $this->extra["gateway_data"];

		$need_permission = ScopePermission::PERMISSION_NONE;

		// проверяем, что метод имеет права на чтение
		if (in_array($method_name, array_map("strtolower", static::READ_METHOD_LIST))) {
			$need_permission = ScopePermission::PERMISSION_READ;
		}

		// если не нашли права на чтение, ищем на запись
		if ($need_permission === ScopePermission::PERMISSION_NONE
		&& in_array($method_name, array_map("strtolower", static::WRITE_METHOD_LIST))) {
			$need_permission = ScopePermission::PERMISSION_WRITE;
		}

		return ScopePermission::isAllowed($gateway_data, $user_id, static::API_SCOPE, $need_permission);
	}
}
