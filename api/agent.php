<?php
/**
 * api/agent.php — এজেন্ট API
 * POST {agent, input: {}} → runs agent, returns JSON output.
 */
require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// বুটস্ট্র্যাপ লোড না হলে
if (!isset($auth) || !isset($agents)) {
    http_response_code(503);
    echo json_encode(['error' => 'সিস্টেম ইনস্টল হয়নি।']);
    exit;
}

// অথেনটিকেশন
$auth->requireLogin(true);

// শুধু POST রিকোয়েস্ট গ্রহণ
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'শুধু POST মেথড গ্রহণযোগ্য।']);
    exit;
}

// JSON বডি পার্স
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    // Fallback to form data
    $data = $_POST;
}

$agent = $data['agent'] ?? '';
$input = $data['input'] ?? [];

// ইনপুট ভ্যালিডেশন — অ্যারে হতে হবে, সাইজ লিমিট
if (!is_array($input)) {
    $input = [];
}
// ইনপুট JSON সাইজ লিমিট — ১০KB
$inputJsonSize = strlen(json_encode($input));
if ($inputJsonSize > 10240) {
    http_response_code(413);
    echo json_encode(['error' => 'ইনপুট ডাটা অনেক বড়।']);
    exit;
}

if (empty($agent)) {
    http_response_code(400);
    echo json_encode(['error' => 'এজেন্ট নাম দিন।']);
    exit;
}

// CSRF টোকেন যাচাই
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? '');
if (!$auth->verifyCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF টোকেন অবৈধ। পেজ রিফ্রেশ করুন।']);
    exit;
}

// বৈধ এজেন্ট চেক
$validAgents = ['leader', 'product_import', 'price', 'inventory', 'cart_recovery', 'social', 'seo', 'content', 'customer_reply', 'order_prep'];

if (!in_array($agent, $validAgents)) {
    http_response_code(400);
    echo json_encode(['error' => 'অজানা এজেন্ট: ' . $agent]);
    exit;
}

// এজেন্ট রান
$output = $agents->run($agent, $input);

echo json_encode([
    'success' => true,
    'agent'   => $agent,
    'output'  => $output,
], JSON_UNESCAPED_UNICODE);
