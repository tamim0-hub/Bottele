<?php
/**
 * api/auth.php — অথেনটিকেশন API
 * POST: login, logout, change_password, csrf
 */
require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// কনফিগ না থাকলে বা $auth তৈরি না হলে
if (!isset($auth)) {
    echo json_encode(['loggedIn' => false, 'error' => 'ইনস্টল করা হয়নি।']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'ইউজারনেম ও পাসওয়ার্ড দিন।']);
        exit;
    }

    $result = $auth->login($username, $password);
    echo json_encode($result);
    exit;
}

if ($action === 'check') {
    echo json_encode(['loggedIn' => $auth->isLoggedIn(), 'username' => $auth->username()]);
    exit;
}

// নিচের সব অ্যাকশনের জন্য লগইন লাগবে
$auth->requireLogin(true);

if ($action === 'logout') {
    $auth->logout();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'change_password') {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $result = $auth->changePassword($old, $new);
    echo json_encode($result);
    exit;
}

if ($action === 'csrf') {
    echo json_encode(['token' => $auth->csrfToken()]);
    exit;
}

echo json_encode(['error' => 'অজানা অ্যাকশন।']);
