<?php

class DB
{
    /** @var array<string, PDO> */
    private static array $pool = [];

    public static function connect(string $sqlitePath): PDO
    {
        if (isset(self::$pool[$sqlitePath])) {
            return self::$pool[$sqlitePath];
        }
        $dsn = 'sqlite:' . $sqlitePath;
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');
        self::$pool[$sqlitePath] = $pdo;
        return $pdo;
    }
}
