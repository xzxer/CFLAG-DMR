<?php
declare(strict_types=1);

/**
 * Minimal PHP SMTP client — no dependencies.
 *
 * Supports:
 *   port 465  → implicit TLS (SMTPS)
 *   port 587  → STARTTLS (always attempted)
 *   port 25   → plain or opportunistic STARTTLS if advertised
 *
 * AUTH LOGIN is used when MAIL_USERNAME is non-empty.
 * Throws \RuntimeException on any protocol or connection failure.
 */
function smtp_send(
    string $host,
    int    $port,
    string $username,
    string $password,
    string $from_address,
    string $from_name,
    string $to_address,
    string $subject,
    string $body
): void {
    $use_ssl = ($port === 465);

    $context = stream_context_create([
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $errno  = 0;
    $errstr = '';
    $conn   = stream_socket_client(
        ($use_ssl ? 'ssl' : 'tcp') . "://{$host}:{$port}",
        $errno, $errstr, 10,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if ($conn === false) {
        throw new \RuntimeException("SMTP: cannot connect to {$host}:{$port} — {$errstr} ({$errno})");
    }

    stream_set_timeout($conn, 15);

    $read = static function () use ($conn): string {
        $buf = '';
        while ($line = fgets($conn, 1024)) {
            $buf .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $buf;
    };

    $cmd = static function (string $command) use ($conn, $read): string {
        fwrite($conn, $command . "\r\n");
        return $read();
    };

    $expect = static function (string $response, string $code) use ($host, $port): void {
        if (!str_starts_with($response, $code)) {
            throw new \RuntimeException(
                "SMTP {$host}:{$port} — expected {$code}, got: " . trim($response)
            );
        }
    };

    $local = gethostname() ?: 'localhost';

    $expect($read(), '220');

    $ehlo = $cmd("EHLO {$local}");
    $expect($ehlo, '250');

    // STARTTLS: mandatory on port 587, opportunistic on port 25 if advertised
    $needs_tls = ($port === 587) || (!$use_ssl && str_contains($ehlo, 'STARTTLS'));
    if ($needs_tls) {
        $expect($cmd('STARTTLS'), '220');
        if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new \RuntimeException("SMTP {$host}:{$port} — STARTTLS negotiation failed");
        }
        $ehlo = $cmd("EHLO {$local}");
        $expect($ehlo, '250');
    }

    // AUTH LOGIN (skipped when no credentials configured)
    if ($username !== '') {
        $expect($cmd('AUTH LOGIN'), '334');
        $expect($cmd(base64_encode($username)), '334');
        $expect($cmd(base64_encode($password)), '235');
    }

    $expect($cmd("MAIL FROM:<{$from_address}>"), '250');
    $expect($cmd("RCPT TO:<{$to_address}>"), '250');
    $expect($cmd('DATA'), '354');

    $msg_id      = bin2hex(random_bytes(16)) . '@' . $host;
    $subject_enc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $from_enc    = '=?UTF-8?B?' . base64_encode($from_name) . '?= <' . $from_address . '>';

    $headers  = 'Date: '       . date('r')       . "\r\n";
    $headers .= 'From: '       . $from_enc        . "\r\n";
    $headers .= 'To: <'        . $to_address . '>' . "\r\n";
    $headers .= 'Subject: '    . $subject_enc      . "\r\n";
    $headers .= 'Message-ID: <' . $msg_id . '>'   . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";

    $encoded = chunk_split(base64_encode($body));

    fwrite($conn, $headers . "\r\n" . $encoded . "\r\n.\r\n");
    $expect($read(), '250');

    $cmd('QUIT');
    fclose($conn);
}
