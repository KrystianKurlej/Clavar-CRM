<?php
declare(strict_types=1);

// Bootstrap
$config = require __DIR__ . '/../bootstrap/config.php';
require __DIR__ . '/../app/Support.php';
require __DIR__ . '/../app/Auth.php';
require __DIR__ . '/../app/DB.php';

date_default_timezone_set($config['timezone']);
ensure_dir($config['data_dir']);
ensure_dir($config['users_dir']);

$auth = new Auth($config);
$auth->startSession();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// CORS (for API)
handle_cors($config);

// Routes
if ($path === '/' && $auth->isLoggedIn()) {
    view('dashboard', ['auth' => $auth]);
    return;
}

if ($path === '/' && !$auth->isLoggedIn()) {
    redirect('/login');
}

if ($path === '/login') {
    if ($method === 'GET') {
        view('login', ['auth' => $auth, 'error' => null]);
        return;
    }
    if ($method === 'POST') {
        $auth->checkCsrf();
        $email = trim((string)post('email'));
        $password = (string)post('password');
        if ($auth->login($email, $password)) {
            redirect('/');
        } else {
            http_response_code(401);
            view('login', ['auth' => $auth, 'error' => 'Nieprawidłowy e-mail lub hasło.']);
        }
        return;
    }
}

if ($path === '/logout') {
    if ($method === 'POST') {
        $auth->checkCsrf();
        $auth->logout();
        redirect('/login');
    } else {
        http_response_code(405);
        echo 'Method Not Allowed';
    }
    return;
}

// API routes (JSON)
if (str_starts_with($path, '/api/')) {
    if ($path === '/api/csrf' && $method === 'GET') {
        json(['csrf' => $auth->csrfToken()]);
    }
    if ($path === '/api/login' && $method === 'POST') {
        // JSON body
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
}

// 404
http_response_code(404);
echo 'Not Found';
