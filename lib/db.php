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
            $this->pdo = null;
        }
    }

    /**
     * ডাটাবেস কানেক্টেড কিনা
     */
    public function isConnected(): bool {
        return $this->pdo !== null;
    }

    /**
     * সব টেবিল তৈরি করুন — install.php থেকে কল হয়
     */
    public function setup(): void {
        if (!$this->pdo) {
            throw new RuntimeException('ডাটাবেস কানেকশন নেই।');
        }
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
        if (!$this->pdo) return;
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([ADMIN_USER]);
            if (!$stmt->fetch()) {
                $stmt = $this->pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
                $stmt->execute([ADMIN_USER, ADMIN_PASS_HASH]);
            }
        } catch (Exception $e) {
            // সাইলেন্ট — ইনস্টলার থেকে কল হয়
        }
    }

    /**
     * এজেন্ট সেটিং পড়ুন
     */
    public function getSetting(string $key, string $default = ''): string {
        try {
            if (!$this->pdo) return $default;
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
        if (!$this->pdo) return;
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO agent_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()'
            );
            $stmt->execute([$key, $value, $value]);
        } catch (Exception $e) {
            // সাইলেন্ট
        }
    }

    /**
     * সব সেটিং পড়ুন
     */
    public function getAllSettings(): array {
        if (!$this->pdo) return [];
        try {
            $stmt = $this->pdo->query('SELECT setting_key, setting_value FROM agent_settings');
            $rows = $stmt->fetchAll();
            $out = [];
            foreach ($rows as $r) {
                $out[$r['setting_key']] = $r['setting_value'];
            }
            return $out;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * এজেন্ট লগ যোগ করুন
     */
    public function addLog(string $agent, string $action, string $input = '', string $output = '', string $status = 'success'): int {
        if (!$this->pdo) return 0;
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO agent_logs (agent, action, input_summary, output_summary, status) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$agent, $action, mb_substr($input, 0, 500), mb_substr($output, 0, 2000), $status]);
            return (int)$this->pdo->lastInsertId();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * এজেন্ট স্টেট আপডেট করুন
     */
    public function setAgentState(string $agent, string $state, string $output = ''): void {
        if (!$this->pdo) return;
        try {
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
        } catch (Exception $e) {
            // সাইলেন্ট — টেবিল না থাকলে
        }
    }

    /**
     * এজেন্ট স্টেট সেট করুন (working/idle/error)
     */
    public function setAgentRunning(string $agent, bool $running): void {
        if (!$this->pdo) return;
        try {
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
        } catch (Exception $e) {
            // সাইলেন্ট
        }
    }

    /**
     * অ্যাটমিক: শুধুমাত্র idle হলে working সেট করুন (রেস কন্ডিশন রোধ)
     * @return bool সফল হলে true, ইতিমধ্যে working হলে false
     */
    public function setAgentRunningIfIdle(string $agent, bool $running): bool {
        if (!$this->pdo) return false;
        try {
            if ($running) {
                // অ্যাটমিক: রো না থাকলে INSERT, থাকলে শুধু idle/error হলে UPDATE
                // rowCount: ১=নতুন INSERT, ২=UPDATE হয়েছে, ০=কোনো পরিবর্তন নেই (ইতিমধ্যে working)
                $stmt = $this->pdo->prepare(
                    'INSERT INTO agent_state (agent, state, updated_at) VALUES (?, "working", NOW())
                     ON DUPLICATE KEY UPDATE
                       state = IF(state IN ("idle", "error"), "working", state),
                       updated_at = IF(state IN ("idle", "error"), NOW(), updated_at)'
                );
                $stmt->execute([$agent]);
                $affected = $stmt->rowCount();
                return $affected > 0; // ১ (নতুন রো) বা ২ (আপডেট) হলে সফল
            }
            return true; // idle সেট করা সবসময় সফল
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * সব এজেন্টের স্টেট পড়ুন
     */
    public function getAgentStates(): array {
        if (!$this->pdo) return [];
        try {
            $stmt = $this->pdo->query('SELECT * FROM agent_state ORDER BY agent');
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * রিসেন্ট লগ পড়ুন
     */
    public function getRecentLogs(int $limit = 50): array {
        if (!$this->pdo) return [];
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM agent_logs ORDER BY created_at DESC LIMIT ?');
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * স্ট্যাটস বের করুন (ড্যাশবোর্ডের জন্য)
     */
    public function getStats(): array {
        if (!$this->pdo) return ['total_logs' => 0, 'today_logs' => 0, 'agents' => [], 'total_orders' => 0, 'pending_orders' => 0, 'active_carts' => 0, 'recovered_carts' => 0];
        try {
        $stats = [
            'total_logs' => 0, 'today_logs' => 0, 'agents' => [],
            'total_orders' => 0, 'pending_orders' => 0,
            'active_carts' => 0, 'recovered_carts' => 0,
        ];
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
        } catch (Exception $e) {
            return ['total_logs' => 0, 'today_logs' => 0, 'agents' => [], 'total_orders' => 0, 'pending_orders' => 0, 'active_carts' => 0, 'recovered_carts' => 0];
        }
    }

    /**
     * চ্যাট মেসেজ সেভ করুন
     */
    public function saveChatMessage(string $role, string $content, int $userId = 0): int {
        if (!$this->pdo) return 0;
        try {
            $stmt = $this->pdo->prepare('INSERT INTO chat_messages (role, content, user_id) VALUES (?, ?, ?)');
            $stmt->execute([$role, mb_substr($content, 0, 5000), $userId]);
            return (int)$this->pdo->lastInsertId();
        } catch (Exception $e) {
            // user_id কলাম না থাকলে ফলব্যাক (পুরানো স্কিমা)
            try {
                $stmt = $this->pdo->prepare('INSERT INTO chat_messages (role, content) VALUES (?, ?)');
                $stmt->execute([$role, mb_substr($content, 0, 5000)]);
                return (int)$this->pdo->lastInsertId();
            } catch (Exception $e2) {
                return 0;
            }
        }
    }

    /**
     * চ্যাট হিস্ট্রি পড়ুন (ইউজার অনুযায়ী ফিল্টার)
     */
    public function getChatHistory(int $limit = 50, int $userId = 0): array {
        if (!$this->pdo) return [];
        try {
            if ($userId > 0) {
                $stmt = $this->pdo->prepare('SELECT * FROM chat_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
                $stmt->bindValue(1, $userId, PDO::PARAM_INT);
                $stmt->bindValue(2, $limit, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $this->pdo->prepare('SELECT * FROM chat_messages ORDER BY created_at DESC LIMIT ?');
                $stmt->bindValue(1, $limit, PDO::PARAM_INT);
                $stmt->execute();
            }
            return array_reverse($stmt->fetchAll());
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * আটকে থাকা এজেন্ট অ্যাটমিকভাবে রিসেট করুন (run_count বাড়াবে না)
     * @return bool রিসেট হলে true
     */
    public function resetStuckAgentIfOld(string $agent, int $stuckSeconds = 300): bool {
        if (!$this->pdo) return false;
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE agent_state SET state = "idle", last_output = "স্বয়ংক্রিয়ভাবে রিসেট (আটকে ছিল)", updated_at = NOW()
                 WHERE agent = ? AND state = "working" AND updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)'
            );
            $stmt->execute([$agent, $stuckSeconds]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * ক্রন লগ যোগ
     */
    public function addCronLog(string $job, string $result): void {
        if (!$this->pdo) return;
        try {
            $stmt = $this->pdo->prepare('INSERT INTO cron_log (job, result) VALUES (?, ?)');
            $stmt->execute([$job, mb_substr($result, 0, 1000)]);
        } catch (Exception $e) {
            // সাইলেন্ট
        }
    }
}
