<?php
/**
 * api/cron.php — ক্রন জব এন্ডপয়েন্ট
 * ক্রন টোকেন দিয়ে কল করুন:
 *   GET api/cron.php?token=YOUR_CRON_TOKEN&job=all|price|inventory|cart_recovery
 *
 * cPanel ক্রন:
 *   0 */6 * * * php /path/to/api/cron.php token=YOUR_TOKEN job=all
 *
 * অথবা cron-job.org থেকে:
 *   https://yourdomain.com/api/cron.php?token=YOUR_TOKEN&job=all
 */
require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($db) || !isset($cron)) {
    http_response_code(503);
    echo json_encode(['error' => 'সিস্টেম ইনস্টল হয়নি।']);
    exit;
}

// ক্রন টোকেন চেক (config.php থেকে)
$cronToken = $_GET['token'] ?? '';
$expectedToken = $db->getSetting('cron_token', '');

// টোকেন না থাকলে তৈরি করুন
if (empty($expectedToken)) {
    $expectedToken = bin2hex(random_bytes(16));
    $db->setSetting('cron_token', $expectedToken);
}

if ($cronToken !== $expectedToken) {
    http_response_code(403);
    echo json_encode(['error' => 'অবৈধ ক্রন টোকেন।']);
    exit;
}

$job = $_GET['job'] ?? 'all';

try {
    switch ($job) {
        case 'price':
            $result = $cron->runPrice();
            break;
        case 'inventory':
            $result = $cron->runInventory();
            break;
        case 'cart_recovery':
            $result = $cron->runCartRecovery();
            break;
        case 'all':
            $result = $cron->runAll();
            break;
        default:
            $result = [['job' => $job, 'status' => 'error', 'message' => 'অজানা জব।']];
            break;
    }

    echo json_encode([
        'success' => true,
        'job'     => $job,
        'result'  => $result,
        'time'    => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'ক্রন ত্রুটি: ' . $e->getMessage()]);
}
