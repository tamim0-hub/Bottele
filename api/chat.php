<?php
/**
 * api/chat.php — ইনবক্স চ্যাট API
 * POST {message} → Groq coach reply + save to DB.
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
    // চ্যাট হিস্ট্রি লোড (শুধু নিজের মেসেজ)
    try {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $history = $db->getChatHistory(50, $userId);
        echo json_encode(['success' => true, 'messages' => $history], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'messages' => []]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'GET (হিস্ট্রি) বা POST (মেসেজ) ব্যবহার করুন।']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$message = trim($data['message'] ?? '');

if (empty($message)) {
    echo json_encode(['error' => 'মেসেজ দিন।']);
    exit;
}

// CSRF টোকেন যাচাই
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? '');
if (!$auth->verifyCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF টোকেন অবৈধ। পেজ রিফ্রেশ করুন।']);
    exit;
}

// মেসেজ সাইজ লিমিট — ১০০০ অক্ষর
if (mb_strlen($message) > 1000) {
    echo json_encode(['error' => 'মেসেজ ১০০০ অক্ষরের মধ্যে দিন।']);
    exit;
}

// ইউজার মেসেজ সেভ
$userId = (int)($_SESSION['user_id'] ?? 0);
$db->saveChatMessage('user', $message, $userId);

// চ্যাট হিস্ট্রি তৈরি (শুধু নিজের)
$history = $db->getChatHistory(20, $userId);
$messages = [];
foreach ($history as $h) {
    $messages[] = ['role' => $h['role'], 'content' => $h['content']];
}

// সিস্টেম প্রম্পট যোগ
array_unshift($messages, [
    'role' => 'system',
    'content' => 'তুমি AI Office-এর AI কোচ। তুমি বাংলাদেশের ড্রপশিপিং এন্ট্রেপ্রেনারকে সাহায্য করো। বাংলায় উত্তর দাও। BusinessKoro.com প্ল্যাটফর্মের কথা মাথায় রাখো। সংক্ষেপে, কার্যকর পরামর্শ দাও।',
]);

// Groq কল
$reply = $groq->chat($messages, 1024);

// AI রিপ্লাই সেভ
$db->saveChatMessage('assistant', $reply, $userId);

echo json_encode([
    'success' => true,
    'reply'   => $reply,
], JSON_UNESCAPED_UNICODE);
