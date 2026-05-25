<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/env.php';

function start_session(): void
{
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => (bool) env('SESSION_SECURE_COOKIE', false),
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function is_admin(): bool
{
    return isset($_SESSION['admin_id']) && is_int($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('/login.php');
    }
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
