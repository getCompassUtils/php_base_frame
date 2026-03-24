<?php

namespace BaseFrame\ApiGateway;

/**
 * Класс, описывающий зоны ответственности
 */
class ScopePermission
{
    // дефолтная зона для контроллеров, где ее не определили
    // доступа к таким зонам нет
    public const int SCOPE_UNDEFINED = -1;
    public const int SCOPE_GLOBAL = 0;
    public const int SCOPE_CONFERENCE = 1;
    public const int SCOPE_SPACE = 2;
    public const int SCOPE_FILE = 3;

    // права доступа к зоне
    public const int PERMISSION_NONE = 0;
    public const int PERMISSION_READ = 1 << 0;
    public const int PERMISSION_WRITE = (1 << 0) | (1 << 1);

    // доступные виды прав
    private const array _ALLOWED_PERMISSION_LIST = [
        self::PERMISSION_NONE,
        self::PERMISSION_READ,
        self::PERMISSION_WRITE
    ];

    /**
     * Разрешен ли вызов
     * @long - множество проверок
     */
    public static function isAllowed(GatewayData $gw_data, int $user_id, int $scope, int $need_permission): bool
    {

        // не можем выдать доступ к неизвестной зоне
        if ($scope === self::SCOPE_UNDEFINED) {
            return false;
        }

        // если токен выдан другому пользователю, то запрещаем
        if ($user_id !== $gw_data->user_id) {
            return false;
        }

        // если права неизвестные, запрещаем доступ
        if (!in_array($need_permission, self::_ALLOWED_PERMISSION_LIST)) {
            return false;
        }

        // для global scope у пользователя есть разрешение
        if ($scope === self::SCOPE_GLOBAL) {
            return true;
        }

        // если для метода отсутствуют права read/write
        if ($need_permission == self::PERMISSION_NONE) {
            return false;
        }

        // если в gateway data нет информации о зоне ответственности, значит прав нет
        if (!isset($gw_data->scope_permissions[$scope])) {
            return false;
        }

        // если права неизвестные, запрещаем доступ
        if (!in_array($gw_data->scope_permissions[$scope], self::_ALLOWED_PERMISSION_LIST)) {
            return false;
        }

        // если права нет, то запрещаем
        if (($gw_data->scope_permissions[$scope] & $need_permission) !== $need_permission) {
            return false;
        }

        return true;
    }
}
