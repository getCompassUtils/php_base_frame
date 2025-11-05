<?php

namespace BaseFrame\Exception\Request;

use BaseFrame\Exception\RequestException;


/**
 * исключение уровня handler для того чтобы вернуть на клиент команду ВМЕСТО ответа
 */
class AnswerCommandException extends RequestException {

    const HTTP_CODE = 200;

    protected string $_command_name;
    protected array  $_command_extra;

    public function __construct(string $command_name, array $command_extra, string $message = "") {

        $this->_command_name  = $command_name;
        $this->_command_extra = $command_extra;
        parent::__construct($message);
    }

    public function getCommandName():string {

        return $this->_command_name;
    }

    public function getCommandExtra():array {

        return $this->_command_extra;
    }
}