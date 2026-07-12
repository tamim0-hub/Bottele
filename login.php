<?php
/**
 * login.php — লগইন পেজ
 * AI Office — Dropshipping Automation
 */
require_once __DIR__ . '/lib/bootstrap.php';

// কনফিগ না থাকলে ইনস্টলারে পাঠান
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

// ইতিমধ্যে লগইন আছে
if (isset($auth) && $auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (isset($auth)) {
        $result = $auth->login($username, $password);
        if ($result['success']) {
            header('Location: index.php');
            exit;
        }
        $error = $result['error'] ?? 'লগইন ব্যর্থ।';
    } else {
        $error = 'সিস্টেম ত্রুটি — অথেনটিকেশন লোড হয়নি।';
    }
}

$demoMode = defined('DEMO_MODE') && DEMO_MODE;
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>লগইন — AI Office</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">🏢</div>
                <h1>AI Office</h1>
                <p>ড্রপশিপিং অটোমেশন</p>
            </div>

            <?php if ($error): ?>
                <div class="toast toast-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($demoMode): ?>
                <div class="toast toast-info">ডেমো মোড: ইউজারনেম <strong>admin</strong>, পাসওয়ার্ড <strong>admin123</strong></div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="login-form">
                <div class="form-group">
                    <label for="username">👤 ইউজারনেম</label>
                    <input type="text" id="username" name="username" required autofocus
                           placeholder="আপনার ইউজারনেম" autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">🔑 পাসওয়ার্ড</label>
                    <input type="password" id="password" name="password" required
                           placeholder="আপনার পাসওয়ার্ড" autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    লগইন করুন →
                </button>
            </form>

            <div class="login-footer">
                <p>🇧🇩 বাংলাদেশের ড্রপশিপারদের জন্য তৈরি</p>
            </div>
        </div>
    </div>
</body>
</html>
