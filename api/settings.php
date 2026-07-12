<?php
/**
 * api/settings.php — সেটিংস API
 * GET: সব নন-সিক্রেট সেটিংস পান
 * POST: সেটিংস আপডেট করুন
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

    // ভ্যালিডেশন রুল
    $validations = [
        'profit_margin'    => ['min' => 0, 'max' => 500, 'type' => 'float'],
        'cart_step1_hours' => ['min' => 0, 'max' => 720, 'type' => 'int'],
        'cart_step2_hours' => ['min' => 0, 'max' => 720, 'type' => 'int'],
        'cart_step3_hours' => ['min' => 0, 'max' => 720, 'type' => 'int'],
        'seo_target_score' => ['min' => 0, 'max' => 100, 'type' => 'int'],
        'store_name'       => ['maxlen' => 100],
        'bk_id'            => ['maxlen' => 50],
        'bk_phone'         => ['maxlen' => 20],
    ];

    $updated = 0;
    $errors = [];
    foreach ($data as $key => $value) {
        if (!in_array($key, $allowedKeys)) continue;

        $val = (string)$value;

        // ভ্যালিডেশন চেক
        if (isset($validations[$key])) {
            $v = $validations[$key];
            if (isset($v['type']) && $v['type'] === 'int') {
                $intVal = (int)$val;
                if (isset($v['min']) && $intVal < $v['min']) { $errors[] = "{$key}: সর্বনিম্ন {$v['min']}"; continue; }
                if (isset($v['max']) && $intVal > $v['max']) { $errors[] = "{$key}: সর্বোচ্চ {$v['max']}"; continue; }
                $val = (string)$intVal;
            }
            if (isset($v['type']) && $v['type'] === 'float') {
                $floatVal = (float)$val;
                if (isset($v['min']) && $floatVal < $v['min']) { $errors[] = "{$key}: সর্বনিম্ন {$v['min']}"; continue; }
                if (isset($v['max']) && $floatVal > $v['max']) { $errors[] = "{$key}: সর্বোচ্চ {$v['max']}"; continue; }
                $val = (string)$floatVal;
            }
            if (isset($v['maxlen']) && mb_strlen($val) > $v['maxlen']) {
                $val = mb_substr($val, 0, $v['maxlen']);
            }
        }

        $db->setSetting($key, $val);
        $updated++;
    }

    if (!empty($errors)) {
        echo json_encode([
            'success' => true,
            'updated' => $updated,
            'warnings' => $errors,
            'message' => "{$updated}টি সেটিং আপডেট হয়েছে। " . implode('; ', $errors),
        ], JSON_UNESCAPED_UNICODE);
        exit;
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
