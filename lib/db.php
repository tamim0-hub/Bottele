<?php
/**
 * lib/db.php — ডাটাবেস কানেকশন ও সেটআপ
 * Database connection using PDO + table creation.
 */

class DB {
    /** @var PDO */
    public $pdo;

    public function __construct() {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // প্রোডাকশনে ডিটেইল হাইড করুন
            die('ডাটাবেস কানেকশন ব্যর্থ: ' . (defined('DEMO_MODE') && DEMO_MODE ? 'ডেমো মোডে ডাটাবেস লাগবে না' : $e->getMessage()));
        }
    }

    /**
     * সব টেবিল তৈরি করুন — install.php থেকে কল হয়
     */
    public function setup(): void {
        $sql = file_get_contents(__DIR__ . '/../setup.sql');
        // Split on CREATE TABLE boundaries for safer execution
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*\n/', $sql)),
            fn($s) => strlen($s) > 0
        );
        foreach ($statements as $stmt) {
            $this->pdo->exec($stmt);
        }

        // ডিফল্ট অ্যাডমিন ইউজার তৈরি (যদি না থাকে)
        $this->ensureAdmin();
    }

    /**
     * অ্যাডমিন ইউজার নিশ্চিত করুন
     */
    public function ensureAdmin(): void {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([ADMIN_USER]);
        if (!$stmt->fetch()) {
            $stmt = $this->pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            $stmt->execute([ADMIN_USER, ADMIN_PASS_HASH]);
        }
    }

    /**
     * এজেন্ট সেটিং পড়ুন
     */
    public function getSetting(string $key, string $default = ''): string {
        try {
            $stmt = $this->pdo->prepare('SELECT setting_value FROM agent_settings WHERE setting_key = ?');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return $row ? ($row['setting_value'] ?? $default) : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * এজেন্ট সেটিং সেভ করুন
     */
    public function setSetting(string $key, string $value): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO agent_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()'
        );
        $stmt->execute([$key, $value, $value]);
    }

    /**
     * সব সেটিং পড়ুন
     */
    public function getAllSettings(): array {
        $stmt = $this->pdo->query('SELECT setting_key, setting_value FROM agent_settings');
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
        return $out;
    }

    /**
     * এজেন্ট লগ যোগ করুন
     */
    public function addLog(string $agent, string $action, string $input = '', string $output = '', string $status = 'success'): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO agent_logs (agent, action, input_summary, output_summary, status) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$agent, $action, mb_substr($input, 0, 500), mb_substr($output, 0, 2000), $status]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * এজেন্ট স্টেট আপডেট করুন
     */
    public function setAgentState(string $agent, string $state, string $output = ''): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO agent_state (agent, state, last_run, last_output, run_count, error_count)
             VALUES (?, ?, NOW(), ?, 1, 0)
             ON DUPLICATE KEY UPDATE
               state = ?,
               last_run = NOW(),
               last_output = ?,
               run_count = run_count + 1,
               error_count = error_count + IF(? = "error", 1, 0),
               updated_at = NOW()'
        );
        $stmt->execute([$agent, $state, $output, $state, $output, $state]);
    }

    /**
     * এজেন্ট স্টেট সেট করুন (working/idle/error)
     */
    public function setAgentRunning(string $agent, bool $running): void {
        if ($running) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO agent_state (agent, state) VALUES (?, "working")
                 ON DUPLICATE KEY UPDATE state = "working", updated_at = NOW()'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE agent_state SET state = "idle", updated_at = NOW() WHERE agent = ?'
            );
        }
        $stmt->execute([$agent]);
    }

    /**
     * সব এজেন্টের স্টেট পড়ুন
     */
    public function getAgentStates(): array {
        $stmt = $this->pdo->query('SELECT * FROM agent_state ORDER BY agent');
        return $stmt->fetchAll();
    }

    /**
     * রিসেন্ট লগ পড়ুন
     */
    public function getRecentLogs(int $limit = 50): array {
        $stmt = $this->pdo->prepare('SELECT * FROM agent_logs ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * স্ট্যাটস বের করুন (ড্যাশবোর্ডের জন্য)
     */
    public function getStats(): array {
        $stats = [];

        // মোট লগ
        $stmt = $this->pdo->query('SELECT COUNT(*) as total FROM agent_logs');
        $stats['total_logs'] = (int)($stmt->fetch()['total'] ?? 0);

        // আজকের লগ
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM agent_logs WHERE DATE(created_at) = CURDATE()");
        $stats['today_logs'] = (int)($stmt->fetch()['total'] ?? 0);

        // এজেন্ট অনুযায়ী রান কাউন্ট
        $stmt = $this->pdo->query('SELECT agent, run_count, error_count, last_run, state FROM agent_state ORDER BY agent');
        $stats['agents'] = $stmt->fetchAll();

        // অর্ডার কাউন্ট
        $stmt = $this->pdo->query('SELECT COUNT(*) as total FROM agent_orders');
        $stats['total_orders'] = (int)($stmt->fetch()['total'] ?? 0);

        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM agent_orders WHERE status = 'pending'");
        $stats['pending_orders'] = (int)($stmt->fetch()['total'] ?? 0);

        // কার্ট রিকভারি কাউন্ট
        $stmt = $this->pdo->query('SELECT COUNT(*) as total FROM cart_recovery WHERE purchased = 0');
        $stats['active_carts'] = (int)($stmt->fetch()['total'] ?? 0);

        $stmt = $this->pdo->query('SELECT COUNT(*) as total FROM cart_recovery WHERE purchased = 1');
        $stats['recovered_carts'] = (int)($stmt->fetch()['total'] ?? 0);

        return $stats;
    }

    /**
     * চ্যাট মেসেজ সেভ করুন
     */
    public function saveChatMessage(string $role, string $content): int {
        $stmt = $this->pdo->prepare('INSERT INTO chat_messages (role, content) VALUES (?, ?)');
        $stmt->execute([$role, mb_substr($content, 0, 5000)]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * চ্যাট হিস্ট্রি পড়ুন
     */
    public function getChatHistory(int $limit = 50): array {
        $stmt = $this->pdo->prepare('SELECT * FROM chat_messages ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$limit]);
        return array_reverse($stmt->fetchAll());
    }

    /**
     * ক্রন লগ যোগ
     */
    public function addCronLog(string $job, string $result): void {
        $stmt = $this->pdo->prepare('INSERT INTO cron_log (job, result) VALUES (?, ?)');
        $stmt->execute([$job, mb_substr($result, 0, 1000)]);
    }
}
