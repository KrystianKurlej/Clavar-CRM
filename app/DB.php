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
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        // Add base_seconds column if missing (for manual time override)
        try {
            $hasCol = false;
            $stmt = $pdo->query('PRAGMA table_info(projects)');
            $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($cols as $c) { if (($c['name'] ?? '') === 'base_seconds') { $hasCol = true; break; } }
            if (!$hasCol) {
                $pdo->exec('ALTER TABLE projects ADD COLUMN base_seconds INTEGER NOT NULL DEFAULT 0');
            }
        } catch (Throwable $e) {
            // ignore if already exists / older SQLite without alter capabilities
        }
        // Index to speed up non-archived queries
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_projects_archived_created ON projects(archived, created_at DESC)');

        // Time entries per project
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS project_time_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                started_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                stopped_at TEXT NULL,
                FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pte_project ON project_time_entries(project_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pte_active ON project_time_entries(project_id, stopped_at)');
    }
}
