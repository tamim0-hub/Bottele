<?php
/**
 * install.php — ওয়েব ইনস্টলার
 * AI Office — Dropshipping Automation
 * 
 * এই ফাইলটি config.php তৈরি করে এবং ডাটাবেস টেবিল সেটআপ করে।
 */
// সেশন শুরু (কোনো আউটপুটের আগে)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF টোকেন তৈরি (ইনস্টলার সুরক্ষা)
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

// নিরাপত্তা: install.lock ফাইল থাকলে ইনস্টলার বন্ধ
if (file_exists(__DIR__ . '/install.lock')) {
    die('🚫 ইনস্টলার লক করা আছে। পুনরায় ইনস্টল করতে install.lock ফাইল ডিলিট করুন।');
}

$step = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$message = '';
$error = '';

// ইতিমধ্যে ইনস্টল হয়েছে কিনা চেক (ধাপ ৫ হলে সম্পন্ন)
$alreadyInstalled = file_exists(__DIR__ . '/config.php') && $step < 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF যাচাই
    $csrfToken = $_POST['install_csrf'] ?? '';
    if (empty($csrfToken) || !hash_equals($_SESSION['install_csrf'], $csrfToken)) {
        $error = 'CSRF টোকেন অবৈধ। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।';
        $step = max(1, $step - 1);
    } elseif (file_exists(__DIR__ . '/config.php')) {
        $error = 'AI Office ইতিমধ্যে ইনস্টল হয়েছে। config.php ডিলিট না করে পুনরায় ইনস্টল করা যাবে না।';
        $step = 1;
    } elseif ($step === 2) {
        // ডাটাবেস টেস্ট
        $dbHost = preg_replace('/[^a-zA-Z0-9._:-]/', '', $_POST['db_host'] ?? 'localhost');
        $dbName = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['db_name'] ?? '');
        $dbUser = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';

        if (empty($dbName) || empty($dbUser)) {
            $error = 'ডাটাবেস নাম ও ইউজার দিন।';
            $step = 1;
        } else {
            try {
                $dsn = "mysql:host={$dbHost};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                // ডাটাবেস তৈরি করুন যদি না থাকে
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$dbName}`");
                $_SESSION['install_db'] = compact('dbHost', 'dbName', 'dbUser', 'dbPass');
                $step = 3;
                $message = '✅ ডাটাবেস কানেকশন সফল!';
            } catch (PDOException $e) {
                error_log('install.php DB connection failed: ' . $e->getMessage());
                $error = 'ডাটাবেস কানেকশন ব্যর্থ। হোস্ট, ইউজারনেম ও পাসওয়ার্ড চেক করুন।';
                $step = 1;
            }
        }
    } elseif ($step === 4) {
        // ধাপ ২ সম্পন্ন হয়েছে কিনা যাচাই
        if (empty($_SESSION['install_db'])) {
            $error = 'ডাটাবেস কানেকশন ধাপ এখনো সম্পন্ন হয়নি। ধাপ ১ থেকে শুরু করুন।';
            $step = 1;
        } else {
        // অ্যাডমিন সেটআপ + config.php তৈরি
        $adminUser = $_POST['admin_user'] ?? 'admin';
        $adminPass = $_POST['admin_pass'] ?? '';
        $adminPass2 = $_POST['admin_pass2'] ?? '';

        if (strlen($adminPass) < 6) {
            $error = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।';
            $step = 3;
        } elseif ($adminPass !== $adminPass2) {
            $error = 'পাসওয়ার্ড মিলছে না।';
            $step = 3;
        } else {
            $db = $_SESSION['install_db'] ?? [];
            $hash = password_hash($adminPass, PASSWORD_BCRYPT);

            $config = "<?php\n";
            $config .= "/**\n * AI Office — কনফিগারেশন (ইনস্টলার দ্বারা তৈরি)\n * এই ফাইল সরাসরি সম্পাদনা করবেন না — সেটিংস প্যানেল ব্যবহার করুন।\n */\n\n";
            $config .= "define('DB_HOST', " . var_export($db['dbHost'] ?? 'localhost', true) . ");\n";
            $config .= "define('DB_NAME', " . var_export($db['dbName'] ?? '', true) . ");\n";
            $config .= "define('DB_USER', " . var_export($db['dbUser'] ?? '', true) . ");\n";
            $config .= "define('DB_PASS', " . var_export($db['dbPass'] ?? '', true) . ");\n\n";
            $config .= "define('ADMIN_USER', " . var_export($adminUser, true) . ");\n";
            $config .= "define('ADMIN_PASS_HASH', " . var_export($hash, true) . ");\n\n";
            $config .= "define('GROQ_API_KEY', '');\n";
            $config .= "define('GROQ_MODEL', 'llama-3.3-70b-versatile');\n\n";
            $config .= "define('WOO_URL', '');\n";
            $config .= "define('WOO_CK', '');\n";
            $config .= "define('WOO_CS', '');\n\n";
            $config .= "define('SMTP_HOST', '');\n";
            $config .= "define('SMTP_PORT', 587);\n";
            $config .= "define('SMTP_USER', '');\n";
            $config .= "define('SMTP_PASS', '');\n";
            $config .= "define('SMTP_FROM', '');\n";
            $config .= "define('SMTP_FROM_NAME', 'AI Office');\n\n";
            $config .= "define('SITE_URL', '');\n";
            $config .= "define('LICENSE_KEY', '');\n";
            $config .= "define('DEMO_MODE', false);\n";
            $config .= "define('APP_TIMEZONE', 'Asia/Dhaka');\n";

            file_put_contents(__DIR__ . '/config.php', $config);

            // ডাটাবেস সেটআপ
            require_once __DIR__ . '/config.php';
            require_once __DIR__ . '/lib/db.php';
            require_once __DIR__ . '/lib/auth.php';
            require_once __DIR__ . '/lib/groq.php';
            require_once __DIR__ . '/lib/woo.php';
            require_once __DIR__ . '/lib/mailer.php';
            require_once __DIR__ . '/lib/agents.php';
            require_once __DIR__ . '/lib/cron.php';
            require_once __DIR__ . '/lib/demo.php';

            // সেশন থেকে সংবেদনশীল ডাটা মুছুন (নিরাপত্তা)
            unset($_SESSION['install_db']);

            try {
                $dbObj = new DB();
                $dbObj->setup();
                $step = 5;
                $message = '🎉 ইনস্টলেশন সম্পন্ন!';

                // ইনস্টলার লক করুন — পুনরায় ইনস্টল রোধ
                file_put_contents(__DIR__ . '/install.lock', date('Y-m-d H:i:s'));

                // CSRF টোকেন রিজেনারেট (টোকেন রিইউজ রোধ)
                $_SESSION['install_csrf'] = bin2hex(random_bytes(32));

                // ডেমো ডাটা অফার
                if (isset($_POST['seed_demo']) && $_POST['seed_demo'] === '1') {
                    Demo::seed($dbObj);
                    $message .= ' (ডেমো ডাটা সিড করা হয়েছে)';
                }
            } catch (Exception $e) {
                error_log('install.php DB setup error: ' . $e->getMessage());
                $error = 'ডাটাবেস সেটআপ ত্রুটি। সার্ভার লগ দেখুন।';
                $step = 3;
            }
        }
        } // ধাপ ২ যাচাই এর else বন্ধ
    }
}

// হেডার আউটপুট
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ইনস্টলার — AI Office</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card" style="max-width:550px;">
            <div class="login-header">
                <div class="login-logo">🔧</div>
                <h1>AI Office ইনস্টলার</h1>
                <p>ধাপ <?= min($step, 4) ?>/4</p>
            </div>

            <?php if ($error): ?>
                <div class="toast toast-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="toast toast-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($alreadyInstalled && $step < 5): ?>
                <div class="toast toast-info">⚠️ AI Office ইতিমধ্যে ইনস্টল হয়েছে। <a href="login.php">লগইন করুন</a></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <!-- ধাপ ১: ডাটাবেস কানেকশন -->
                <form method="POST" class="login-form">
                    <input type="hidden" name="step" value="2">
                    <input type="hidden" name="install_csrf" value="<?= htmlspecialchars($_SESSION['install_csrf']) ?>">
                    <h3>📊 ডাটাবেস কানেকশন</h3>
                    <p class="text-muted">আপনার MySQL ডাটাবেস তথ্য দিন (cPanel থেকে পাবেন)।</p>
                    <div class="form-group">
                        <label>হোস্ট</label>
                        <input type="text" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label>ডাটাবেস নাম *</label>
                        <input type="text" name="db_name" placeholder="u123456_ai_office" required>
                    </div>
                    <div class="form-group">
                        <label>ইউজারনেম *</label>
                        <input type="text" name="db_user" placeholder="u123456_admin" required>
                    </div>
                    <div class="form-group">
                        <label>পাসওয়ার্ড</label>
                        <input type="password" name="db_pass" placeholder="ডাটাবেস পাসওয়ার্ড">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">কানেকশন টেস্ট করুন →</button>
                </form>

            <?php elseif ($step === 3): ?>
                <!-- ধাপ ২: অ্যাডমিন সেটআপ -->
                <form method="POST" class="login-form">
                    <input type="hidden" name="step" value="4">
                    <input type="hidden" name="install_csrf" value="<?= htmlspecialchars($_SESSION['install_csrf']) ?>">
                    <h3>👤 অ্যাডমিন অ্যাকাউন্ট</h3>
                    <p class="text-muted">এই তথ্য দিয়ে আপনি লগইন করবেন।</p>
                    <div class="form-group">
                        <label>ইউজারনেম</label>
                        <input type="text" name="admin_user" value="admin" required>
                    </div>
                    <div class="form-group">
                        <label>পাসওয়ার্ড *</label>
                        <input type="password" name="admin_pass" placeholder="কমপক্ষে ৬ অক্ষর" required>
                    </div>
                    <div class="form-group">
                        <label>পাসওয়ার্ড নিশ্চিত করুন *</label>
                        <input type="password" name="admin_pass2" required>
                    </div>
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="seed_demo" value="1" checked>
                            🎮 ডেমো ডাটা যোগ করুন (পরীক্ষার জন্য)
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">ইনস্টল করুন 🚀</button>
                </form>

            <?php elseif ($step === 5): ?>
                <!-- সফল -->
                <div class="install-success">
                    <div class="success-icon">🎉</div>
                    <h2>ইনস্টলেশন সম্পন্ন!</h2>
                    <p>AI Office সফলভাবে ইনস্টল হয়েছে।</p>
                    <div class="install-steps">
                        <h4>পরবর্তী পদক্ষেপ:</h4>
                        <ol>
                            <li>লগইন করুন</li>
                            <li>সেটিংসে গিয়ে Groq API কি দিন (ফ্রি: <a href="https://console.groq.com" target="_blank">console.groq.com</a>)</li>
                            <li>WooCommerce API কি দিন (config.php ফাইলে)</li>
                            <li>SMTP সেটআপ করুন (ইমেইল পাঠাতে)</li>
                            <li>প্রথম পণ্য ইম্পোর্ট করুন!</li>
                        </ol>
                    </div>
                    <a href="login.php" class="btn btn-primary btn-block">লগইন করুন →</a>
                    <p class="text-muted" style="margin-top:16px;">⚠️ নিরাপত্তার জন্য install.php ডিলিট করুন।</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
