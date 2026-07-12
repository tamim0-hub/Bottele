<?php
/**
 * lib/agents.php — ১০টি AI এজেন্ট
 * Each agent is a method. All return string output.
 * Leader orchestrates the rest.
 */

class Agents {
    private Groq $groq;
    private Woo $woo;
    private DB $db;
    private Mailer $mailer;

    public function __construct(Groq $groq, Woo $woo, DB $db, Mailer $mailer) {
        $this->groq   = $groq;
        $this->woo    = $woo;
        $this->db     = $db;
        $this->mailer = $mailer;
    }

    /**
     * এজেন্ট রানার — এজেন্ট নাম দিলে সে মেথড কল হয়
     * @return string output
     */
    public function run(string $agent, array $input = []): string {
        $map = [
            'leader'          => 'leader',
            'product_import'  => 'productImport',
            'price'           => 'price',
            'inventory'       => 'inventory',
            'cart_recovery'   => 'cartRecovery',
            'social'          => 'social',
            'seo'             => 'seo',
            'content'         => 'content',
            'customer_reply'  => 'customerReply',
            'order_prep'      => 'orderPrep',
        ];

        $method = $map[$agent] ?? null;
        if (!$method || !method_exists($this, $method)) {
            return "❌ অজানা এজেন্ট: $agent";
        }

        // স্টেট → working (শুধু তখনই যখন idle)
        $currentState = $this->getAgentState($agent);
        if ($currentState === 'working') {
            // আগের রান আটকে আছে কিনা চেক — ৫ মিনিটের বেশি হলে রিসেট
            $this->resetStuckAgent($agent);
            if ($this->getAgentState($agent) === 'working') {
                return "⚠️ {$agent} এজেন্ট ইতিমধ্যে চলছে। কিছুক্ষণ পর আবার চেষ্টা করুন।";
            }
        }
        $this->db->setAgentRunning($agent, true);

        try {
            $output = $this->$method($input);
            $this->db->setAgentState($agent, 'idle', $output);
            $this->db->setAgentRunning($agent, false);
            $this->db->addLog($agent, $method, json_encode($input, JSON_UNESCAPED_UNICODE), mb_substr($output, 0, 500), 'success');
            return $output;
        } catch (Exception $e) {
            $errMsg = 'ত্রুটি: ' . $e->getMessage();
            $this->db->setAgentState($agent, 'error', $errMsg);
            $this->db->setAgentRunning($agent, false);
            $this->db->addLog($agent, $method, json_encode($input, JSON_UNESCAPED_UNICODE), $errMsg, 'error');
            return $errMsg;
        }
    }

    /**
     * এজেন্টের বর্তমান স্টেট পান
     */
    private function getAgentState(string $agent): string {
        $states = $this->db->getAgentStates();
        foreach ($states as $s) {
            if ($s['agent'] === $agent) return $s['state'];
        }
        return 'idle';
    }

    /**
     * আটকে থাকা এজেন্ট রিসেট করুন (৫+ মিনিট working)
     */
    private function resetStuckAgent(string $agent): void {
        $states = $this->db->getAgentStates();
        foreach ($states as $s) {
            if ($s['agent'] === $agent && $s['state'] === 'working') {
                $updated = strtotime($s['updated_at']);
                if ($updated && (time() - $updated) > 300) {
                    $this->db->setAgentState($agent, 'idle', 'স্বয়ংক্রিয়ভাবে রিসেট (আটকে ছিল)');
                    $this->db->setAgentRunning($agent, false);
                }
            }
        }
    }

    // ────────────────────────────────────────────────────────────
    // ১. LEADER — অর্কেস্ট্রেশন
    // ────────────────────────────────────────────────────────────
    private function leader(array $input): string {
        $states = $this->db->getAgentStates();
        $stats  = $this->db->getStats();
        $pending = (int)($stats['pending_orders'] ?? 0);
        $activeCarts = (int)($stats['active_carts'] ?? 0);

        $summary = "📊 অফিস রিপোর্ট:\n\n";
        foreach ($states as $s) {
            $emoji = $s['state'] === 'idle' ? '✅' : ($s['state'] === 'working' ? '🔄' : '❌');
            $lastRun = $s['last_run'] ? $this->timeAgo($s['last_run']) : 'কখনো না';
            $summary .= "{$emoji} {$s['agent']}: {$s['state']} (শেষ রান: {$lastRun}, মোট: {$s['run_count']})\n";
        }

        $summary .= "\n📦 পেন্ডিং অর্ডার: {$pending}\n";
        $summary .= "🛒 সক্রিয় কার্ট: {$activeCarts}\n";

        // AI-পাওয়া পরামর্শ
        $prompt = "তুমি একটি ড্রপশিপিং বিজনেসের AI ম্যানেজার। এই রিপোর্ট দেখে বাংলায় ছোট পরামর্শ দাও:\n\n{$summary}";
        $advice = $this->groq->prompt(
            'তুমি একজন ড্রপশিপিং বিজনেস কোচ। বাংলায় উত্তর দাও।',
            $prompt,
            512
        );

        return $summary . "\n💡 পরামর্শ:\n" . $advice;
    }

    // ────────────────────────────────────────────────────────────
    // ২. PRODUCT IMPORT — BusinessKoro → Groq → WooCommerce
    // ────────────────────────────────────────────────────────────
    private function productImport(array $input): string {
        $name      = $input['name'] ?? '';
        $wholesale = $input['wholesale'] ?? 0;
        $category  = $input['category'] ?? 'সাধারণ';
        $imageUrl  = $input['image_url'] ?? '';
        $margin    = (float)($this->db->getSetting('profit_margin', '30'));

        if (empty($name)) {
            return '❌ পণ্যের নাম দিন।';
        }

        // Groq-কে description + SEO লিখতে বলুন
        $systemPrompt = 'তুমি একজন ই-কমার্স কপিরাইটার। বাংলায় পণ্যের বিবরণ, SEO title, meta description লিখো। JSON ফরম্যাটে দাও: {"description":"...","seo_title":"...","meta_description":"...","tags":["..."]}';
        $userPrompt = "পণ্যের নাম: {$name}\nক্যাটেগরি: {$category}\nহোলসেল মূল্য: ৳{$wholesale}";

        $aiResponse = $this->groq->prompt($systemPrompt, $userPrompt, 1024);

        // JSON পার্স করার চেষ্টা
        $descData = $this->parseJsonResponse($aiResponse);
        $description = $descData['description'] ?? $aiResponse;
        $seoTitle    = $descData['seo_title'] ?? $name;
        $metaDesc    = $descData['meta_description'] ?? '';
        $tags        = $descData['tags'] ?? [];

        // মার্জিন সহ দাম হিসাব
        $retailPrice = $wholesale > 0 ? round($wholesale * (1 + $margin / 100), 0) : 0;

        // WooCommerce-তে পণ্য তৈরি
        $productData = [
            'name'              => $name,
            'type'              => 'simple',
            'regular_price'     => (string)$retailPrice,
            'description'       => $description,
            'short_description' => mb_substr($description, 0, 200),
            'categories'        => [['name' => $category]],
            'meta_data'         => [
                ['key' => '_seo_title', 'value' => $seoTitle],
                ['key' => '_meta_description', 'value' => $metaDesc],
            ],
        ];

        if ($imageUrl) {
            $productData['images'] = [['src' => $imageUrl]];
        }

        if (!empty($tags)) {
            $productData['tags'] = array_map(fn($t) => ['name' => $t], $tags);
        }

        $result = $this->woo->createProduct($productData);

        if ($result['success'] ?? false) {
            $id = $result['id'];
            return "✅ পণ্য তৈরি হয়েছে!\n\n📦 {$name}\n💰 হোলসেল: ৳{$wholesale} → রিটেইল: ৳{$retailPrice} ({$margin}% মার্জিন)\n📋 WooCommerce ID: {$id}\n\n📝 বিবরণ:\n{$description}\n\n🔍 SEO: {$seoTitle}\n📌 Meta: {$metaDesc}";
        }

        return "⚠️ পণ্য বিবরণ তৈরি হয়েছে কিন্তু WooCommerce-তে সেভ হয়নি।\n\n📝 বিবরণ:\n{$description}\n\n🔍 SEO: {$seoTitle}\n\nWooCommerce ত্রুটি: " . ($result['error'] ?? 'সংযোগ নেই');
    }

    // ────────────────────────────────────────────────────────────
    // ৩. PRICE — মার্জিন-ভিত্তিক রিপ্রাইস
    // ────────────────────────────────────────────────────────────
    private function price(array $input): string {
        $margin = (float)($this->db->getSetting('profit_margin', '30'));
        $products = $this->woo->getProducts();

        if (empty($products)) {
            return '⚠️ কোনো পণ্য পাওয়া যায়নি। WooCommerce কানেকশন চেক করুন।';
        }

        $updated = 0;
        $details = '';

        foreach ($products as $p) {
            $currentPrice = (float)($p['price'] ?? 0);
            if ($currentPrice <= 0) continue;

            // AI-পাওয়া কম্পিটিটিভ প্রাইসিং সুপারিশ
            $advice = $this->groq->prompt(
                'তুমি একজন প্রাইসিং বিশেষজ্ঞ। বাংলায় উত্তর দাও। শুধু JSON: {"suggested_price":number,"reason":"string"}',
                "পণ্য: {$p['name']}, বর্তমান দাম: ৳{$currentPrice}, টার্গেট মার্জিন: {$margin}%, মার্কেট: বাংলাদেশ",
                256
            );

            $priceData = $this->parseJsonResponse($advice);
            $suggested = (float)($priceData['suggested_price'] ?? $currentPrice);
            $reason    = $priceData['reason'] ?? 'মার্জিন অনুযায়ী';

            // WooCommerce-তে আপডেট
            if ($suggested > 0 && abs($suggested - $currentPrice) > 1) {
                $result = $this->woo->updateProduct((int)$p['id'], ['regular_price' => (string)round($suggested)]);
                if ($result['success'] ?? false) {
                    $updated++;
                    $details .= "📦 {$p['name']}: ৳{$currentPrice} → ৳" . round($suggested) . " ({$reason})\n";
                }
            }
        }

        if ($updated === 0) {
            return "✅ সব পণ্যের দাম ঠিক আছে। কোনো পরিবর্তন লাগবে না।";
        }

        return "💰 {$updated}টি পণ্যের দাম আপডেট হয়েছে:\n\n{$details}";
    }

    // ────────────────────────────────────────────────────────────
    // ৪. INVENTORY — out-of-stock পণ্য unpublish
    // ────────────────────────────────────────────────────────────
    private function inventory(array $input): string {
        $products = $this->woo->getProducts();

        if (empty($products)) {
            return '⚠️ পণ্য তালিকা পাওয়া যায়নি।';
        }

        $unpublished = 0;
        $republished = 0;
        $details = '';

        foreach ($products as $p) {
            $stockStatus = $p['stock_status'] ?? 'instock';
            $currentStatus = $p['status'] ?? 'publish';

            if ($stockStatus === 'outofstock' && $currentStatus === 'publish') {
                // Out of stock — unpublish
                $this->woo->updateProduct((int)$p['id'], ['status' => 'draft']);
                $unpublished++;
                $details .= "❌ {$p['name']} — out of stock, আনপাবলিশ করা হয়েছে\n";
            } elseif ($stockStatus === 'instock' && $currentStatus === 'draft') {
                // Back in stock — republish
                $this->woo->updateProduct((int)$p['id'], ['status' => 'publish']);
                $republished++;
                $details .= "✅ {$p['name']} — স্টকে ফিরেছে, পাবলিশ করা হয়েছে\n";
            }
        }

        if ($unpublished === 0 && $republished === 0) {
            return "✅ সব পণ্যের ইনভেন্টরি ঠিক আছে। কোনো পরিবর্তন লাগবে না।";
        }

        $msg = '';
        if ($unpublished > 0) $msg .= "❌ {$unpublished}টি পণ্য আনপাবলিশ (out of stock)\n";
        if ($republished > 0) $msg .= "✅ {$republished}টি পণ্য রিপাবলিশ (ফিরে এসেছে)\n";
        return $msg . "\n" . $details;
    }

    // ────────────────────────────────────────────────────────────
    // ৫. CART RECOVERY — ৩-ধাপ বাংলা ইমেইল সিকোয়েন্স
    // ────────────────────────────────────────────────────────────
    private function cartRecovery(array $input): string {
        $action = $input['action'] ?? 'generate';

        if ($action === 'send_pending') {
            return $this->cartRecoverySendPending();
        }

        if ($action === 'add') {
            // নতুন কার্ট যোগ
            $email = $input['email'] ?? '';
            $name  = $input['name'] ?? '';
            $cart  = $input['cart_data'] ?? '{}';

            if (empty($email)) return '❌ ইমেইল দিন।';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return '❌ সঠিক ইমেইল দিন।';

            try {
                $stmt = $this->db->pdo->prepare(
                    'INSERT INTO cart_recovery (customer_email, customer_name, cart_data, step) VALUES (?, ?, ?, 0)'
                );
                $stmt->execute([$email, $name, is_string($cart) ? $cart : json_encode($cart)]);
                return "✅ কার্ট রিকভারি তালিকায় যোগ হয়েছে: {$email}";
            } catch (Exception $e) {
                return "❌ কার্ট যোগে ত্রুটি: " . $e->getMessage();
            }
        }

        // ডিফল্ট: AI দিয়ে ইমেইল ড্রাফট তৈরি
        $step = (int)($input['step'] ?? 0);
        $email = $input['email'] ?? 'customer@example.com';
        $name  = $input['name'] ?? 'গ্রাহক';
        $storeName = $this->db->getSetting('store_name', 'আমার স্টোর');

        $stepNames = ['১ম রিমাইন্ডার (১ ঘন্টা পর)', '২য় রিমাইন্ডার (২৪ ঘন্টা পর)', '৩য় রিমাইন্ডার (৭২ ঘন্টা পর)'];

        $draft = $this->groq->prompt(
            'তুমি একজন ইমেইল মার্কেটিং বিশেষজ্ঞ। বাংলায় কার্ট রিকভারি ইমেইল লিখো। HTML ফরম্যাটে দাও।',
            "স্টোর: {$storeName}\nগ্রাহক: {$name}\nধাপ: " . ($stepNames[$step] ?? 'রিমাইন্ডার'),
            1024
        );

        // ইমেইল পাঠানোর অফার
        return "📧 কার্ট রিকভারি ইমেইল ড্রাফট তৈরি:\n\nধাপ: {$stepNames[$step]}\nপ্রাপক: {$email}\n\n---\n{$draft}\n---\n\n💡 ইমেইল পাঠাতে 'send' অ্যাকশন ব্যবহার করুন অথবা ক্রন জব সেটআপ করুন।";
    }

    /**
     * পেন্ডিং কার্ট রিকভারি ইমেইল পাঠান
     */
    private function cartRecoverySendPending(): string {
        if (!$this->db->isConnected()) return '❌ ডাটাবেস কানেকশন নেই।';

        $storeName = $this->db->getSetting('store_name', 'আমার স্টোর');
        $step1h = (int)$this->db->getSetting('cart_step1_hours', '1');
        $step2h = (int)$this->db->getSetting('cart_step2_hours', '24');
        $step3h = (int)$this->db->getSetting('cart_step3_hours', '72');

        $intervals = [$step1h, $step2h, $step3h];

        // যে কার্টগুলো এখন ইমেইল পাঠানোর সময় হয়েছে
        $stmt = $this->db->pdo->query('SELECT * FROM cart_recovery WHERE purchased = 0 AND step < 3 ORDER BY created_at ASC LIMIT 100');
        $carts = $stmt->fetchAll();

        $sent = 0;
        $errors = 0;
        $details = '';

        foreach ($carts as $cart) {
            $step = (int)$cart['step'];
            $hoursSince = (time() - strtotime($cart['created_at'])) / 3600;
            $lastSent = $cart['last_sent'] ? (time() - strtotime($cart['last_sent'])) / 3600 : $hoursSince;

            // এই ধাপের জন্য যথেষ্ট সময় হয়েছে কিনা
            $threshold = $intervals[$step] ?? 999999;
            if ($lastSent < $threshold && $step > 0) continue;
            if ($hoursSince < $threshold && $step === 0) {
                // প্রথম ইমেইলের জন্য created_at থেকে হিসাব
                continue;
            }

            // কুপন কোড
            $coupons = ['WELCOME10', 'COMEBACK15', 'LASTCHANCE20'];
            $coupon = $coupons[$step] ?? '';

            // ইমেইল পাঠান
            $result = $this->mailer->sendCartRecovery(
                $cart['customer_email'],
                $cart['customer_name'] ?: 'গ্রাহক',
                $step,
                $storeName,
                $coupon
            );

            if ($result['success'] ?? false) {
                $sent++;
                // ধাপ আপডেট
                $upd = $this->db->pdo->prepare(
                    'UPDATE cart_recovery SET step = step + 1, last_sent = NOW() WHERE id = ?'
                );
                $upd->execute([$cart['id']]);
                $details .= "✅ {$cart['customer_email']} — ধাপ " . ($step + 1) . " পাঠানো হয়েছে\n";
            } else {
                $errors++;
                $details .= "❌ {$cart['customer_email']} — ত্রুটি: " . ($result['error'] ?? 'অজানা') . "\n";
            }
        }

        if ($sent === 0 && $errors === 0) {
            return "✅ এখন কোনো কার্ট রিকভারি ইমেইল পাঠানোর সময় হয়নি।";
        }

        return "📧 কার্ট রিকভারি রিপোর্ট:\nপাঠানো: {$sent}, ত্রুটি: {$errors}\n\n{$details}";
    }

    // ────────────────────────────────────────────────────────────
    // ৬. SOCIAL — পোস্ট/ক্যাপশন জেনারেটর
    // ────────────────────────────────────────────────────────────
    private function social(array $input): string {
        $productName = $input['product_name'] ?? 'আমার পণ্য';
        $platforms   = $input['platforms'] ?? $this->db->getSetting('social_platforms', 'facebook,instagram');
        $price       = $input['price'] ?? '';
        $storeName   = $this->db->getSetting('store_name', 'আমার স্টোর');

        $output = $this->groq->prompt(
            'তুমি একজন সোশ্যাল মিডিয়া মার্কেটার। বাংলায় পোস্ট ও ক্যাপশন লিখো। প্রতিটি প্ল্যাটফর্মের জন্য আলাদা দাও।',
            "পণ্য: {$productName}\nদাম: ৳{$price}\nপ্ল্যাটফর্ম: {$platforms}\nস্টোর: {$storeName}\n\nপ্রতিটি প্ল্যাটফর্মের জন্য পোস্ট + হ্যাশট্যাগ দাও।",
            1024
        );

        return "📱 সোশ্যাল মিডিয়া পোস্ট তৈরি:\n\n📦 পণ্য: {$productName}\n\n{$output}";
    }

    // ────────────────────────────────────────────────────────────
    // ৭. SEO — রিয়েল সার্ভার-সাইড ক্রল + অডিট
    // ────────────────────────────────────────────────────────────
    private function seo(array $input): string {
        $url = $input['url'] ?? '';
        if (empty($url)) {
            $url = defined('WOO_URL') ? WOO_URL : '';
        }

        if (empty($url)) {
            return '❌ অডিট করার জন্য URL দিন অথবা WooCommerce URL কনফিগ করুন।';
        }

        // সার্ভার-সাইড ক্রল
        $html = $this->fetchUrl($url);

        if (empty($html)) {
            return '❌ পেজ ফেচ করা যায়নি: ' . $url;
        }

        // হিউরিস্টিক অডিট
        $audit = $this->auditHtml($html, $url);

        // AI-পাওয়া সুপারিশ
        $aiRec = $this->groq->prompt(
            'তুমি একজন SEO বিশেষজ্ঞ। বাংলায় উত্তর দাও।',
            "এই SEO অডিট দেখে সুপারিশ দাও:\n\n" . json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            512
        );

        $score = $audit['score'];
        $emoji = $score >= 80 ? '🟢' : ($score >= 50 ? '🟡' : '🔴');

        $report = "🔍 SEO অডিট রিপোর্ট\n\n";
        $report .= "{$emoji} স্কোর: {$score}/100\n\n";
        $report .= "📊 বিস্তারিত:\n";

        foreach ($audit['checks'] as $check) {
            $icon = $check['pass'] ? '✅' : '❌';
            $report .= "{$icon} {$check['name']}: {$check['value']}\n";
            if (!empty($check['fix'])) {
                $report .= "   💡 {$check['fix']}\n";
            }
        }

        $report .= "\n🤖 AI সুপারিশ:\n{$aiRec}";

        return $report;
    }

    /**
     * HTML অডিট — রিয়েল ক্রল ডাটা থেকে
     */
    private function auditHtml(string $html, string $url): array {
        $score = 0;
        $checks = [];

        // Title tag
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(html_entity_decode($m[1]));
        }
        $titleLen = mb_strlen($title);
        $titlePass = $titleLen >= 10 && $titleLen <= 60;
        if ($titlePass) $score += 15;
        $checks[] = [
            'name' => 'Title Tag',
            'pass' => $titlePass,
            'value' => $titleLen > 0 ? "\"{$title}\" ({$titleLen} অক্ষর)" : 'পাওয়া যায়নি',
            'fix' => $titleLen === 0 ? 'Title tag যোগ করুন (১০-৬০ অক্ষর)' : ($titleLen > 60 ? 'Title ৬০ অক্ষরের মধ্যে করুন' : ''),
        ];

        // Meta description
        $metaDesc = '';
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $html, $m)) {
            $metaDesc = trim(html_entity_decode($m[1]));
        }
        $metaLen = mb_strlen($metaDesc);
        $metaPass = $metaLen >= 50 && $metaLen <= 160;
        if ($metaPass) $score += 15;
        $checks[] = [
            'name' => 'Meta Description',
            'pass' => $metaPass,
            'value' => $metaLen > 0 ? "{$metaLen} অক্ষর" : 'পাওয়া যায়নি',
            'fix' => $metaLen === 0 ? 'Meta description যোগ করুন (৫০-১৬০ অক্ষর)' : ($metaLen > 160 ? '১৬০ অক্ষরের মধ্যে করুন' : ($metaLen < 50 ? '৫০ অক্ষরের বেশি লিখুন' : '')),
        ];

        // H1 tag
        $h1Count = preg_match_all('/<h1[^>]*>/i', $html);
        $h1Pass = $h1Count === 1;
        if ($h1Pass) $score += 10;
        $checks[] = [
            'name' => 'H1 Tag',
            'pass' => $h1Pass,
            'value' => "{$h1Count}টি H1",
            'fix' => $h1Count === 0 ? 'একটি H1 ট্যাগ যোগ করুন' : ($h1Count > 1 ? 'শুধু একটি H1 রাখুন' : ''),
        ];

        // Image alt tags
        $imgTotal = preg_match_all('/<img/i', $html);
        $imgWithAlt = preg_match_all('/<img[^>]+alt=["\'][^"\']+["\']/i', $html);
        $imgMissing = $imgTotal - $imgWithAlt;
        $imgPass = $imgMissing === 0 || $imgTotal === 0;
        if ($imgPass) $score += 10;
        $checks[] = [
            'name' => 'Image Alt Tags',
            'pass' => $imgPass,
            'value' => $imgTotal > 0 ? "{$imgWithAlt}/{$imgTotal} ছবিতে alt আছে" : 'কোনো ছবি নেই',
            'fix' => $imgMissing > 0 ? "{$imgMissing}টি ছবিতে alt ট্যাগ যোগ করুন" : '',
        ];

        // Internal links
        $internalLinks = 0;
        $domain = parse_url($url, PHP_URL_HOST);
        if (preg_match_all('/<a\s+href=["\']([^"\']+)["\']/i', $html, $links)) {
            foreach ($links[1] as $link) {
                if (str_contains($link, $domain) || str_starts_with($link, '/') || str_starts_with($link, '#')) {
                    $internalLinks++;
                }
            }
        }
        $linkPass = $internalLinks >= 3;
        if ($linkPass) $score += 10;
        $checks[] = [
            'name' => 'Internal Links',
            'pass' => $linkPass,
            'value' => "{$internalLinks}টি",
            'fix' => $internalLinks < 3 ? 'আরো ইন্টারনাল লিংক যোগ করুন (কমপক্ষে ৩টি)' : '',
        ];

        // Canonical
        $hasCanonical = (bool)preg_match('/<link[^>]+rel=["\']canonical["\']/i', $html);
        if ($hasCanonical) $score += 5;
        $checks[] = [
            'name' => 'Canonical URL',
            'pass' => $hasCanonical,
            'value' => $hasCanonical ? 'আছে' : 'নেই',
            'fix' => $hasCanonical ? '' : 'Canonical link যোগ করুন',
        ];

        // Open Graph
        $hasOg = (bool)preg_match('/<meta[^>]+property=["\']og:/i', $html);
        if ($hasOg) $score += 10;
        $checks[] = [
            'name' => 'Open Graph Tags',
            'pass' => $hasOg,
            'value' => $hasOg ? 'আছে' : 'নেই',
            'fix' => $hasOg ? '' : 'OG ট্যাগ যোগ করুন (Facebook/LinkedIn শেয়ারের জন্য)',
        ];

        // Viewport (mobile)
        $hasViewport = (bool)preg_match('/<meta[^>]+name=["\']viewport["\']/i', $html);
        if ($hasViewport) $score += 10;
        $checks[] = [
            'name' => 'Mobile Viewport',
            'pass' => $hasViewport,
            'value' => $hasViewport ? 'আছে' : 'নেই',
            'fix' => $hasViewport ? '' : 'Viewport meta tag যোগ করুন',
        ];

        // HTTPS
        $httpsPass = str_starts_with($url, 'https://');
        if ($httpsPass) $score += 10;
        $checks[] = [
            'name' => 'HTTPS',
            'pass' => $httpsPass,
            'value' => $httpsPass ? 'আছে ✅' : 'নেই ❌',
            'fix' => $httpsPass ? '' : 'SSL সার্টিফিকেট ইনস্টল করুন',
        ];

        // Content length (readability proxy)
        $textLen = mb_strlen(strip_tags($html));
        $contentPass = $textLen >= 300;
        if ($contentPass) $score += 5;
        $checks[] = [
            'name' => 'Content Length',
            'pass' => $contentPass,
            'value' => "{$textLen} অক্ষর",
            'fix' => $textLen < 300 ? 'কনটেন্ট বাড়ান (কমপক্ষে ৩০০ অক্ষর)' : '',
        ];

        return ['score' => min($score, 100), 'checks' => $checks, 'url' => $url];
    }

    /**
     * URL ফেচ — সার্ভার-সাইড ক্রল
     */
    private function fetchUrl(string $url): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'AI-Office-SEO-Bot/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200 ? (string)$html : '';
    }

    // ────────────────────────────────────────────────────────────
    // ৮. CONTENT — ভিডিও/ব্লগ স্ক্রিপ্ট
    // ────────────────────────────────────────────────────────────
    private function content(array $input): string {
        $topic  = $input['topic'] ?? 'পণ্য পরিচিতি';
        $type   = $input['type'] ?? 'video'; // video, blog
        $product = $input['product_name'] ?? '';

        $systemPrompt = $type === 'video'
            ? 'তুমি একজন ভিডিও স্ক্রিপ্ট লেখক। বাংলায় ছোট ভিডিও স্ক্রিপ্ট লিখো (৩০-৬০ সেকেন্ড)। হুক, সমস্যা, সমাধান, CTA ফরম্যাটে।'
            : ($type === 'blog'
                ? 'তুমি একজন ব্লগ লেখক। বাংলায় SEO-ফ্রেন্ডলি ব্লগ পোস্ট লিখো। H2, H3 সাবহেডিং ব্যবহার করো।'
                : 'তুমি একজন কনটেন্ট ক্রিয়েটর। বাংলায় লিখো।');

        $userPrompt = "বিষয়: {$topic}\nপণ্য: {$product}";
        $output = $this->groq->prompt($systemPrompt, $userPrompt, 1024);

        $typeLabel = $type === 'video' ? 'ভিডিও স্ক্রিপ্ট' : 'ব্লগ পোস্ট';
        return "📝 {$typeLabel} তৈরি:\n\nবিষয়: {$topic}\n\n---\n{$output}\n---";
    }

    // ────────────────────────────────────────────────────────────
    // ৯. CUSTOMER REPLY — রিপ্লাই ড্রাফট
    // ────────────────────────────────────────────────────────────
    private function customerReply(array $input): string {
        $message = $input['message'] ?? '';
        $customerName = $input['customer_name'] ?? 'গ্রাহক';
        $orderId = $input['order_id'] ?? '';
        $storeName = $this->db->getSetting('store_name', 'আমার স্টোর');

        if (empty($message)) {
            return '❌ কাস্টমারের মেসেজ দিন।';
        }

        $draft = $this->groq->prompt(
            "তুমি {$storeName}-এর কাস্টমার সাপোর্ট। বাংলায় ভদ্র, সাহায্যকারী রিপ্লাই লিখো।",
            "কাস্টমার: {$customerName}\nঅর্ডার: #{$orderId}\nমেসেজ: {$message}",
            512
        );

        return "💬 কাস্টমার রিপ্লাই ড্রাফট:\n\n👤 কাস্টমার: {$customerName}\n📨 মেসেজ: {$message}\n\n---\n✉️ প্রস্তাবিত রিপ্লাই:\n\n{$draft}\n---\n\n💡 রিপ্লাই সম্পাদনা করে কাস্টমারকে পাঠান।";
    }

    // ────────────────────────────────────────────────────────────
    // ১০. ORDER PREP — WooCommerce অর্ডার BusinessKoro ফরম্যাট
    // ────────────────────────────────────────────────────────────
    private function orderPrep(array $input): string {
        $action = $input['action'] ?? 'pending';

        if ($action === 'mark_forwarded') {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) return '❌ অর্ডার ID দিন।';
            try {
                $stmt = $this->db->pdo->prepare('UPDATE agent_orders SET status = "forwarded", forwarded_at = NOW() WHERE id = ?');
                $stmt->execute([$id]);
                return "✅ অর্ডার #{$id} BusinessKoro-তে ফরওয়ার্ড হিসেবে চিহ্নিত।";
            } catch (Exception $e) {
                return "❌ আপডেট ত্রুটি: " . $e->getMessage();
            }
        }

        if (!$this->db->isConnected()) return '❌ ডাটাবেস কানেকশন নেই।';

        // পেন্ডিং অর্ডার ফরম্যাট করুন
        $orders = $this->woo->getOrders();

        if (empty($orders)) {
            return '⚠️ কোনো অর্ডার পাওয়া যায়নি।';
        }

        $pending = array_filter($orders, fn($o) => in_array($o['status'] ?? '', ['processing', 'pending']));
        if (empty($pending)) {
            return '✅ সব অর্ডার ফরওয়ার্ড হয়েছে! নতুন অর্ডার নেই।';
        }

        $bkId = $this->db->getSetting('bk_id', '');
        $bkPhone = $this->db->getSetting('bk_phone', '');

        $output = "📦 ফরওয়ার্ডের জন্য প্রস্তুত অর্ডার:\n\n";

        foreach ($pending as $o) {
            $id = $o['id'];
            $billing = $o['billing'] ?? [];
            $name = ($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '');
            $phone = $billing['phone'] ?? '';
            $address = ($billing['address_1'] ?? '') . ', ' . ($billing['city'] ?? '');
            $total = $o['total'] ?? '0';

            // পণ্যের তালিকা
            $items = [];
            foreach ($o['line_items'] ?? [] as $item) {
                $items[] = ($item['name'] ?? '') . ' x' . ($item['quantity'] ?? 1);
            }
            $itemsStr = implode(', ', $items);

            // BusinessKoro ফরম্যাট — কপি-পেস্ট ফ্রেন্ডলি
            $formatted = "━━━━━━━━━━━━━━━━━━━━━\n";
            $formatted .= "📋 অর্ডার #{$id}\n";
            $formatted .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $formatted .= "👤 গ্রাহক: {$name}\n";
            $formatted .= "📱 ফোন: {$phone}\n";
            $formatted .= "🏠 ঠিকানা: {$address}\n";
            $formatted .= "📦 পণ্য: {$itemsStr}\n";
            $formatted .= "💰 মোট: ৳{$total}\n";
            $formatted .= "💵 পেমেন্ট: ক্যাশ অন ডেলিভারি\n";
            if ($bkId) $formatted .= "🏢 BK ID: {$bkId}\n";
            if ($bkPhone) $formatted .= "📞 BK ফোন: {$bkPhone}\n";
            $formatted .= "━━━━━━━━━━━━━━━━━━━━━\n";

            $output .= $formatted . "\n";

            // লোকাল DB-তে সেভ
            try {
                $stmt = $this->db->pdo->prepare(
                    'INSERT INTO agent_orders (woo_order_id, customer_name, customer_email, total, status, bk_formatted)
                     VALUES (?, ?, ?, ?, "pending", ?)
                     ON DUPLICATE KEY UPDATE bk_formatted = ?'
                );
                $stmt->execute([
                    $id, $name, $billing['email'] ?? '',
                    $total, $formatted, $formatted
                ]);
            } catch (Exception $e) {
                // সেভ ব্যর্ত হলেও আউটপুট দেখান
            }
        }

        $output .= "\n⚠️ BusinessKoro-তে ম্যানুয়ালি এন্ট্রি করুন (তাদের API নেই)।";
        $output .= "\n✅ ফরওয়ার্ড করার পর 'mark_forwarded' অ্যাকশন দিন।";

        return $output;
    }

    // ────────────────────────────────────────────────────────────
    // হেল্পার মেথড
    // ────────────────────────────────────────────────────────────

    /**
     * JSON রেসপন্স পার্স — AI থেকে আসা JSON বের করুন
     */
    private function parseJsonResponse(string $text): array {
        // JSON ব্লক খুঁজুন
        if (preg_match('/```json\s*(.*?)```/s', $text, $m)) {
            $json = $m[1];
        } elseif (preg_match('/\{.*\}/s', $text, $m)) {
            $json = $m[0];
        } else {
            return [];
        }

        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * টাইম এগো — মানুষের পড়ার সুবিধায়
     */
    private function timeAgo(string $datetime): string {
        $now = time();
        $then = strtotime($datetime);
        $diff = $now - $then;

        if ($diff < 60) return 'এইমাত্র';
        if ($diff < 3600) return floor($diff / 60) . ' মিনিট আগে';
        if ($diff < 86400) return floor($diff / 3600) . ' ঘন্টা আগে';
        return floor($diff / 86400) . ' দিন আগে';
    }
}
