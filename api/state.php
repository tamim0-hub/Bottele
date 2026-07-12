<?php
/**
 * api/state.php — এজেন্ট স্টেট, লগ, স্ট্যাটস API
 * GET → returns agent states + recent logs + stats.
 */
require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!isset($auth)) {
    http_response_code(503);
    echo json_encode(['error' => 'সিস্টেম ইনস্টল হয়নি।']);
    exit;
}

$auth->requireLogin(true);

try {
    $states   = $db->getAgentStates();
    $logs     = $db->getRecentLogs(30);
    $stats    = $db->getStats();
    $allSettings = $db->getAllSettings();
    // সিক্রেট সেটিংস ব্রাউজারে পাঠাবেন না
    unset($allSettings['cron_token']);
    $settings = $allSettings;

    echo json_encode([
        'success'  => true,
        'states'   => $states,
        'logs'     => $logs,
        'stats'    => $stats,
        'settings' => $settings,
        'demo'     => defined('DEMO_MODE') && DEMO_MODE,
        'time'     => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'স্টেট লোডে সমস্যা: ' . $e->getMessage()]);
}
