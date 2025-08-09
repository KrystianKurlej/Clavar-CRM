<?php

class Auth
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function startSession(): void
    {
        // Session cookie params
        session_name($this->config['session_name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $this->config['session_secure'],
            'httponly' => true,
            'samesite' => $this->config['session_samesite'],
        ]);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    public function checkCsrf(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
            http_response_code(400);
            echo 'Bad CSRF token';
            exit;
        }
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user']);
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function login(string $email, string $password): bool
    {
        $userFile = $this->userFile($email);
        if (!is_file($userFile)) {
            return false;
        }
        $json = json_decode(file_get_contents($userFile), true);
        if (!$json || !isset($json['password_hash'])) {
            return false;
        }
        if (!password_verify($password, $json['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'email' => $json['email'],
            'db_path' => $json['db_path'],
            'created_at' => $json['created_at'] ?? null,
        ];
        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            redirect('/login');
        }
    }

    private function userFile(string $email): string
    {
        $usersDir = $this->config['users_dir'];
        ensure_dir($usersDir);
        // Normalize email into filename-safe key
        $key = strtolower(trim($email));
        $key = preg_replace('/[^a-z0-9\-_.@]+/i', '_', $key);
        return $usersDir . '/' . $key . '.json';
    }
}
