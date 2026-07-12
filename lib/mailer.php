<?php
/**
 * lib/mailer.php — ইমেইল পাঠানোর ক্লাস
 * Uses PHP's mail() or SMTP via basic socket connection.
 * No external dependencies required.
 */

class Mailer {
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUser;
    private string $smtpPass;
    private string $fromEmail;
    private string $fromName;
    private bool $useSmtp;

    public function __construct() {
        $this->smtpHost  = defined('SMTP_HOST') ? SMTP_HOST : '';
        $this->smtpPort  = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
        $this->smtpUser  = defined('SMTP_USER') ? SMTP_USER : '';
        $this->smtpPass  = defined('SMTP_PASS') ? SMTP_PASS : '';
        $this->fromEmail = defined('SMTP_FROM') ? SMTP_FROM : 'noreply@aioffice.local';
        $this->fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'AI Office';
        $this->useSmtp   = !empty($this->smtpHost) && !empty($this->smtpUser);
    }

    /**
     * ইমেইল পাঠান
     * @return array{success:bool, error?:string}
     */
    public function send(string $to, string $subject, string $bodyHtml, string $bodyText = ''): array {
        // ডেমো মোডে শুধু লগ করুন
        if (defined('DEMO_MODE') && DEMO_MODE) {
            return ['success' => true, 'demo' => true, 'to' => $to, 'subject' => $subject];
        }

        if ($this->useSmtp) {
            return $this->sendSmtp($to, $subject, $bodyHtml, $bodyText);
        }

        return $this->sendMail($to, $subject, $bodyHtml, $bodyText);
    }

    /**
     * কার্ট রিকভারি ইমেইল — বাংলা টেমপ্লেট
     */
    public function sendCartRecovery(string $to, string $name, int $step, string $storeName, string $coupon = ''): array {
        $templates = $this->cartTemplates($storeName, $name, $coupon);

        $idx = min($step, count($templates) - 1);
        $tpl = $templates[$idx];

        return $this->send($to, $tpl['subject'], $tpl['html'], $tpl['text']);
    }

    /**
     * কার্ট রিকভারি টেমপ্লেট (৩ ধাপ)
     */
    private function cartTemplates(string $store, string $name, string $coupon): array {
        return [
            // ধাপ ১: ১ ঘন্টা পর — স্মরণ
            [
                'subject' => "🛒 {$name}, আপনার কার্ট অপেক্ষা করছে!",
                'html' => $this->emailWrap("
                    <h2>হ্যালো {$name},</h2>
                    <p>মনে হচ্ছে আপনি কিছু দারুণ পণ্য কার্টে রেখে গেছেন! 😊</p>
                    <p>আপনার পণ্যগুলো এখনো স্টকে আছে — দ্রুত অর্ডার কমপ্লিট করুন।</p>
                    <a href='#' style='display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;margin:16px 0;'>কার্ট দেখুন →</a>
                    <p>ধন্যবাদান্তে,<br><strong>{$store}</strong> টিম</p>
                "),
                'text' => "হ্যালো {$name}, আপনার কার্টে কিছু পণ্য বাকি আছে। দ্রুত অর্ডার কমপ্লিট করুন। ধন্যবাদ, {$store}",
            ],
            // ধাপ ২: ২৪ ঘন্টা পর — ছাড়ের অফার
            [
                'subject' => "🎁 {$name}, আপনার জন্য বিশেষ ছাড়!",
                'html' => $this->emailWrap("
                    <h2>হ্যালো {$name},</h2>
                    <p>আপনার কার্টের পণ্যে আমরা বিশেষ ছাড় দিচ্ছি! 🎉</p>
                    " . ($coupon ? "<p style='font-size:18px;padding:12px;background:#f0fdf4;border:2px dashed #22c55e;border-radius:8px;text-align:center;'>কুপন কোড: <strong>{$coupon}</strong></p>" : "") . "
                    <p>এই অফার সীমিত সময়ের — এখনই ব্যবহার করুন!</p>
                    <a href='#' style='display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;margin:16px 0;'>এখনই অর্ডার করুন →</a>
                    <p>ধন্যবাদান্তে,<br><strong>{$store}</strong> টিম</p>
                "),
                'text' => "হ্যালো {$name}, আপনার কার্টে বিশেষ ছাড়! কুপন: {$coupon}। ধন্যবাদ, {$store}",
            ],
            // ধাপ ৩: ৭২ ঘন্টা পর — শেষ সুযোগ
            [
                'subject' => "⏰ {$name}, শেষ সুযোগ — কার্ট শীঘ্রই মুছে যাবে!",
                'html' => $this->emailWrap("
                    <h2>হ্যালো {$name},</h2>
                    <p>এটি আপনার শেষ রিমাইন্ডার! আপনার কার্টের পণ্য শীঘ্রই মুছে যেতে পারে।</p>
                    <p>এই দারুণ পণ্যগুলো হাতছাড়া হতে দিতে চান না তো! 😢</p>
                    " . ($coupon ? "<p style='font-size:18px;padding:12px;background:#fef2f2;border:2px dashed #ef4444;border-radius:8px;text-align:center;'>শেষ সুযোগ! কুপন: <strong>{$coupon}</strong></p>" : "") . "
                    <a href='#' style='display:inline-block;padding:12px 24px;background:#ef4444;color:#fff;text-decoration:none;border-radius:8px;margin:16px 0;'>অর্ডার করুন — শেষ সুযোগ →</a>
                    <p style='color:#888;font-size:14px;'>আর রিমাইন্ডার পেতে চাইলে এই মেসেজ উপেক্ষা করুন।</p>
                    <p>ধন্যবাদান্তে,<br><strong>{$store}</strong> টিম</p>
                "),
                'text' => "হ্যালো {$name}, শেষ সুযোগ! কার্ট শীঘ্রই মুছে যাবে। কুপন: {$coupon}। ধন্যবাদ, {$store}",
            ],
        ];
    }

    /**
     * ইমেইল HTML র‍্যাপার
     */
    private function emailWrap(string $body): string {
        return "<!DOCTYPE html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
        <body style='margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#f8fafc;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='max-width:600px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);'>
        <tr><td style='padding:32px;color:#1e293b;line-height:1.6;'>{$body}</td></tr>
        </table></body></html>";
    }

    /**
     * PHP mail() দিয়ে পাঠানো
     */
    private function sendMail(string $to, string $subject, string $html, string $text): array {
        $boundary = uniqid('ai_office_');

        $headers  = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        $message  = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= $text . "\r\n\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $html . "\r\n\r\n";
        $message .= "--{$boundary}--\r\n";

        $sent = mail($to, "=?UTF-8?B?" . base64_encode($subject) . "?=", $message, $headers);

        if ($sent) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'mail() ফাংশনে সমস্যা। SMTP কনফিগ করুন।'];
    }

    /**
     * SMTP দিয়ে পাঠানো (বেসিক socket-based)
     * Note: STARTTLS support for port 587. For shared hosting, PHP mail() is recommended.
     */
    private function sendSmtp(string $to, string $subject, string $html, string $text): array {
        try {
            $socket = @fsockopen($this->smtpHost, $this->smtpPort, $errno, $errstr, 10);
            if (!$socket) {
                return ['success' => false, 'error' => "SMTP কানেকশন ব্যর্থ: $errstr ($errno)"];
            }

            $this->smtpRead($socket);

            // EHLO
            $this->smtpSend($socket, "EHLO " . ($this->fromEmail ? explode('@', $this->fromEmail)[1] : 'localhost'));
            $this->smtpRead($socket);

            // STARTTLS for port 587
            if ($this->smtpPort == 587) {
                $this->smtpSend($socket, "STARTTLS");
                $this->smtpRead($socket);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return ['success' => false, 'error' => 'STARTTLS ব্যর্থ।'];
                }
                $this->smtpSend($socket, "EHLO " . ($this->fromEmail ? explode('@', $this->fromEmail)[1] : 'localhost'));
                $this->smtpRead($socket);
            }

            // AUTH LOGIN
            $this->smtpSend($socket, "AUTH LOGIN");
            $this->smtpRead($socket);
            $this->smtpSend($socket, base64_encode($this->smtpUser));
            $this->smtpRead($socket);
            $this->smtpSend($socket, base64_encode($this->smtpPass));
            $response = $this->smtpRead($socket);

            if (str_starts_with($response, '5')) {
                return ['success' => false, 'error' => 'SMTP অথেনটিকেশন ব্যর্থ।'];
            }

            // MAIL FROM / RCPT TO
            $this->smtpSend($socket, "MAIL FROM:<{$this->fromEmail}>");
            $this->smtpRead($socket);
            $this->smtpSend($socket, "RCPT TO:<{$to}>");
            $this->smtpRead($socket);

            // DATA
            $this->smtpSend($socket, "DATA");
            $this->smtpRead($socket);

            $boundary = uniqid('ai_office_');
            $email  = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $email .= "To: <{$to}>\r\n";
            $email .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $email .= "MIME-Version: 1.0\r\n";
            $email .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $email .= "\r\n";
            $email .= "--{$boundary}\r\n";
            $email .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $email .= $text . "\r\n\r\n";
            $email .= "--{$boundary}\r\n";
            $email .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $email .= $html . "\r\n\r\n";
            $email .= "--{$boundary}--\r\n";
            $email .= ".\r\n";

            $this->smtpSend($socket, $email);
            $this->smtpRead($socket);

            $this->smtpSend($socket, "QUIT");
            fclose($socket);

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'SMTP ত্রুটি: ' . $e->getMessage()];
        }
    }

    private function smtpSend($socket, string $data): void {
        fwrite($socket, $data . "\r\n");
    }

    private function smtpRead($socket): string {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (preg_match('/^\d{3} /', $line)) break;
        }
        return $response;
    }

    /**
     * SMTP কনফিগার আছে কিনা
     */
    public function isConfigured(): bool {
        return $this->useSmtp || function_exists('mail');
    }
}
