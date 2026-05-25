<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/smtp.php';

function send_verification_email(string $to_email, string $display_name, string $token): void
{
    $app_url = rtrim((string) env('APP_URL', 'http://localhost'), '/');
    $link    = $app_url . '/verify-email.php?token=' . urlencode($token);
    $name    = $display_name !== '' ? $display_name : $to_email;

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
             . "If you did not register for CFLAG DMR, you can safely ignore this email.\r\n";

    try {
        smtp_send(
            host:         (string) env('MAIL_HOST',         'localhost'),
            port:         (int)    env('MAIL_PORT',         587),
            username:     (string) env('MAIL_USERNAME',     ''),
            password:     (string) env('MAIL_PASSWORD',     ''),
            from_address: (string) env('MAIL_FROM_ADDRESS', 'noreply@localhost'),
            from_name:    (string) env('MAIL_FROM_NAME',    'CFLAG DMR'),
            to_address:   $to_email,
            subject:      $subject,
            body:         $body
        );
    } catch (\RuntimeException $e) {
        error_log('CFLAG DMR mailer: ' . $e->getMessage());
        // Do not re-throw — account is created, user can request resend
    }
}
