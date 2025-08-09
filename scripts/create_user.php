<?php
declare(strict_types=1);

// CLI: php scripts/create_user.php email@example.com "SuperHaslo123"

$root = dirname(__DIR__);
$config = require $root . '/bootstrap/config.php';
require $root . '/app/Support.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI\n");
    exit(1);
}

[$script, $email, $password] = array_pad($argv, 3, null);
if (!$email || !$password) {
    fwrite(STDERR, "Usage: php scripts/create_user.php <email> <password>\n");
    exit(1);
}

ensure_dir($config['users_dir']);
ensure_dir($config['data_dir']);

// Normalize email to file key
$key = strtolower(trim($email));
$key = preg_replace('/[^a-z0-9\-_.@]+/i', '_', $key);
$userFile = $config['users_dir'] . '/' . $key . '.json';

if (file_exists($userFile)) {
    fwrite(STDERR, "User already exists: $email\n");
    exit(2);
}

// Per-user sqlite path
$dbPath = $config['data_dir'] . '/' . $key . '.sqlite';

// Create empty sqlite file and bootstrap minimal schema for future tables
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA journal_mode = WAL;');
$pdo->exec('PRAGMA foreign_keys = ON;');
// MVP: no tables now; later will add user-specific tables (projects, time_entries)

$user = [
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'db_path' => $dbPath,
    'created_at' => date('c'),
];

file_put_contents($userFile, json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

fwrite(STDOUT, "User created: $email\nDB: $dbPath\n");
