<?php
/**
 * lib/woo.php — WooCommerce REST API র‍্যাপার
 * Handles products and orders via WooCommerce REST API.
 * Falls back gracefully when keys are not configured.
 */

class Woo {
    private string $url;
    private string $ck;
    private string $cs;
    private bool $demo;

    public function __construct() {
        $this->url  = defined('WOO_URL') ? rtrim(WOO_URL, '/') : '';
        $this->ck   = defined('WOO_CK') ? WOO_CK : '';
        $this->cs   = defined('WOO_CS') ? WOO_CS : '';
        $this->demo = (defined('DEMO_MODE') && DEMO_MODE) || empty($this->url) || empty($this->ck);
    }

    /**
     * পণ্য তৈরি করুন
     * @param array $data WooCommerce product data
     * @return array{success:bool, id?:int, error?:string}
     */
    public function createProduct(array $data): array {
        if ($this->demo) {
            return $this->demoProduct($data);
        }

        try {
            $endpoint = $this->url . '/wp-json/wc/v3/products';
            $response = $this->request('POST', $endpoint, $data);

            if (isset($response['id'])) {
                return ['success' => true, 'id' => (int)$response['id'], 'data' => $response];
            }
            return ['success' => false, 'error' => $response['message'] ?? 'পণ্য তৈরিতে সমস্যা।'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * পণ্য আপডেট করুন
     */
    public function updateProduct(int $id, array $data): array {
        if ($this->demo) {
            return ['success' => true, 'id' => $id];
        }

        try {
            $endpoint = $this->url . "/wp-json/wc/v3/products/{$id}";
            $response = $this->request('PUT', $endpoint, $data);

            if (isset($response['id'])) {
                return ['success' => true, 'id' => (int)$response['id']];
            }
            return ['success' => false, 'error' => $response['message'] ?? 'আপডেটে সমস্যা।'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * পণ্যের তালিকা পান
     */
    public function getProducts(int $page = 1, int $per_page = 50): array {
        if ($this->demo) {
            return $this->demoProducts();
        }

        try {
            $endpoint = $this->url . "/wp-json/wc/v3/products?page={$page}&per_page={$per_page}";
            return $this->request('GET', $endpoint);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * একটি পণ্য পান
     */
    public function getProduct(int $id): array {
        if ($this->demo) {
            return $this->demoSingleProduct($id);
        }

        try {
            $endpoint = $this->url . "/wp-json/wc/v3/products/{$id}";
            return $this->request('GET', $endpoint);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * অর্ডারের তালিকা পান
     */
    public function getOrders(int $page = 1, int $per_page = 50): array {
        if ($this->demo) {
            return $this->demoOrders();
        }

        try {
            $endpoint = $this->url . "/wp-json/wc/v3/orders?page={$page}&per_page={$per_page}";
            return $this->request('GET', $endpoint);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * একটি অর্ডার পান
     */
    public function getOrder(int $id): array {
        if ($this->demo) {
            return $this->demoSingleOrder($id);
        }

        try {
            $endpoint = $this->url . "/wp-json/wc/v3/orders/{$id}";
            return $this->request('GET', $endpoint);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * ডেমো পণ্য
     */
    private function demoProducts(): array {
        return [
            ['id' => 1, 'name' => 'ওয়্যারলেস ইয়ারবাড', 'price' => '1200', 'status' => 'publish', 'stock_status' => 'instock'],
            ['id' => 2, 'name' => 'স্মার্ট ওয়াচ', 'price' => '2500', 'status' => 'publish', 'stock_status' => 'instock'],
            ['id' => 3, 'name' => 'ব্লুটুথ স্পিকার', 'price' => '1800', 'status' => 'publish', 'stock_status' => 'instock'],
            ['id' => 4, 'name' => 'পোর্টেবল চার্জার', 'price' => '950', 'status' => 'draft', 'stock_status' => 'outofstock'],
            ['id' => 5, 'name' => 'LED ডেস্ক ল্যাম্প', 'price' => '750', 'status' => 'publish', 'stock_status' => 'instock'],
        ];
    }

    private function demoSingleProduct(int $id): array {
        $products = $this->demoProducts();
        foreach ($products as $p) {
            if ($p['id'] === $id) return $p;
        }
        return [];
    }

    /**
     * ডেমো অর্ডার
     */
    private function demoOrders(): array {
        return [
            ['id' => 1001, 'status' => 'processing', 'total' => '1200', 'billing' => ['first_name' => 'রহিম', 'last_name' => 'উদ্দিন', 'email' => 'rahim@example.com', 'phone' => '01712345678', 'address_1' => 'মিরপুর-১০', 'city' => 'ঢাকা'], 'line_items' => [['name' => 'ওয়্যারলেস ইয়ারবাড', 'quantity' => 1]]],
            ['id' => 1002, 'status' => 'pending', 'total' => '2500', 'billing' => ['first_name' => 'ফাতেমা', 'last_name' => 'বেগম', 'email' => 'fatema@example.com', 'phone' => '01898765432', 'address_1' => 'সেক্টর-৭', 'city' => 'উত্তরা, ঢাকা'], 'line_items' => [['name' => 'স্মার্ট ওয়াচ', 'quantity' => 1]]],
            ['id' => 1003, 'status' => 'completed', 'total' => '1800', 'billing' => ['first_name' => 'করিম', 'last_name' => 'হাসান', 'email' => 'karim@example.com', 'phone' => '01654321098', 'address_1' => 'জি ই সি মোড', 'city' => 'চট্টগ্রাম'], 'line_items' => [['name' => 'ব্লুটুথ স্পিকার', 'quantity' => 1]]],
        ];
    }

    private function demoSingleOrder(int $id): array {
        $orders = $this->demoOrders();
        foreach ($orders as $o) {
            if ($o['id'] === $id) return $o;
        }
        return [];
    }

    private function demoProduct(array $data): array {
        static $demoId = 100;
        $demoId++;
        return ['success' => true, 'id' => $demoId, 'data' => array_merge($data, ['id' => $demoId])];
    }

    /**
     * WooCommerce REST API রিকোয়েস্ট
     */
    private function request(string $method, string $url, array $body = []): array {
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERPWD        => $this->ck . ':' . $this->cs,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException("WooCommerce API cURL error: $err");
        }

        if ($httpCode >= 400) {
            error_log("WooCommerce API HTTP $httpCode: $response");
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * ডেমো মোডে আছে কিনা
     */
    public function isDemo(): bool {
        return $this->demo;
    }
}
