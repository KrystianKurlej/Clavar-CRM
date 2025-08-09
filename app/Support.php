<?php

function ensure_dir(string $path): void {
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

function view(string $template, array $data = []): void {
    extract($data);
    include __DIR__ . '/../views/' . $template . '.php';
}

function h(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post(string $key, $default = null) {
    return $_POST[$key] ?? $default;
}

function get(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}

function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function is_get(): bool { return $_SERVER['REQUEST_METHOD'] === 'GET'; }

function json(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function handle_cors(array $config): void {
    $cors = $config['cors'] ?? ['enabled' => false];
    if (!($cors['enabled'] ?? false)) return;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = $cors['allowed_origins'] ?? [];
    if ($origin && (in_array('*', $allowed, true) || in_array($origin, $allowed, true))) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: ' . implode(', ', $cors['allowed_headers'] ?? []));
        header('Access-Control-Allow-Methods: ' . implode(', ', $cors['allowed_methods'] ?? []));
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
