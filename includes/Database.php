<?php
declare(strict_types=1);

final class Database
{
    private static ?mysqli $connection = null;

    public static function connection(): mysqli
    {
        if (self::$connection === null) {
            self::$connection = getDbConnection();
        }
        return self::$connection;
    }
}
