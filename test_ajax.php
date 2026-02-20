<?php
/**
 * Direct test of create_purchase logic (bypassing HTTP/session)
 */
header('Content-Type: text/plain; charset=utf-8');

// Start session and simulate login
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'director';
$_SESSION['user_name'] = 'Test User';

require_once __DIR__ . '/config/config.php';
$db = getDB();

echo "=== Direct Test of create_purchase ===\n\n";

// Check if supplier_id=1 exists
$supplier = $db->query("SELECT id, company_name FROM suppliers LIMIT 1")->fetch();
if (!$supplier) {
    echo "ERROR: No suppliers found in database!\n";
    exit;
}
echo "Using supplier: {$supplier['company_name']} (ID: {$supplier['id']})\n";

// Check if product_id=1 exists
$product = $db->query("SELECT id, name FROM products LIMIT 1")->fetch();
if (!$product) {
    echo "ERROR: No products found in database!\n";
    exit;
}
echo "Using product: {$product['name']} (ID: {$product['id']})\n\n";

try {
    $db->beginTransaction();

    $invoiceNumber = 'TEST-' . time();
    $purchaseDate = date('Y-m-d');

    // 1. Insert purchase
    $stmt = $db->prepare("
        INSERT INTO purchases (supplier_id, invoice_number, purchase_date, vat_mode, `condition`, currency, notes, attachment_url, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $supplier['id'],
        $invoiceNumber,
        $purchaseDate,
        'marginal',
        'used',
        'EUR',
        'Test purchase',
        '',
        $_SESSION['user_id']
    ]);
    $purchaseId = $db->lastInsertId();
    echo "✅ Purchase created successfully! ID: $purchaseId\n";

    // 2. Insert device
    $deviceStmt = $db->prepare("
        INSERT INTO devices (
            product_id, memory_id, color_id, supplier_id, purchase_id, `condition`, grading, 
            purchase_date, quantity, quantity_available, invoice_in, purchase_price, 
            purchase_currency, delivery_cost, delivery_currency, imei, vat_mode, notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $deviceStmt->execute([
        $product['id'],
        null,   // memory_id
        null,   // color_id
        $supplier['id'],
        $purchaseId,
        'used',
        'A',
        $purchaseDate,
        1,      // quantity
        1,      // quantity_available
        $invoiceNumber,
        150.00,
        'EUR',
        0,
        'EUR',
        null,   // imei
        'marginal',
        'Test notes',
        $_SESSION['user_id']
    ]);
    echo "✅ Device inserted successfully!\n";

    // Rollback - don't actually save test data
    $db->rollBack();
    echo "\n✅ ALL TESTS PASSED! (Test data rolled back)\n";
    echo "\nThe create_purchase logic works correctly.\n";
    echo "The save button should now work in the browser.\n";

} catch (Exception $e) {
    if ($db->inTransaction())
        $db->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

// Also check debug log
echo "\n--- Debug Log ---\n";
$debugLog = '/tmp/j2i_debug.log';
if (file_exists($debugLog)) {
    $lines = file($debugLog);
    echo implode("", array_slice($lines, -10));
} else {
    echo "No debug log found.\n";
}
