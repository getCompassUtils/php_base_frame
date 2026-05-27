<?php

declare(strict_types=1);

namespace BaseFrame\Siem;

/**
 * Интерфейс драйвера SIEM
 */
interface DriverInterface
{
    /**
     * Отправить сообщение для SIEM
     */
	public function send(string $source_id, string $event_type, array $event_data): void;
}
