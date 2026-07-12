<?php
/**
 * lib/auth.php — সেশন-ভিত্তিক অথেনটিকেশন
 * Session-based authentication with bcrypt password hashing.
 */

class Auth {
    private DB $db;

    public function __construct(DB $db) {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session settings
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_strict_mode', '1');
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                ini_set('session.cookie_secure', '1');
            }
            session_name('ai_office_sess');
            session_start();
        }
    }

    /**
     * লগইন চেষ্টা করুন
     * @return array{success:bool, error?:string}
     */
    public function login(string $username, string $password): array {
        // ব্রুট-ফোর্স প্রোটেকশন — ৫ বার ভুল হলে ১৫ মিনিট ব্লক
        if ($this->isLoginBlocked()) {
            return ['success' => false, 'error' => 'অনেকবার ভুল চেষ্টা হয়েছে। ১৫ মিনিট পর আবার চেষ্টা করুন।'];
        }

        try {
            $stmt = $this->db->pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'error' => 'ইউজারনেম বা পাসওয়ার্ড ভুল।'];
            }

            if (!password_verify($password, $user['password_hash'])) {
                $this->recordFailedLogin();
                return ['success' => false, 'error' => 'ইউজারনেম বা পাসওয়ার্ড ভুল।'];
            }

            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['login_time'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $this->clearFailedLogins();

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'লগইনে সমস্যা হয়েছে।'];
        }
    }

    /**
     * লগআউট
     */
    public function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /**
     * লগইন আছে কিনা চেক
     */
    public function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
    }

    /**
     * গার্ড — লগইন না থাকলে লগইন পেজে পাঠান
     * API endpoints এর জন্য JSON response দেয়।
     */
    public function requireLogin(bool $isApi = false): void {
        if (!$this->isLoggedIn()) {
            if ($isApi) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'লগইন প্রয়োজন।', 'code' => 401]);
                exit;
            } else {
                header('Location: login.php');
                exit;
            }
        }
    }

    /**
     * CSRF টোকেন যাচাই
     */
    public function verifyCsrf(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * CSRF টোকেন পান
     */
    public function csrfToken(): string {
        return $_SESSION['csrf_token'] ?? '';
    }

    /**
     * পাসওয়ার্ড পরিবর্তন
     */
    public function changePassword(string $oldPass, string $newPass): array {
        try {
            $stmt = $this->db->pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($oldPass, $user['password_hash'])) {
                return ['success' => false, 'error' => 'পুরানো পাসওয়ার্ড ভুল।'];
            }

            if (strlen($newPass) < 6) {
                return ['success' => false, 'error' => 'নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।'];
            }

            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $this->db->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $_SESSION['user_id']]);

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'পাসওয়ার্ড পরিবর্তনে সমস্যা।'];
        }
    }

    /**
     * নতুন ইউজার তৈরি (শুধু admin দ্বারা)
     */
    public function createUser(string $username, string $password): array {
        try {
            if (strlen($username) < 3 || strlen($password) < 6) {
                return ['success' => false, 'error' => 'ইউজারনেম ৩+ এবং পাসওয়ার্ড ৬+ অক্ষর দিন।'];
            }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $this->db->pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$username, $hash]);
            return ['success' => true];
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return ['success' => false, 'error' => 'এই ইউজারনেম আগেই আছে।'];
            }
            return ['success' => false, 'error' => 'ইউজার তৈরিতে সমস্যা।'];
        }
    }

    /**
     * সেশনের বর্তমান ইউজারনেম
     */
    public function username(): string {
        return $_SESSION['username'] ?? '';
    }

    // ── ব্রুট-ফোর্স প্রোটেকশন ────────────────────────────────

    /**
     * লগইন ব্লক আছে কিনা (সেশন + IP-ভিত্তিক ডাবল চেক)
     */
    private function isLoginBlocked(): bool {
        // সেশন-ভিত্তিক চেক
        $attempts = (int)($_SESSION['login_attempts'] ?? 0);
        $lastAttempt = (int)($_SESSION['login_last_attempt'] ?? 0);
        if ($attempts >= 5 && (time() - $lastAttempt) < 900) {
            return true;
        }
        // ১৫ মিনিট পর রিসেট
        if ($attempts >= 5 && (time() - $lastAttempt) >= 900) {
            $this->clearFailedLogins();
        }

        // IP-ভিত্তিক চেক (সেশন বাইপাস রোধ)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($ip)) {
            $ipFile = sys_get_temp_dir() . '/ai_office_login_' . md5($ip);
            if (file_exists($ipFile)) {
                $data = json_decode(file_get_contents($ipFile), true);
                if ($data && ($data['attempts'] ?? 0) >= 10 && (time() - ($data['last'] ?? 0)) < 900) {
                    return true;
                }
                if ($data && ($data['attempts'] ?? 0) >= 10 && (time() - ($data['last'] ?? 0)) >= 900) {
                    @unlink($ipFile);
                }
            }
        }

        return false;
    }

    /**
     * ব্যর্থ লগইন রেকর্ড (সেশন + IP ফাইল)
     */
    private function recordFailedLogin(): void {
        $_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['login_last_attempt'] = time();

        // IP-ভিত্তিক কাউন্টার
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($ip)) {
            $ipFile = sys_get_temp_dir() . '/ai_office_login_' . md5($ip);
            $data = ['attempts' => 0, 'last' => 0];
            if (file_exists($ipFile)) {
                $existing = json_decode(file_get_contents($ipFile), true);
                if ($existing) $data = $existing;
            }
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;
            $data['last'] = time();
            @file_put_contents($ipFile, json_encode($data));
        }
    }

    /**
     * ব্যর্থ লগইন কাউন্টার রিসেট
     */
    private function clearFailedLogins(): void {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_last_attempt'] = 0;

        // IP ফাইলও রিসেট (সফল লগইনে)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($ip)) {
            $ipFile = sys_get_temp_dir() . '/ai_office_login_' . md5($ip);
            @unlink($ipFile);
        }
    }
}
