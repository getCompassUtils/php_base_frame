<?php

namespace BaseFrame\Server;

use BaseFrame\Exception\Domain\ReturnFatalException;

/**
 * Класс-обертка для работы с серверами
 */
class ServerProvider {

	/**
	 * Закрываем конструктор.
	 */
	protected function __construct() {

	}

	/**
	 * проверяем, что это тестовый сервер
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	public static function isDev():bool {

		if (self::_hasTag(ServerHandler::DEV_TAG)) {
			return true;
		}

		return false;
	}

	/**
	 * проверяем, что это CI сервер
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	public static function isCi():bool {

		if (self::_hasTag(ServerHandler::CI_TAG)) {
			return true;
		}

		return false;
	}

	/**
	 * проверяем, что это Stage сервер
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	public static function isStage():bool {

		if (self::_hasTag(ServerHandler::STAGE_TAG)) {
			return true;
		}

		return false;
	}

	/**
	 * проверяем, что это on-premise сервер
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	public static function isOnPremise():bool {

		if (self::_hasTag(ServerHandler::ON_PREMISE_TAG)) {
			return true;
		}

		return false;
	}

	/**
	 * проверяем, что это saas сервер
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	public static function isSaas():bool {

		if (self::_hasTag(ServerHandler::SAAS_TAG)) {
			return true;
		}

		return false;
	}

	/**
	 * проверяем, что это production сервер
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	public static function isProduction():bool {

		if (self::_hasTag(ServerHandler::PRODUCTION_TAG)) {
			return true;
		}

		return false;
	}

	/**
	 * проверяем, что это локальный сервер
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	public static function isLocal():bool {

		if (self::_hasTag(ServerHandler::LOCAL_TAG)) {
			return true;
		}

		return false;
	}

	/**
	 * проверяем, что сервер с интеграцией
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	public static function isIntegration():bool {

		if (self::_hasTag(ServerHandler::INTEGRATION_TAG)) {
			return true;
		}

		return false;
	}

	/**
	 * проверяем, что это on-premise сервер из yandex cloud marketplace
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	public static function isYandexCloudMarketplace():bool {

		if (self::isOnPremise() && self::_hasTag(ServerHandler::YANDEX_CLOUD_MARKETPLACE_TAG)) {
			return true;
		}

		return false;
	}

	/**
	 * проверяем, что это on-premise сервер с локальной лицензией
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	public static function isLocalLicense():bool {

		if (self::isOnPremise() && self::_hasTag(ServerHandler::LOCAL_LICENSE_TAG)) {
			return true;
		}

		return false;
	}

	/**
	 * проверяем, что это master сервер
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	public static function isMaster():bool {

		if (self::_hasTag(ServerHandler::MASTER_TAG)) {
			return true;
		}

		return false;
	}

	/**
	 * проверяем, что это тестовое окружение
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	public static function isTest():bool {

		if (self::isDev() || self::isCi() || self::isMaster() || self::isLocal()) {
			return true;
		}

		return false;
	}

	/**
	 * Проверка запущено ли на тестовом сервере
	 *
	 * @return void
	 * @throws ReturnFatalException
	 * @throws \ParseException
	 */
	public static function assertTest():void {

		// если запущены не на тестовом сервере
		if (!self::isTest()) {
			throw new \ParseException("called is not test server");
		}
	}

	/**
	 * Проверка запущено ли на продакшене
	 *
	 * @return void
	 * @throws ReturnFatalException
	 * @throws \parseException
	 */
	public static function assertProduction():void {

		// если запущены не на продакшене
		if (!self::isProduction()) {
			throw new \ParseException("called is not production server");
		}
	}

	/**
	 * получаем лейбл сервиса сервера
	 *
	 * @return string
	 * @throws \BaseFrame\Exception\Domain\ReturnFatalException
	 */
	public static function serviceLabel():string {

		return ServerHandler::instance()->serviceLabel();
	}

	/**
	 * Является ли сервер резервным
	 *
	 * @return bool
	 * @throws \BaseFrame\Exception\Domain\ReturnFatalException
	 */
	public static function isReserveServer():bool {

		if (!\BaseFrame\Server\ServerProvider::isOnPremise()) {
			return false;
		}

		if (self::isTest() && ((int) getenv("IS_NEED_REPLICATION_TEST")) === 1) {
			return true;
		}

		$service_label = \BaseFrame\Server\ServerProvider::serviceLabel();
		if (mb_strlen($service_label) == 0) {
			return false;
		}

		if (!defined("COMPANIES_RELATIONSHIP_FILE") || mb_strlen(COMPANIES_RELATIONSHIP_FILE) == 0) {
			return false;
		}

		// инициализируем файл конфига компаний резервных серверов
		$companies_relationship_file = \BaseFrame\System\File::init(sprintf("%s", DOMINO_CONFIG_PATH), COMPANIES_RELATIONSHIP_FILE);

		// если конфиг отсутствует
		if (!$companies_relationship_file->isExists()) {
			return false;
		}

		$companies_relationship_config = fromJson($companies_relationship_file->read());

		// получаем флаг master для service_label
		if (!isset($companies_relationship_config[$service_label]["master"])) {
			return true;
		}

		if (false == $companies_relationship_config[$service_label]["master"]) {
			return true;
		}

		return false;
	}

	// ---------------------------------------------------
	// PROTECTED
	// ---------------------------------------------------

	/**
	 * проверяем на совпадение всех тега
	 *
	 * @param string $tag
	 *
	 * @return bool
	 * @throws ReturnFatalException
	 */
	protected static function _hasTag(string $tag):bool {

		$server_tag_list = array_flip(ServerHandler::instance()->tagList());
		if (isset($server_tag_list[$tag])) {
			return true;
		}

		return false;
	}
}
