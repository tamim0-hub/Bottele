<?php
/**
 * lib/demo.php — ডেমো মোড: সিড ডাটা ও ফেক ফিচার
 * Provides sample products, orders, and agent runs for evaluation.
 */

class Demo {

    /**
     * ডেমো ডাটা সিড করুন — ইনস্টলার বা সেটিংস থেকে কল হয়
     */
    public static function seed(DB $db): void {
        // এজেন্ট স্টেট সিড
        $agents = ['leader', 'product_import', 'price', 'inventory', 'cart_recovery', 'social', 'seo', 'content', 'customer_reply', 'order_prep'];
        $stmt = $db->pdo->prepare(
            'INSERT INTO agent_state (agent, state, last_run, last_output, run_count, error_count)
             VALUES (?, "idle", DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*24) HOUR), ?, FLOOR(5+RAND()*20), 0)
             ON DUPLICATE KEY UPDATE run_count = run_count + 1'
        );

        $outputs = [
            'leader' => 'সব এজেন্ট সচল। কোনো সমস্যা নেই।',
            'product_import' => '৫টি পণ্য ইম্পোর্ট সম্পন্ন।',
            'price' => '১২টি পণ্যের দাম আপডেট হয়েছে।',
            'inventory' => '৩টি পণ্য out-of-stock, unpublish করা হয়েছে।',
            'cart_recovery' => '৪টি কার্ট রিকভারি ইমেইল পাঠানো হয়েছে।',
            'social' => 'Facebook ও Instagram পোস্ট তৈরি হয়েছে।',
            'seo' => 'SEO স্কোর: ৬৫/১০০। ৩টি সুপারিশ আছে।',
            'content' => 'ভিডিও স্ক্রিপ্ট ও ব্লগ পোস্ট ড্রাফট তৈরি।',
            'customer_reply' => '২টি কাস্টমার রিপ্লাই ড্রাফট তৈরি।',
            'order_prep' => '৩টি অর্ডার ফরম্যাট করা হয়েছে।',
        ];

        foreach ($agents as $a) {
            $stmt->execute([$a, $outputs[$a] ?? 'কোনো আউটপুট নেই।']);
        }

        // স্যাম্পল অর্ডার সিড
        $orderStmt = $db->pdo->prepare(
            'INSERT IGNORE INTO agent_orders (woo_order_id, customer_name, customer_email, total, status, bk_formatted)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $orders = [
            [1001, 'রহিম উদ্দিন', 'rahim@example.com', 1200.00, 'forwarded', "অর্ডার: #1001\nগ্রাহক: রহিম উদ্দিন\nফোন: 01712345678\nঠিকানা: মিরপুর-১০, ঢাকা\nপণ্য: ওয়্যারলেস ইয়ারবাড x1\nমোট: ৳1,200\nপেমেন্ট: COD"],
            [1002, 'ফাতেমা বেগম', 'fatema@example.com', 2500.00, 'pending', "অর্ডার: #1002\nগ্রাহক: ফাতেমা বেগম\nফোন: 01898765432\nঠিকানা: উত্তরা, ঢাকা\nপণ্য: স্মার্ট ওয়াচ x1\nমোট: ৳2,500\nপেমেন্ট: COD"],
            [1003, 'করিম হাসান', 'karim@example.com', 1800.00, 'completed', "অর্ডার: #1003\nগ্রাহক: করিম হাসান\nফোন: 01654321098\nঠিকানা: চট্টগ্রাম\nপণ্য: ব্লুটুথ স্পিকার x1\nমোট: ৳1,800\nপেমেন্ট: COD"],
            [1004, 'আনিসা খাতুন', 'anisa@example.com', 950.00, 'pending', "অর্ডার: #1004\nগ্রাহক: আনিসা খাতুন\nফোন: 01512348765\nঠিকানা: সিলেট\nপণ্য: পোর্টেবল চার্জার x1\nমোট: ৳950\nপেমেন্ট: COD"],
            [1005, 'সাকিব আলী', 'sakib@example.com', 1950.00, 'forwarded', "অর্ডার: #1005\nগ্রাহক: সাকিব আলী\nফোন: 01498761234\nঠিকানা: রাজশাহী\nপণ্য: LED ডেস্ক ল্যাম্প x2\nমোট: ৳1,950\nপেমেন্ট: COD"],
        ];

        foreach ($orders as $o) {
            $orderStmt->execute($o);
        }

        // স্যাম্পল কার্ট রিকভারি সিড
        $cartStmt = $db->pdo->prepare(
            'INSERT IGNORE INTO cart_recovery (customer_email, customer_name, cart_data, step, purchased)
             VALUES (?, ?, ?, ?, ?)'
        );

        $carts = [
            ['nusrat@example.com', 'নুসরাত জাহান', '{"items":[{"name":"ওয়্যারলেস ইয়ারবাড","qty":1,"price":1200}],"total":1200}', 0, 0],
            ['kamal@example.com', 'কামাল হোসেন', '{"items":[{"name":"স্মার্ট ওয়াচ","qty":1,"price":2500},{"name":"স্ক্রিন প্রোটেক্টর","qty":2,"price":200}],"total":2900}', 1, 0],
            ['rupa@example.com', 'রূপা আক্তার', '{"items":[{"name":"ব্লুটুথ স্পিকার","qty":1,"price":1800}],"total":1800}', 2, 0],
            ['mizan@example.com', 'মিজানুর রহমান', '{"items":[{"name":"LED ডেস্ক ল্যাম্প","qty":1,"price":750}],"total":750}', 0, 1],
        ];

        foreach ($carts as $c) {
            $cartStmt->execute($c);
        }

        // স্যাম্পল লগ সিড
        $logStmt = $db->pdo->prepare(
            'INSERT INTO agent_logs (agent, action, input_summary, output_summary, status, created_at)
             VALUES (?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? MINUTE))'
        );

        $logs = [
            ['product_import', 'import', 'ওয়্যারলেস ইয়ারবাড, ৳500', 'পণ্য তৈরি হয়েছে (ID: 1)', 'success', 120],
            ['price', 'reprice', 'সব পণ্য', '১২টি পণ্যের দাম আপডেট', 'success', 60],
            ['inventory', 'sync', 'সব পণ্য', '৩টি out-of-stock unpublish', 'success', 55],
            ['cart_recovery', 'send', '৪টি কার্ট', '৪টি ইমেইল পাঠানো হয়েছে', 'success', 30],
            ['social', 'generate', 'Facebook, Instagram', '২টি পোস্ট তৈরি', 'success', 25],
            ['seo', 'audit', 'হোমপেজ', 'স্কোর: ৬৫/১০০', 'success', 15],
            ['content', 'generate', 'ভিডিও স্ক্রিপ্ট', '৩০ সেকেন্ড স্ক্রিপ্ট তৈরি', 'success', 10],
            ['customer_reply', 'draft', '২টি মেসেজ', '২টি রিপ্লাই ড্রাফট', 'success', 5],
            ['order_prep', 'format', '৫টি অর্ডার', '৩টি পেন্ডিং ফরম্যাট', 'success', 2],
            ['leader', 'orchestrate', 'দৈনিক', 'সব এজেন্ট চেক সম্পন্ন', 'success', 1],
        ];

        foreach ($logs as $l) {
            $logStmt->execute($l);
        }

        // স্যাম্পল চ্যাট মেসেজ
        $chatStmt = $db->pdo->prepare(
            'INSERT INTO chat_messages (role, content, created_at)
             VALUES (?, ?, DATE_SUB(NOW(), INTERVAL ? MINUTE))'
        );
        $chatStmt->execute(['user', 'আমার ড্রপশিপিং বিজনেস শুরু করতে চাই, কি করব?', 10]);
        $chatStmt->execute(['assistant', 'ড্রপশিপিং শুরু করতে এই পদক্ষেপগুলো অনুসরণ করুন:\n\n১. BusinessKoro-তে অ্যাকাউন্ট খুলুন\n২. ৫-১০টি পণ্য বেছে নিন\n৩. আপনার WooCommerce স্টোরে পণ্য ইম্পোর্ট করুন (Product Import এজেন্ট ব্যবহার করুন)\n৪. সোশ্যাল মিডিয়ায় মার্কেটিং করুন (Social এজেন্ট ব্যবহার করুন)\n৫. অর্ডার পেলে দ্রুত BusinessKoro-তে ফরওয়ার্ড করুন\n\nকোন ধাপে সাহায্য চান?', 9]);
    }
}
