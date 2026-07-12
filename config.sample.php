<?php
/**
 * AI Office — Dropshipping Automation
 * কনফিগ টেমপ্লেট — এটা config.php হিসেবে কপি করুন এবং নিজের তথ্য দিন।
 * Copy this file to config.php and fill in your values.
 */

// ── ডাটাবেস / Database ──────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'ai_office');
define('DB_USER', 'root');
define('DB_PASS', '');

// ── অ্যাডমিন লগইন / Admin Login ────────────────────────────
// ইনস্টলার থেকে সেট করুন বা এখানে দিন। পাসওয়ার্ড bcrypt হ্যাশ হবে।
define('ADMIN_USER', 'admin');
// নিচের হ্যাশ হলো 'admin123' এর — প্রথম লগইনে পরিবর্তন করুন!
define('ADMIN_PASS_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

// ── Groq AI ──────────────────────────────────────────────────
// https://console.groq.com থেকে ফ্রি কি নিন। খালি রাখলে ডেমো মোড চলবে।
define('GROQ_API_KEY', '');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');

// ── WooCommerce REST API ─────────────────────────────────────
// WooCommerce > Settings > Advanced > REST API থেকে কি নিন।
define('WOO_URL', '');          // e.g. https://yourstore.com
define('WOO_CK', '');           // Consumer Key
define('WOO_CS', '');           // Consumer Secret

// ── SMTP (কার্ট রিকভারি ইমেইল পাঠাতে) ──────────────────────
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', '');        // e.g. noreply@yourstore.com
define('SMTP_FROM_NAME', 'AI Office');

// ── সাইট বেস URL ────────────────────────────────────────────
define('SITE_URL', '');         // e.g. https://aioffice.yourdomain.com

// ── লাইসেন্স / Licensing ─────────────────────────────────────
// রিসেলার হলে লাইসেন্স কি দিন। খালি = কোনো লাইসেন্স চেক নেই।
define('LICENSE_KEY', '');

// ── ডেমো মোড / Demo Mode ────────────────────────────────────
// true রাখলে ফেক ডাটা দেখাবে, Groq/Woo কি লাগবে না।
define('DEMO_MODE', false);

// ── টাইমজোন ────────────────────────────────────────────────
define('APP_TIMEZONE', 'Asia/Dhaka');
