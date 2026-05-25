<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth/session.php';

start_session();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

session_destroy();

redirect('/login.php');
