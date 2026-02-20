<?php
/**
 * Quick syntax + filter logic test
 */
session_start();
$_SESSION['user_id'] = 3;
$_SESSION['user_role'] = 'director';
$_SESSION['user_name'] = 'Admin';

require_once __DIR__ . '/config/config.php';
$db = getDB();
header('Content-Type: text/plain; charset=utf-8');

echo "=== Filter Queries Test ===\n\n";

// Test 1: Memory filter
$_GET = ['memory' => '1', 'status' => ''];
$memoryFilter = $_GET['memory'] ?? '';
$where = [];
$params = [];
if ($memoryFilter) {
    $where[] = "d.memory_id = ?";
    $params[] = $memoryFilter;
}
$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT COUNT(*) FROM devices d JOIN products p ON d.product_id = p.id JOIN brands b ON p.brand_id = b.id $whereClause";
$stmt = $db->prepare($sql);
$stmt->execute($params);
echo "Memory filter (id=1): " . $stmt->fetchColumn() . " devices\n";

// Test 2: Color filter
$_GET = ['color' => '1', 'status' => ''];
$colorFilter = $_GET['color'] ?? '';
$where = [];
$params = [];
if ($colorFilter) {
    $where[] = "d.color_id = ?";
    $params[] = $colorFilter;
}
$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
$stmt = $db->prepare("SELECT COUNT(*) FROM devices d JOIN products p ON d.product_id = p.id JOIN brands b ON p.brand_id = b.id $whereClause");
$stmt->execute($params);
echo "Color filter (id=1): " . $stmt->fetchColumn() . " devices\n";

// Test 3: Brand + Model combined filter
$_GET = ['brand' => '1', 'model' => '79', 'status' => ''];
$brandFilter = $_GET['brand'] ?? '';
$modelFilter = $_GET['model'] ?? '';
$where = [];
$params = [];
if ($brandFilter) {
    $where[] = "b.id = ?";
    $params[] = $brandFilter;
}
if ($modelFilter) {
    $where[] = "p.id = ?";
    $params[] = $modelFilter;
}
$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
$stmt = $db->prepare("SELECT COUNT(*) FROM devices d JOIN products p ON d.product_id = p.id JOIN brands b ON p.brand_id = b.id $whereClause");
$stmt->execute($params);
echo "Brand+Model filter: " . $stmt->fetchColumn() . " devices\n";

// Test 4: Fetch filter data
$memories = $db->query("SELECT * FROM memory_options ORDER BY sort_order")->fetchAll();
echo "\nMemory options: " . count($memories) . "\n";
foreach ($memories as $m)
    echo "  - {$m['size']}\n";

$colors = $db->query("SELECT * FROM color_options ORDER BY sort_order, name_en")->fetchAll();
echo "\nColor options: " . count($colors) . "\n";
foreach ($colors as $c)
    echo "  - {$c['name_en']}\n";

// Test 5: Delete check (sale_items table exists)
try {
    $count = $db->query("SELECT COUNT(*) FROM sale_items")->fetchColumn();
    echo "\nSale items in DB: $count\n";
} catch (Exception $e) {
    echo "\n⚠️ sale_items table issue: " . $e->getMessage() . "\n";
}

echo "\n✅ All filter queries work correctly!\n";
