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
@require __DIR__ . '/../app/Controllers/Api/ProjectsApiController.php';
@require __DIR__ . '/../app/Controllers/Pages/ProjectsPageController.php';

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

// Central API auth gate (whitelist unauthenticated endpoints)
$apiWhitelist = ['/api/health', '/api/csrf', '/api/login', '/api/seed-user'];
if (str_starts_with($path, '/api/') && $method !== 'OPTIONS' && !in_array($path, $apiWhitelist, true)) {
    if (!$auth->isLoggedIn()) {
        json(['ok' => false, 'error' => 'Unauthorized'], 401);
    }
}

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
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        $email = '';
        $password = '';
        if (is_array($input)) {
            // JSON body
            $_POST['_csrf'] = $input['_csrf'] ?? ($_POST['_csrf'] ?? '');
            $email = trim((string)($input['email'] ?? ''));
            $password = (string)($input['password'] ?? '');
        } else {
            // Form body
            $_POST['_csrf'] = $_POST['_csrf'] ?? '';
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
        }
        $auth->checkCsrf();
        if ($auth->login($email, $password)) {
            if (is_array($input)) {
                // JSON client
                json(['ok' => true]);
            } else {
                // Form submit → redirect to projects
                header('Location: /projects', true, 303);
                exit;
            }
        }
        if (is_array($input)) {
            json(['ok' => false, 'error' => 'Unauthorized'], 401);
        } else {
            header('Location: /login?error=1', true, 303);
            exit;
        }
    }

    if ($path === '/api/logout' && $method === 'POST') {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (is_array($input)) {
            $_POST['_csrf'] = $input['_csrf'] ?? ($_POST['_csrf'] ?? '');
        } else {
            $_POST['_csrf'] = $_POST['_csrf'] ?? '';
        }
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
        $api = class_exists('ProjectsApiController') ? new ProjectsApiController($auth) : null;
        if (!$api) { json(['ok' => false, 'error' => 'Controller missing'], 500); }
        if ($path === '/api/projects' && $method === 'GET') { $api->list(); }
        if ($path === '/api/projects' && $method === 'POST') { $api->create(); }
        if (preg_match('#^/api/projects/(\d+)$#', $path, $m) && $method === 'PUT') { $api->update((int)$m[1]); }
        if (preg_match('#^/api/projects/(\d+)$#', $path, $m) && $method === 'DELETE') { $api->delete((int)$m[1]); }
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
    $page = class_exists('ProjectsPageController') ? new ProjectsPageController($auth, $latte, $views) : null;
    if (!$page) { http_response_code(500); echo 'Controller missing'; exit; }
    $page->show();
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
