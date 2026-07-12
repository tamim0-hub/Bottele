<?php
/**
 * lib/bootstrap.php — অ্যাপ বুটস্ট্র্যাপ
 * Loads config, all libs, returns instances.
 */

// Error reporting — production তে বন্ধ করুন
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// PHP 8.0+ polyfills for shared hosting that might have PHP 7.4
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return strpos($haystack, $needle) === 0;
    }
}

// Timezone
if (defined('APP_TIMEZONE')) {
    date_default_timezone_set(APP_TIMEZONE);
} else {
    date_default_timezone_set('Asia/Dhaka');
}

// Config ফাইল চেক
if (!file_exists(__DIR__ . '/../config.php')) {
    // ইনস্টলারে রিডাইরেক্ট (login.php ও api/auth.php ছাড়া)
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!in_array($script, ['install.php', 'login.php']) && strpos($script, 'auth.php') === false) {
        // সঠিক পাথ হিসাব করুন — api/ সাবডিরেক্টরি থেকেও কাজ করবে
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        // api/ সাবডিরেক্টরি থেকে কল হলে এক লেভেল উপরে যান
        $basePath = preg_replace('#/api$#', '', $scriptDir);
        $redirect = ($basePath ? $basePath : '') . '/install.php';
        header('Location: ' . $redirect);
        exit;
    }
    // কনফিগ ছাড়া লাইব্রেরি লোড করবেন না
    return;
}

require_once __DIR__ . '/../config.php';

// লাইব্রেরি লোড
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/groq.php';
require_once __DIR__ . '/woo.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/agents.php';
require_once __DIR__ . '/cron.php';
require_once __DIR__ . '/demo.php';

// ইনস্ট্যান্স তৈরি
$db     = new DB();
$auth   = new Auth($db);
$groq   = new Groq();
$woo    = new Woo();
$mailer = new Mailer();
$agents = new Agents($groq, $woo, $db, $mailer);
$cron   = new Cron($db, $agents, $mailer);

/**
 * লাইসেন্স চেক — রিসেলার হুক
 * @return array{valid:bool, message?:string}
 */
function checkLicense(): array {
    if (!defined('LICENSE_KEY') || empty(LICENSE_KEY)) {
        return ['valid' => true, 'message' => 'লাইসেন্স চেক নেই (ব্যক্তিগত ব্যবহার)।'];
    }

    // এখানে রিসেলারের লাইসেন্স সার্ভারে API কল করতে পারেন
    // উদাহরণ:
    // $ch = curl_init('https://your-license-server.com/api/verify');
    // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['key' => LICENSE_KEY, 'domain' => $_SERVER['HTTP_HOST']]));
    // ...

    // আপাতত সব কি গ্রহণ করুন
    return ['valid' => true, 'message' => 'লাইসেন্স সক্রিয়।'];
}
