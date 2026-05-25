<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

function send_verification_email(string $to_email, string $display_name, string $token): void
{
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $link  = 'http://' . $host . '/verify-email.php?token=' . urlencode($token);
    $name  = $display_name !== '' ? $display_name : $to_email;

    if ((bool) env('EMAIL_DEV_MODE', false)) {
        $line = '[' . date('Y-m-d H:i:s') . '] TO: ' . $to_email
              . ' | LINK: ' . $link . PHP_EOL;
        file_put_contents('/var/log/cflag-dmr-email-dev.log', $line, FILE_APPEND | LOCK_EX);
        return;
    }

    $subject = 'Verify your CFLAG DMR email address';
    $body    = "Hi {$name},\r\n\r\n"
             . "Please verify your email address by visiting the link below:\r\n\r\n"
             . "{$link}\r\n\r\n"
             . "This link expires in 24 hours.\r\n\r\n"
             . "If you did not register for CFLAG DMR, you can ignore this email.\r\n";
    $headers = 'From: noreply@' . $host;

    mail($to_email, $subject, $body, $headers);
}
