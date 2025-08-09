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

    public static function ensureSchema(PDO $pdo): void
    {
        // Minimal schema for projects
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                archived INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime("now"))
            )'
        );
        // Index to speed up non-archived queries
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_projects_archived_created ON projects(archived, created_at DESC)');
    }
}
