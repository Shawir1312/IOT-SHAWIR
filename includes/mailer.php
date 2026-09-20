<?php
/**
 * ShawirIOT - Native SMTP Mailer & Email Verification Module
 * Pure PHP Socket SMTP Client with SSL/TLS and HTML Template Engine
 */

require_once __DIR__ . '/functions.php';

/**
 * Ensure email_verifications table exists
 */
function ensureEmailVerificationTable(): void {
    static $ensured = false;
    if ($ensured) return;
    try {
        DB::query("CREATE TABLE IF NOT EXISTS `email_verifications` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `email` VARCHAR(150) NOT NULL,
            `token` VARCHAR(64) NOT NULL UNIQUE,
            `otp_code` VARCHAR(6) NOT NULL,
            `expires_at` TIMESTAMP NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_ev_email` (`email`),
            INDEX `idx_ev_token` (`token`),
            INDEX `idx_ev_otp` (`otp_code`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (\Throwable $e) {
        // Silent table creation failure if permission restricted
    }
    $ensured = true;
}

/**
 * Lightweight Native SMTP Client (Pure PHP Socket)
 */
class ShawirSMTP {
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $crypto; // 'tls', 'ssl', 'none'
    private int $timeout;
    private $socket = null;
    private array $logs = [];

    public function __construct(string $host, int $port, string $user, string $pass, string $crypto = 'tls', int $timeout = 10) {
        $this->host = trim($host);
        $this->port = $port;
        $this->user = trim($user);
        $this->pass = $pass;
        $this->crypto = strtolower(trim($crypto));
        $this->timeout = $timeout;
    }

    public function getLogs(): array {
        return $this->logs;
    }

    private function log(string $msg): void {
        $this->logs[] = $msg;
    }

    private function readResponse(array $expectedCodes): array {
        $fullResponse = '';
        $lastCode = 0;
        $startTime = time();

        while (!feof($this->socket)) {
            if ((time() - $startTime) > $this->timeout) {
                return ['success' => false, 'code' => 0, 'message' => 'Connection read timeout'];
            }
            $line = fgets($this->socket, 515);
            if ($line === false) break;
            $fullResponse .= $line;
            $this->log("S: " . trim($line));

            // SMTP response format: '250 ' or '250-...'
            if (strlen($line) >= 4 && $line[3] === ' ') {
                $lastCode = (int)substr($line, 0, 3);
                break;
            }
        }

        if (empty($fullResponse)) {
            return ['success' => false, 'code' => 0, 'message' => 'Empty response from SMTP server'];
        }

        $code = (int)substr($fullResponse, 0, 3);
        $ok = in_array($code, $expectedCodes, true);
        return [
            'success' => $ok,
            'code'    => $code,
            'message' => trim($fullResponse)
        ];
    }

    private function sendCommand(string $command, array $expectedCodes, bool $hideLog = false): array {
        if ($hideLog) {
            $this->log("C: [HIDDEN]");
        } else {
            $this->log("C: {$command}");
        }
        fwrite($this->socket, $command . "\r\n");
        return $this->readResponse($expectedCodes);
    }

    public function send(string $fromEmail, string $fromName, string $toEmail, string $subject, string $htmlBody, string $textBody = ''): array {
        $errno = 0;
        $errstr = '';

        $connectHost = $this->host;
        if ($this->crypto === 'ssl') {
            $connectHost = 'ssl://' . $this->host;
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ]);

        $this->log("Connecting to {$connectHost}:{$this->port}...");
        $this->socket = @stream_socket_client(
            $connectHost . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            return [
                'success' => false,
                'message' => "Gagal terhubung ke SMTP server ({$this->host}:{$this->port}): {$errstr} (Error #{$errno})",
                'logs'    => $this->logs
            ];
        }

        stream_set_timeout($this->socket, $this->timeout);

        // 1. Read greeting banner (220)
        $resp = $this->readResponse([220]);
        if (!$resp['success']) {
            $this->close();
            return ['success' => false, 'message' => 'Banner server tidak valid: ' . $resp['message'], 'logs' => $this->logs];
        }

        // 2. EHLO
        $heloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $heloHost)) {
            $heloHost = 'localhost';
        }
        $resp = $this->sendCommand("EHLO {$heloHost}", [250]);
        if (!$resp['success']) {
            // fallback HELO
            $resp = $this->sendCommand("HELO {$heloHost}", [250]);
            if (!$resp['success']) {
                $this->close();
                return ['success' => false, 'message' => 'Handshake EHLO/HELO gagal: ' . $resp['message'], 'logs' => $this->logs];
            }
        }

        // 3. STARTTLS if crypto is tls
        if ($this->crypto === 'tls') {
            $resp = $this->sendCommand("STARTTLS", [220]);
            if (!$resp['success']) {
                $this->close();
                return ['success' => false, 'message' => 'STARTTLS ditolak server: ' . $resp['message'], 'logs' => $this->logs];
            }

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            $cryptoOk = @stream_socket_enable_crypto($this->socket, true, $cryptoMethod);
            if (!$cryptoOk) {
                $this->close();
                return ['success' => false, 'message' => 'Gagal mengaktifkan enkripsi TLS pada socket', 'logs' => $this->logs];
            }
            $this->log("TLS handshake sukses.");

            // Resend EHLO after TLS
            $resp = $this->sendCommand("EHLO {$heloHost}", [250]);
            if (!$resp['success']) {
                $this->close();
                return ['success' => false, 'message' => 'EHLO pasca-TLS gagal: ' . $resp['message'], 'logs' => $this->logs];
            }
        }

        // 4. Authentication (AUTH LOGIN)
        if (!empty($this->user)) {
            $resp = $this->sendCommand("AUTH LOGIN", [334]);
            if (!$resp['success']) {
                $this->close();
                return ['success' => false, 'message' => 'Server menolak perintah AUTH LOGIN: ' . $resp['message'], 'logs' => $this->logs];
            }

            // Send username
            $resp = $this->sendCommand(base64_encode($this->user), [334], true);
            if (!$resp['success']) {
                $this->close();
                return ['success' => false, 'message' => 'Username SMTP ditolak: ' . $resp['message'], 'logs' => $this->logs];
            }

            // Send password
            $resp = $this->sendCommand(base64_encode($this->pass), [235], true);
            if (!$resp['success']) {
                $this->close();
                return ['success' => false, 'message' => 'Password/Sandi Aplikasi SMTP ditolak (Autentikasi gagal): ' . $resp['message'], 'logs' => $this->logs];
            }
        }

        // 5. MAIL FROM
        $resp = $this->sendCommand("MAIL FROM: <{$fromEmail}>", [250]);
        if (!$resp['success']) {
            $this->close();
            return ['success' => false, 'message' => 'Pengirim (MAIL FROM) ditolak: ' . $resp['message'], 'logs' => $this->logs];
        }

        // 6. RCPT TO
        $resp = $this->sendCommand("RCPT TO: <{$toEmail}>", [250, 251]);
        if (!$resp['success']) {
            $this->close();
            return ['success' => false, 'message' => 'Penerima (RCPT TO) ditolak: ' . $resp['message'], 'logs' => $this->logs];
        }

        // 7. DATA
        $resp = $this->sendCommand("DATA", [354]);
        if (!$resp['success']) {
            $this->close();
            return ['success' => false, 'message' => 'Perintah DATA ditolak: ' . $resp['message'], 'logs' => $this->logs];
        }

        // 8. Construct MIME Message
        $boundary = '=_shawir_' . md5(uniqid((string)time(), true));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

        $plainContent = !empty($textBody) ? $textBody : strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

        $headers = [];
        $headers[] = "Date: " . date('r');
        $headers[] = "From: {$encodedFromName} <{$fromEmail}>";
        $headers[] = "Reply-To: {$fromEmail}";
        $headers[] = "To: <{$toEmail}>";
        $headers[] = "Subject: {$encodedSubject}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $headers[] = "X-Mailer: ShawirIOT Mailer 1.0";

        $body  = implode("\r\n", $headers) . "\r\n\r\n";
        
        // Plain text section
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($plainContent)) . "\r\n";

        // HTML section
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $body .= "--{$boundary}--\r\n";

        // 9. Send content and end with <CRLF>.<CRLF>
        $this->log("Mengirim isi email (Panjang: " . strlen($body) . " bytes)...");
        fwrite($this->socket, $body . "\r\n.\r\n");

        $resp = $this->readResponse([250]);
        if (!$resp['success']) {
            $this->close();
            return ['success' => false, 'message' => 'Pengiriman data email gagal diterima server: ' . $resp['message'], 'logs' => $this->logs];
        }

        // 10. QUIT
        $this->sendCommand("QUIT", [221]);
        $this->close();

        return [
            'success' => true,
            'message' => 'Email berhasil dikirim via SMTP!',
            'logs'    => $this->logs
        ];
    }

    private function close(): void {
        if ($this->socket && is_resource($this->socket)) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}

/**
 * Universal Email Sender
 */
function sendEmail(string $toEmail, string $subject, string $htmlBody, string $textBody = ''): array {
    $smtpEnabled = getSetting('smtp_enabled', '0') === '1';

    $fromEmail = getSetting('smtp_from_email', getSetting('platform_email', 'admin@shawiriot.com'));
    $fromName  = getSetting('smtp_from_name', getSetting('platform_name', 'ShawirIOT'));

    if (empty($fromEmail)) {
        $fromEmail = 'admin@shawiriot.com';
    }

    if ($smtpEnabled) {
        $host   = getSetting('smtp_host', 'smtp.gmail.com');
        $port   = (int)getSetting('smtp_port', '465');
        $user   = getSetting('smtp_user', '');
        $pass   = getSetting('smtp_pass', '');
        $crypto = getSetting('smtp_crypto', 'ssl');

        if (!empty($host) && !empty($user)) {
            $smtp = new ShawirSMTP($host, $port, $user, $pass, $crypto, 12);
            $result = $smtp->send($fromEmail, $fromName, $toEmail, $subject, $htmlBody, $textBody);
            if ($result['success']) {
                return $result;
            }
            // If SMTP failed, log error
            error_log("[ShawirIOT SMTP Error] " . $result['message']);
        }
    }

    // Fallback: standard mail() function
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$encodedFromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $mailOk = @mail($toEmail, $encodedSubject, $htmlBody, $headers);
    if ($mailOk) {
        return ['success' => true, 'message' => 'Email terkirim melalui fungsi mail() server.'];
    }

    return [
        'success' => false,
        'message' => 'Gagal mengirim email: SMTP belum aktif dan server lokal tidak mendukung mail(). Periksa konfigurasi SMTP di Admin.'
    ];
}

/**
 * Generate and send Email Verification Token + 6-digit OTP
 */
function sendVerificationEmail(int $userId, string $userName, string $email): array {
    ensureEmailVerificationTable();

    // 1. Generate Token (64 char hex) & OTP (6 digit)
    $token   = generateToken(32);
    $otpCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

    // 2. Remove old pending verifications for this user/email
    try {
        DB::query("DELETE FROM email_verifications WHERE user_id = ? OR email = ?", [$userId, $email]);
    } catch (\Throwable $e) {}

    // 3. Save new verification token with 24 hours expiry
    DB::insert(
        "INSERT INTO email_verifications (user_id, email, token, otp_code, expires_at) 
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))",
        [$userId, $email, $token, $otpCode]
    );

    // Save to session for convenient developer fallback if SMTP is unconfigured
    $_SESSION['last_verification_email'] = $email;
    $_SESSION['dev_otp_fallback'] = $otpCode;
    $_SESSION['dev_token_fallback'] = $token;

    // 4. Construct Email HTML
    $platformName = getSetting('platform_name', 'ShawirIOT');
    $verifyUrl = PLATFORM_URL . '/verify.php?token=' . urlencode($token);

    $subject = "Kode Verifikasi Akun {$platformName}: {$otpCode}";
    $html = buildVerificationEmailTemplate($userName, $otpCode, $verifyUrl, $platformName);

    $result = sendEmail($email, $subject, $html);
    $result['otp']   = $otpCode;
    $result['token'] = $token;

    return $result;
}

/**
 * Modern HTML Email Template
 */
function buildVerificationEmailTemplate(string $name, string $otp, string $verifyUrl, string $platformName): string {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safePlatform = htmlspecialchars($platformName, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifikasi Akun {$safePlatform}</title>
<style>
  body { margin:0; padding:0; background-color:#0b0f19; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#cbd5e1; }
  .wrapper { width:100%; max-width:580px; margin:0 auto; padding:32px 16px; }
  .card { background:#131d31; border:1px solid #1e293b; border-radius:16px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,0.4); }
  .header { padding:32px 24px; text-align:center; background:linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%); border-bottom:1px solid #1e293b; }
  .header h1 { margin:0; font-size:24px; font-weight:800; color:#ffffff; letter-spacing:-0.5px; }
  .header p { margin:6px 0 0 0; font-size:13px; color:#94a3b8; }
  .content { padding:32px 28px; }
  .greeting { font-size:16px; color:#f8fafc; font-weight:600; margin-bottom:12px; }
  .desc { font-size:14px; line-height:1.6; color:#94a3b8; margin-bottom:24px; }
  .otp-box { background:#0a0e17; border:2px dashed #6366f1; border-radius:12px; padding:20px; text-align:center; margin:24px 0; }
  .otp-label { font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:#818cf8; font-weight:700; margin-bottom:8px; }
  .otp-code { font-family:Consolas,Monaco,'Courier New',Courier,monospace; font-size:36px; font-weight:800; letter-spacing:8px; color:#38bdf8; margin:4px 0; }
  .otp-exp { font-size:12px; color:#64748b; margin-top:6px; }
  .btn-wrap { text-align:center; margin:28px 0; }
  .btn { display:inline-block; padding:14px 32px; background:linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color:#ffffff !important; text-decoration:none; font-weight:700; font-size:15px; border-radius:10px; box-shadow:0 4px 14px rgba(99,102,241,0.4); }
  .divider { border-top:1px solid #1e293b; margin:28px 0 20px 0; }
  .footer { font-size:12px; color:#64748b; line-height:1.5; text-align:center; }
  .url-wrap { word-break:break-all; font-size:12px; color:#6366f1; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <h1>{$safePlatform}</h1>
      <p>Platform IoT Modern & Telemetri Real-time</p>
    </div>
    <div class="content">
      <div class="greeting">Halo, {$safeName}! 👋</div>
      <div class="desc">
        Terima kasih telah mendaftar di <strong>{$safePlatform}</strong>. Untuk memastikan keamanan akun Anda, silakan verifikasi alamat email ini menggunakan kode OTP atau tautan tombol di bawah ini:
      </div>

      <div class="otp-box">
        <div class="otp-label">Kode Verifikasi OTP Anda</div>
        <div class="otp-code">{$otp}</div>
        <div class="otp-exp">Kode berlaku selama 24 jam</div>
      </div>

      <div class="btn-wrap">
        <a href="{$verifyUrl}" class="btn" target="_blank">✓ Verifikasi Akun Saya</a>
      </div>

      <div class="divider"></div>

      <div style="font-size:12px; color:#94a3b8; margin-bottom:8px;">Atau salin tautan berikut ke browser Anda jika tombol tidak berfungsi:</div>
      <div class="url-wrap"><a href="{$verifyUrl}" style="color:#818cf8;">{$verifyUrl}</a></div>

      <div class="divider"></div>

      <div class="footer">
        Jika Anda tidak merasa mendaftar di {$safePlatform}, silakan abaikan email ini.<br>
        &copy; 2026 {$safePlatform}. Seluruh hak cipta dilindungi.
      </div>
    </div>
  </div>
</div>
</body>
</html>
HTML;
}
