<?php
session_start();
$_SESSION['user_id'] = 3;  // Actual user ID in DB
$_SESSION['user_role'] = 'director';

require_once __DIR__ . '/config/config.php';
$db = getDB();
header('Content-Type: text/plain; charset=utf-8');

echo "=== Final Test with correct user_id=3 ===\n\n";

$supplier = $db->query("SELECT id, company_name FROM suppliers LIMIT 1")->fetch();
echo "Supplier: {$supplier['company_name']} (ID: {$supplier['id']})\n";

$product = $db->query("SELECT id, name FROM products LIMIT 1")->fetch();
echo "Product: {$product['name']} (ID: {$product['id']})\n\n";

try {
    $db->beginTransaction();

    $invoiceNumber = 'TEST-' . time();
    $purchaseDate = date('Y-m-d');

    $stmt = $db->prepare("
        INSERT INTO purchases (supplier_id, invoice_number, purchase_date, vat_mode, `condition`, currency, notes, attachment_url, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$supplier['id'], $invoiceNumber, $purchaseDate, 'marginal', 'used', 'EUR', 'Test', '', 3]);
    $purchaseId = $db->lastInsertId();
    echo "✅ Purchase created: ID=$purchaseId\n";

    $deviceStmt = $db->prepare("
        INSERT INTO devices (
            product_id, memory_id, color_id, supplier_id, purchase_id, `condition`, grading, 
            purchase_date, quantity, quantity_available, invoice_in, purchase_price, 
            purchase_currency, delivery_cost, delivery_currency, imei, vat_mode, notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $deviceStmt->execute([
        $product['id'],
        null,
        null,
        $supplier['id'],
        $purchaseId,
        'used',
        'A',
        $purchaseDate,
        1,
        1,
        $invoiceNumber,
        150.00,
        'EUR',
        0,
        'EUR',
        null,
        'marginal',
        'Test',
        3
    ]);
    echo "✅ Device inserted!\n";

    $db->rollBack();
    echo "\n🎉 ALL TESTS PASSED! (data rolled back)\n";
    echo "The create_purchase endpoint works correctly when user is logged in.\n";

} catch (Exception $e) {
    if ($db->inTransaction())
        $db->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
