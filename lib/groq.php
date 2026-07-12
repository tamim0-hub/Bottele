<?php
/**
 * lib/groq.php — Groq AI ক্লায়েন্ট (সার্ভার-সাইড)
 * Calls Groq API for LLM completions. Falls back to simulated responses if no key.
 */

class Groq {
    private string $apiKey;
    private string $model;
    private bool $demo;

    public function __construct() {
        $this->apiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
        $this->model  = defined('GROQ_MODEL') ? GROQ_MODEL : 'llama-3.3-70b-versatile';
        $this->demo   = (defined('DEMO_MODE') && DEMO_MODE) || empty($this->apiKey);
    }

    /**
     * চ্যাট কম্প্লিশন — messages অ্যারে পাঠান
     * @param array $messages [[role=>string, content=>string], ...]
     * @param int $maxTokens
     * @return string AI উত্তর
     */
    public function chat(array $messages, int $maxTokens = 1024): string {
        if ($this->demo) {
            return $this->simulated($messages);
        }

        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $payload = json_encode([
            'model'       => $this->model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => 0.7,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log("Groq cURL error: $err");
            return $this->simulated($messages);
        }

        if ($httpCode !== 200) {
            error_log("Groq HTTP $httpCode: $response");
            return $this->simulated($messages);
        }

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            return trim($data['choices'][0]['message']['content'] ?? $this->simulated($messages));
        } catch (Exception $e) {
            error_log("Groq parse error: " . $e->getMessage());
            return $this->simulated($messages);
        }
    }

    /**
     * সিম্পল সিঙ্গেল-প্রম্পট কল
     */
    public function prompt(string $systemPrompt, string $userPrompt, int $maxTokens = 1024): string {
        return $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ], $maxTokens);
    }

    /**
     * ডেমো / ফলব্যাক — কি না থাকলে সিম্পুলেটেড উত্তর
     */
    private function simulated(array $messages): string {
        $last = '';
        foreach (array_reverse($messages) as $m) {
            if ($m['role'] === 'user') {
                $last = mb_strtolower($m['content']);
                break;
            }
        }

        // কীওয়ার্ড-ভিত্তিক সিম্পুলেটেড উত্তর
        if (str_contains($last, 'পণ্য') || str_contains($last, 'product') || str_contains($last, 'description')) {
            return "✨ ডেমো মোড\n\nপণ্যের বিবরণ:\nএই পণ্যটি দৈনন্দিন জীবনে অত্যন্ত কার্যকরী। উচ্চ মানের উপকরণে তৈরি, দীর্ঘস্থায়ী এবং সাশ্রয়ী। এখনই অর্ডার করুন এবং দারুণ ছাড় উপভোগ করুন!\n\nSEO Title: সেরা মানের পণ্য - সাশ্রয়ী মূল্যে\nMeta: উচ্চ মানের পণ্য সরাসরি আপনার দোরগোড়ায়। দ্রুত ডেলিভারি, সেরা দাম।";
        }

        if (str_contains($last, 'মূল্য') || str_contains($last, 'price') || str_contains($last, 'pricing')) {
            return "💰 ডেমো মোড\n\nপ্রাইসিং রিকমেন্ডেশন:\n- হোলসেল মূল্য: ৳500\n- প্রস্তাবিত রিটেইল: ৳750 (50% মার্জিন)\n- কম্পিটিটিভ রেঞ্জ: ৳699-৳899\n\n💡 টিপ: শুরুতে ৳699 রাখুন, রিভিউ পেলে ৳849 করুন।";
        }

        if (str_contains($last, 'ইমেইল') || str_contains($last, 'email') || str_contains($last, 'cart') || str_contains($last, 'কার্ট')) {
            return "📧 ডেমো মোড\n\nকার্ট রিকভারি ইমেইল:\n\nবিষয়: আপনার কার্ট আপনার জন্য অপেক্ষা করছে! 🛒\n\nহ্যালো {name},\n\nআপনি কিছু দারুণ পণ্য কার্টে রেখে গেছেন! এখনই অর্ডার কমপ্লিট করুন এবং 10% ছাড় পান।\n\nকুপন কোড: COMEBACK10\n\nধন্যবাদান্তে,\nআমার স্টোর টিম";
        }

        if (str_contains($last, 'সোশ্যাল') || str_contains($last, 'social') || str_contains($last, 'post') || str_contains($last, 'ক্যাপশন')) {
            return "📱 ডেমো মোড\n\nFacebook Post:\n🔥 নতুন পণ্য এসেছে! এই দারুণ পণ্যটি আপনার দৈনন্দিন জীবনকে সহজ করবে। সীমিত সময়ের অফার - এখনই অর্ডার করুন!\n\n#Bangladesh #অনলাইনশপিং #সেরাদাম\n\nInstagram Caption:\n✨ নতুন আইটেম অ্যালার্ট! 💫\nদাম কম, মান বেশি। লিংক বায়োতে! 🔗\n.\n.\n.\n#বাংলাদেশ #শপিং #ডিল";
        }

        if (str_contains($last, 'seo') || str_contains($last, 'এসইও')) {
            return "🔍 ডেমো মোড\n\nSEO অডিট রিপোর্ট:\n- Title Tag: ✅ আছে (৬০ অক্ষরের মধ্যে)\n- Meta Description: ⚠️ দীর্ঘ (১৭০ অক্ষর — ১৬০ এ কমান)\n- H1: ✅ আছে\n- Images Alt: ❌ ৩টি ছবিতে alt ট্যাগ নেই\n- Page Speed: ⚠️ উন্নতি প্রয়োজন\n- Internal Links: ✅ ৫টি\n- Mobile Friendly: ✅\n\nস্কোর: ৬৫/১০০\n\nসুপারিশ: alt ট্যাগ যোগ করুন, মেটা ডেসক্রিপশন ছোট করুন, ছবি কম্প্রেস করুন।";
        }

        if (str_contains($last, 'অর্ডার') || str_contains($last, 'order') || str_contains($last, 'ফরওয়ার্ড')) {
            return "📦 ডেমো মোড\n\nঅর্ডার ফরম্যাট (BusinessKoro-তে কপি-পেস্ট করুন):\n\nঅর্ডার ID: #1001\nগ্রাহক: রহিম উদ্দিন\nফোন: 01712345678\nঠিকানা: ঢাকা, মিরপুর-১০\nপণ্য: ওয়্যারলেস ইয়ারবাড x1\nমোট: ৳1,200\nপেমেন্ট: ক্যাশ অন ডেলিভারি\n\n⚠️ BusinessKoro-তে ম্যানুয়ালি এন্ট্রি করুন (API নেই)।";
        }

        if (str_contains($last, 'রিপ্লাই') || str_contains($last, 'reply') || str_contains($last, 'কাস্টমার') || str_contains($last, 'customer')) {
            return "💬 ডেমো মোড\n\nকাস্টমার রিপ্লাই ড্রাফট:\n\nহ্যালো, আপনার অনুসন্ধানের জন্য ধন্যবাদ!\n\n১. হ্যাঁ, এই পণ্যটি স্টকে আছে।\n২. ঢাকার মধ্যে ডেলিভারি ১-২ দিন, ঢাকার বাইরে ৩-৫ দিন।\n৩. ক্যাশ অন ডেলিভারি পেমেন্ট সাপোর্ট করি।\n\nযেকোনো প্রশ্ন থাকলে জানান।\n\nধন্যবাদান্তে,\nআমার স্টোর";
        }

        if (str_contains($last, 'কন্টেন্ট') || str_contains($last, 'content') || str_contains($last, 'ভিডিও') || str_contains($last, 'video') || str_contains($last, 'স্ক্রিপ্ট')) {
            return "🎬 ডেমো মোড\n\nভিডিও স্ক্রিপ্ট:\n\n[হুক - ০-৩ সেকেন্ড]\n\"এই পণ্যটি আপনার জীবন বদলে দেবে!\"\n\n[সমস্যা - ৩-১০ সেকেন্ড]\n\"কি বিরক্তিকর হয় যখন...\"\n\n[সমাধান - ১০-২৫ সেকেন্ড]\n\"এই পণ্যটি দিয়ে সহজেই...\"\n\n[CTA - ২৫-৩০ সেকেন্ড]\n\"লিংকে ক্লিক করে এখনই অর্ডার করুন!\"";
        }

        // জেনেরিক ইনবক্স রিপ্লাই
        return "🤖 ডেমো মোড\n\nআমি আপনার AI কোচ। আমি আপনাকে ড্রপশিপিং বিজনেস চালাতে সাহায্য করব।\n\nকিছু পরামর্শ:\n📌 প্রথমে ৫-১০টি পণ্য ইম্পোর্ট করুন\n📌 প্রতিদিন সোশ্যাল মিডিয়ায় পোস্ট করুন\n📌 অর্ডার দ্রুত ফরওয়ার্ড করুন\n📌 কাস্টমার রিপ্লাই দ্রুত দিন\n\nকি বিষয়ে জানতে চান?";
    }

    /**
     * ডেমো মোডে আছে কিনা
     */
    public function isDemo(): bool {
        return $this->demo;
    }
}
