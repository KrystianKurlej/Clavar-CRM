<?php
declare(strict_types=1);

// Bootstrap
$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php', // project root
    __DIR__ . '/vendor/autoload.php',    // sometimes vendor uploaded into public
];
foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) { require $autoload; break; }
}
$config = require __DIR__ . '/../bootstrap/config.php';
require __DIR__ . '/../app/Support.php';
require __DIR__ . '/../app/Auth.php';
require __DIR__ . '/../app/DB.php';
// lightweight psr-4-less includes for our simple OOP structure
@require __DIR__ . '/../app/Repositories/ProjectRepository.php';
@require __DIR__ . '/../app/Controllers/Ajax/ProjectsController.php';

date_default_timezone_set($config['timezone']);
ensure_dir($config['data_dir']);
ensure_dir($config['users_dir']);
// Log PHP errors to a writable place
$logDir = rtrim($config['data_dir'] ?? (__DIR__ . '/../data'), '/') . '/logs';
if (!ensure_dir($logDir)) {
    $logDir = sys_get_temp_dir() . '/calvar-crm/logs';
    ensure_dir($logDir);
}
@ini_set('log_errors', '1');
@ini_set('display_errors', '0');
@ini_set('error_log', rtrim($logDir, '/') . '/php-error.log');

$auth = new Auth($config);
$auth->startSession();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// CORS (for API)
handle_cors($config);

// API routes (JSON)
if (str_starts_with($path, '/api/')) {
    if ($path === '/api/health' && $method === 'GET') {
        $latteOk = class_exists('Latte\\Engine');
        $sqliteOk = extension_loaded('pdo_sqlite');
        $vendorPaths = [__DIR__ . '/../vendor/autoload.php', __DIR__ . '/vendor/autoload.php'];
        $vendorFound = null; foreach ($vendorPaths as $vp) { if (is_file($vp)) { $vendorFound = $vp; break; } }
        // write test in data_dir
        $writeTest = false; $writeErr = null;
        $testFile = rtrim($config['data_dir'] ?? (__DIR__ . '/../data'), '/') . '/.writetest';
        try { if (ensure_dir(dirname($testFile)) && @file_put_contents($testFile, (string)time()) !== false) { $writeTest = true; @unlink($testFile); } }
        catch (Throwable $e) { $writeErr = $e->getMessage(); }
        $resp = [
            'ok' => true,
            'php' => PHP_VERSION,
            'latte' => $latteOk,
            'pdo_sqlite' => $sqliteOk,
            'vendor_autoload' => $vendorFound,
            'data_dir' => [ 'path' => $config['data_dir'], 'exists' => is_dir($config['data_dir']), 'writable' => is_writable($config['data_dir']), 'write_test' => $writeTest, 'write_err' => $writeErr ],
            'users_dir' => [ 'path' => $config['users_dir'], 'exists' => is_dir($config['users_dir']), 'writable' => is_writable($config['users_dir']) ],
        ];
        json($resp);
    }
    if ($path === '/api/csrf' && $method === 'GET') {
        json(['csrf' => $auth->csrfToken()]);
    }

    if ($path === '/api/login' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $_POST['_csrf'] = $input['_csrf'] ?? '';
        $auth->checkCsrf();
        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');
        if ($auth->login($email, $password)) {
            json(['ok' => true]);
        }
        json(['ok' => false, 'error' => 'Unauthorized'], 401);
    }

    if ($path === '/api/logout' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $_POST['_csrf'] = $input['_csrf'] ?? '';
        $auth->checkCsrf();
        $auth->logout();
        json(['ok' => true]);
    }

    if ($path === '/api/me' && $method === 'GET') {
        if (!$auth->isLoggedIn()) {
            json(['ok' => false], 401);
        }
        json(['ok' => true, 'user' => $auth->user()]);
    }

    if ($path === '/api/seed-user' && $method === 'POST') {
        $seed = $config['seed'] ?? ['enabled' => false];
        if (!($seed['enabled'] ?? false)) {
            json(['ok' => false, 'error' => 'Disabled'], 403);
        }
        $lockFile = ($config['data_dir'] ?? __DIR__ . '/../data') . '/.seed.lock';
        if (($seed['once'] ?? true) && is_file($lockFile)) {
            json(['ok' => false, 'error' => 'Locked'], 403);
        }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $secret = (string)($input['secret'] ?? '');
        if (!$secret || !hash_equals((string)($seed['secret'] ?? ''), $secret)) {
            json(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');
        if (!$email || !$password) {
            json(['ok' => false, 'error' => 'Missing email or password'], 400);
        }
        $usersDir = $config['users_dir'];
        ensure_dir($usersDir);
        $dataDir = $config['data_dir'];
        ensure_dir($dataDir);
        $key = strtolower(trim($email));
        $key = preg_replace('/[^a-z0-9\-_.@]+/i', '_', $key);
        $userFile = $usersDir . '/' . $key . '.json';
        if (file_exists($userFile)) {
            json(['ok' => false, 'error' => 'User exists'], 409);
        }
        $dbPath = $dataDir . '/' . $key . '.sqlite';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $user = [
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'db_path' => $dbPath,
            'created_at' => date('c'),
        ];
        file_put_contents($userFile, json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($seed['once'] ?? true) {
            file_put_contents($lockFile, (string)time());
        }
        json(['ok' => true]);
    }

    // Projects API (auth required)
    if (str_starts_with($path, '/api/projects')) {
        if (!$auth->isLoggedIn()) { json(['ok' => false, 'error' => 'Unauthorized'], 401); }
    $user = $auth->user();
    $pdo = DB::connect($user['db_path']);
    DB::ensureSchema($pdo);
    $projRepo = class_exists('ProjectRepository') ? new ProjectRepository() : null;

        // GET /api/projects -> list non-archived
        if ($path === '/api/projects' && $method === 'GET') {
            $rows = $projRepo ? $projRepo->listActive($pdo) : (function($pdo){ $stmt = $pdo->query('SELECT id, name, archived, created_at FROM projects WHERE archived = 0 ORDER BY created_at DESC'); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; })($pdo);
            json(['ok' => true, 'projects' => $rows]);
        }

        // POST /api/projects -> create { name }
        if ($path === '/api/projects' && $method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $_POST['_csrf'] = $input['_csrf'] ?? '';
            $auth->checkCsrf();
            $name = trim((string)($input['name'] ?? ''));
            if ($name === '') { json(['ok' => false, 'error' => 'Name required'], 422); }
            if ($projRepo) { $id = $projRepo->create($pdo, $name); }
            else { $stmt = $pdo->prepare('INSERT INTO projects(name) VALUES(:name)'); $stmt->execute([':name' => $name]); $id = (int)$pdo->lastInsertId(); }
            json(['ok' => true, 'id' => $id]);
        }

        // PUT /api/projects/{id} -> update name or archived
        if (preg_match('#^/api/projects/(\d+)$#', $path, $m) && $method === 'PUT') {
            $id = (int)$m[1];
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $_POST['_csrf'] = $input['_csrf'] ?? '';
            $auth->checkCsrf();
            $fields = [];
            $params = [':id' => $id];
            if (array_key_exists('name', $input)) { $fields[] = 'name = :name'; $params[':name'] = trim((string)$input['name']); }
            if (array_key_exists('archived', $input)) { $fields[] = 'archived = :archived'; $params[':archived'] = (int)!!$input['archived']; }
            if (!$fields) { json(['ok' => false, 'error' => 'No changes'], 400); }
            $sql = 'UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            json(['ok' => true]);
        }

        // DELETE /api/projects/{id}
        if (preg_match('#^/api/projects/(\d+)$#', $path, $m) && $method === 'DELETE') {
            $id = (int)$m[1];
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $_POST['_csrf'] = $input['_csrf'] ?? '';
            $auth->checkCsrf();
            if ($projRepo) { $projRepo->delete($pdo, $id); }
            else { $stmt = $pdo->prepare('DELETE FROM projects WHERE id = :id'); $stmt->execute([':id' => $id]); }
            json(['ok' => true]);
        }
    }
}

// Server-side routes (pages)
use Latte\Engine;
if (!class_exists('Latte\\Engine')) {
    http_response_code(500);
    echo 'Brak zależności aplikacji (vendor). Zainstaluj dependencies: composer install, a następnie odśwież stronę.';
    exit;
}
$latte = new Engine();
// Choose writable cache dir for Latte (prefer system temp on shared hosting)
$tmp = sys_get_temp_dir() . '/calvar-crm/_latte';
if (!ensure_dir($tmp)) {
    $tmp = rtrim($config['data_dir'] ?? (__DIR__ . '/../data'), '/') . '/_latte';
    ensure_dir($tmp);
}
$latteCache = $tmp;
$latte->setTempDirectory($latteCache);
$views = __DIR__ . '/../views';

function render(Latte\Engine $latte, string $template, array $params = []): void {
    global $views, $config, $auth;
    $params['config'] = $config;
    $params['me'] = $auth->user();
    $params['presenterPath'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    // CSRF helper for forms rendered server-side
    if (!function_exists('csrfToken')) {
        function csrfToken(): string { global $auth; return $auth->csrfToken(); }
    }
    $latte->render($views . '/' . $template . '.latte', $params);
}

// Home -> redirect to /projects or /login
if ($path === '/' || $path === '/index.html') {
    if ($auth->isLoggedIn()) { redirect('/projects'); }
    redirect('/login');
}

// Login page
if ($path === '/login') {
    if ($auth->isLoggedIn()) { redirect('/projects'); }
    render($latte, 'login');
    exit;
}

// Projects page (auth required)
if ($path === '/projects') {
    if (!$auth->isLoggedIn()) { redirect('/login'); }
    $user = $auth->user();
    $pdo = DB::connect($user['db_path']);
    DB::ensureSchema($pdo);
    $projRepo = class_exists('ProjectRepository') ? new ProjectRepository() : null;
    $projects = $projRepo ? $projRepo->listActive($pdo) : (function($pdo){ $stmt = $pdo->query('SELECT id, name, archived, created_at FROM projects WHERE archived = 0 ORDER BY created_at DESC'); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; })($pdo);
    render($latte, 'projects', ['projects' => $projects]);
    exit;
}

// AJAX form actions (non-API) routed to OOP controller
if ($path === '/ax_projects' && $method === 'POST') {
    if (class_exists('ProjectsController')) {
        $controller = new ProjectsController($auth, $config);
        $controller->handle();
    } else {
        // Fallback: preserve existing behavior if controller not available
        if (!$auth->isLoggedIn()) { json(['status' => 'error', 'message' => 'Unauthorized']); }
        $auth->checkCsrf();
        json(['status' => 'error', 'message' => 'Controller missing'], 500);
    }
}

http_response_code(404);
echo 'Not Found';
