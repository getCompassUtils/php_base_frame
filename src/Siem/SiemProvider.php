<?php

declare(strict_types=1);

namespace BaseFrame\Siem;

/**
 * Класс для работы с siem
 */
class SiemProvider
{
	/**
	 * Получить драйвер
	 */
	public static function driver(string $instance_key): DriverInterface
	{

		return SiemHandler::instance($instance_key)->driver();
	}
}
