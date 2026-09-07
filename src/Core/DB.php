<?php
// src/Core/DB.php — lazy PDO singleton. Backend: add query helpers in Features, not here.
final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = require dirname(__DIR__, 2) . '/config/config.php';
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['db']['host'],
                $c['db']['port'],
                $c['db']['name'],
                $c['db']['charset']
            );
            self::$pdo = new PDO($dsn, $c['db']['user'], $c['db']['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }
}