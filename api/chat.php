<?php
/**
 * api/chat.php — ইনবক্স চ্যাট API
 * POST {message} → Groq coach reply + save to DB.
 */
require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$auth->requireLogin(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'শুধু POST মেথড।']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$message = trim($data['message'] ?? '');

if (empty($message)) {
    echo json_encode(['error' => 'মেসেজ দিন।']);
    exit;
}

// ইউজার মেসেজ সেভ
$db->saveChatMessage('user', $message);

// চ্যাট হিস্ট্রি তৈরি
$history = $db->getChatHistory(20);
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
$db->saveChatMessage('assistant', $reply);

echo json_encode([
    'success' => true,
    'reply'   => $reply,
], JSON_UNESCAPED_UNICODE);
