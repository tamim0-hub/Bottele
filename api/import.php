<?php
/**
 * api/import.php — BusinessKoro ক্যাটালগ ইম্পোর্ট API
 * POST: CSV/JSON ফাইল আপলোড বা JSON ডাটা পাঠান
 *
 * CSV ফরম্যাট (হেডার সহ):
 *   name,wholesale_price,category,image_url,sku
 *   "ওয়্যারলেস ইয়ারবাড",500,"ইলেকট্রনিক্স","https://...","WB-001"
 *
 * JSON ফরম্যাট:
 *   [{"name":"...","wholesale_price":500,"category":"...","image_url":"...","sku":"..."}]
 */
require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($auth) || !isset($agents)) {
    http_response_code(503);
    echo json_encode(['error' => 'সিস্টেম ইনস্টল হয়নি।']);
    exit;
}

$auth->requireLogin(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'শুধু POST মেথড।']);
    exit;
}

// ফাইল সাইজ লিমিট — ৫MB পর্যন্ত
$maxFileSize = 5 * 1024 * 1024;
if (!empty($_FILES['catalog']['size']) && $_FILES['catalog']['size'] > $maxFileSize) {
    echo json_encode(['error' => 'ফাইল সাইজ ৫MB এর বেশি হতে পারবে না।']);
    exit;
}

$imported = 0;
$skipped = 0;
$errors  = 0;
$details = [];

try {
    // ফাইল আপলোড চেক
    if (!empty($_FILES['catalog']['tmp_name'])) {
        $tmpPath = $_FILES['catalog']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['catalog']['name'], PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $rows = parseCsv($tmpPath);
        } elseif ($ext === 'json') {
            $rows = json_decode(file_get_contents($tmpPath), true);
            if (!is_array($rows)) {
                echo json_encode(['error' => 'JSON ফাইল পার্স ত্রুটি।']);
                exit;
            }
        } else {
            echo json_encode(['error' => 'শুধু CSV বা JSON ফাইল গ্রহণযোগ্য।']);
            exit;
        }
    } else {
        // JSON বডি থেকে ডাটা
        $raw = file_get_contents('php://input');
        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            echo json_encode(['error' => 'ডাটা ফরম্যাট ত্রুটি। JSON অ্যারে দিন।']);
            exit;
        }
    }

    if (empty($rows)) {
        echo json_encode(['error' => 'কোনো ডাটা পাওয়া যায়নি।']);
        exit;
    }

    // ক্যাটালগ ইম্পোর্ট লগ
    if (!$db->isConnected()) {
        echo json_encode(['error' => 'ডাটাবেস কানেকশন নেই।']);
        exit;
    }
    $stmt = $db->pdo->prepare(
        'INSERT INTO catalog_import (filename, total_rows, status) VALUES (?, ?, "processing")'
    );
    $filename = $_FILES['catalog']['name'] ?? 'json_data';
    $stmt->execute([$filename, count($rows)]);
    $importId = $db->pdo->lastInsertId();

    // প্রতিটি সারি Product Import এজেন্ট দিয়ে ইম্পোর্ট
    foreach ($rows as $row) {
        $name = $row['name'] ?? $row['product_name'] ?? '';
        $wholesale = (float)($row['wholesale_price'] ?? $row['price'] ?? 0);
        $category  = $row['category'] ?? 'সাধারণ';
        $imageUrl  = $row['image_url'] ?? $row['image'] ?? '';
        $sku       = $row['sku'] ?? '';

        if (empty($name)) {
            $skipped++;
            $details[] = "⏭️ নাম ছাড়া সারি স্কিপ";
            continue;
        }

        try {
            $output = $agents->run('product_import', [
                'name'      => $name,
                'wholesale' => $wholesale,
                'category'  => $category,
                'image_url' => $imageUrl,
                'sku'       => $sku,
            ]);
            $imported++;
            $details[] = "✅ {$name}";
        } catch (Exception $e) {
            $errors++;
            $details[] = "❌ {$name}: " . $e->getMessage();
        }
    }

    // ইম্পোর্ট লগ আপডেট
    $stmt = $db->pdo->prepare(
        'UPDATE catalog_import SET imported = ?, skipped = ?, status = "done" WHERE id = ?'
    );
    $stmt->execute([$imported, $skipped + $errors, $importId]);

    echo json_encode([
        'success'  => true,
        'total'    => count($rows),
        'imported' => $imported,
        'skipped'  => $skipped,
        'errors'   => $errors,
        'details'  => $details,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => 'ইম্পোর্ট ত্রুটি: ' . $e->getMessage()]);
}

/**
 * CSV পার্স হেল্পার
 */
function parseCsv(string $path): array {
    $rows = [];
    $handle = fopen($path, 'r');
    if (!$handle) return $rows;

    // হেডার পড়ুন
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return $rows;
    }

    // হেডার ক্লিন করুন
    $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

    // ডাটা পড়ুন
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) === count($headers)) {
            $rows[] = array_combine($headers, $data);
        }
    }

    fclose($handle);
    return $rows;
}
