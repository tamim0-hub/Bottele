<?php
/**
 * lib/cron.php — ক্রন জব ম্যানেজার
 * Runs scheduled tasks: Price check, Inventory sync, Cart recovery.
 * Called via api/cron.php with a secret token, or via cPanel cron.
 */

class Cron {
    private DB $db;
    private Agents $agents;
    private Mailer $mailer;

    public function __construct(DB $db, Agents $agents, Mailer $mailer) {
        $this->db     = $db;
        $this->agents = $agents;
        $this->mailer = $mailer;
    }

    /**
     * সব নির্ধারিত জব চালান
     */
    public function runAll(): array {
        $results = [];
        $results[] = $this->runPrice();
        $results[] = $this->runInventory();
        $results[] = $this->runCartRecovery();
        return $results;
    }

    /**
     * দাম আপডেট — মার্জিন অনুযায়ী রিপ্রাইস
     */
    public function runPrice(): array {
        $result = ['job' => 'price', 'status' => 'ok', 'updated' => 0, 'errors' => 0];

        try {
            $output = $this->agents->run('price', ['action' => 'reprice_all']);
            $result['updated'] = 1;
            $result['message'] = $output;
            $this->db->addCronLog('price', json_encode($result));
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['message'] = $e->getMessage();
            $result['errors'] = 1;
            $this->db->addCronLog('price', json_encode($result));
        }

        return $result;
    }

    /**
     * ইনভেন্টরি সিঙ্ক — out-of-stock পণ্য unpublish
     */
    public function runInventory(): array {
        $result = ['job' => 'inventory', 'status' => 'ok', 'checked' => 0, 'unpublished' => 0];

        try {
            $output = $this->agents->run('inventory', ['action' => 'sync']);
            $result['checked'] = 1;
            $result['message'] = $output;
            $this->db->addCronLog('inventory', json_encode($result));
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['message'] = $e->getMessage();
            $this->db->addCronLog('inventory', json_encode($result));
        }

        return $result;
    }

    /**
     * কার্ট রিকভারি — ধাপ অনুযায়ী ইমেইল পাঠান
     */
    public function runCartRecovery(): array {
        $result = ['job' => 'cart_recovery', 'status' => 'ok', 'sent' => 0, 'errors' => 0];

        try {
            $output = $this->agents->run('cart_recovery', ['action' => 'send_pending']);
            $result['sent'] = 1;
            $result['message'] = $output;
            $this->db->addCronLog('cart_recovery', json_encode($result));
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['message'] = $e->getMessage();
            $result['errors'] = 1;
            $this->db->addCronLog('cart_recovery', json_encode($result));
        }

        return $result;
    }

    /**
     * কোন ক্রন জব চালানো উচিত কিনা চেক
     */
    public function shouldRun(string $job, int $intervalHours = 24): bool {
        try {
            $stmt = $this->db->pdo->prepare(
                'SELECT ran_at FROM cron_log WHERE job = ? ORDER BY ran_at DESC LIMIT 1'
            );
            $stmt->execute([$job]);
            $last = $stmt->fetch();
            if (!$last) return true;
            return (time() - strtotime($last['ran_at'])) >= ($intervalHours * 3600);
        } catch (Exception $e) {
            return true;
        }
    }
}
