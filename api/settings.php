<?php
/**
 * api/settings.php — সেটিংস API
 * GET: সব নন-সিক্রেট সেটিংস পান
 * POST: সেটিংস আপডেট করুন
 */
require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($auth)) {
    http_response_code(503);
    echo json_encode(['error' => 'সিস্টেম ইনস্টল হয়নি।']);
    exit;
}

$auth->requireLogin(true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $allSettings = $db->getAllSettings();
    // সিক্রেট সেটিংস ব্রাউজারে পাঠাবেন না
    unset($allSettings['cron_token']);
    echo json_encode(['success' => true, 'settings' => $allSettings], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    // CSRF টোকেন যাচাই
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? '');
    if (!$auth->verifyCsrf($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF টোকেন অবৈধ। পেজ রিফ্রেশ করুন।']);
        exit;
    }

    // নন-সিক্রেট সেটিংস অনুমোদিত (কোনো API key, পাসওয়ার্ড নয়)
    $allowedKeys = [
        'profit_margin', 'bk_id', 'bk_phone', 'ai_model',
        'store_name', 'currency', 'language',
        'cart_step1_hours', 'cart_step2_hours', 'cart_step3_hours',
        'social_platforms', 'seo_target_score',
    ];

    $updated = 0;
    foreach ($data as $key => $value) {
        if (in_array($key, $allowedKeys)) {
            $db->setSetting($key, (string)$value);
            $updated++;
        }
    }

    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'message' => "{$updated}টি সেটিং আপডেট হয়েছে।",
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'GET বা POST ব্যবহার করুন।']);
