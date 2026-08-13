<?php

namespace BaseFrame\Siem\Driver;

use BaseFrame\Siem\DriverInterface;

/**
 * Драйвер-заглушка, когда выключен SIEM
 */
class NoneDriver implements DriverInterface {
    
    /**
     * Отправить сообщение для SIEM
     */
    public function send(string $source_id, string $event_type, array $event_data): void
    {
        return;
    }
}