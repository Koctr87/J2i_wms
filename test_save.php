<?php
// Simulating an AJAX request to create_purchase
$_SESSION['user_id'] = 1; // Simulate admin
$_SESSION['user_role'] = 'director';

require_once __DIR__ . '/config/config.php';
$db = getDB();

$testData = [
    'action' => 'create_purchase',
    'supplier_id' => 1,
    'purchase_date' => date('Y-m-d'),
    'invoice_number' => 'TEST-INV-001',
    'condition' => 'used',
    'vat_mode' => 'marginal',
    'currency' => 'EUR',
    'notes' => 'Test purchase',
    'items' => [
        [
            'product_id' => 1,
            'memory_id' => null,
            'color_id' => null,
            'grading' => 'A',
            'quantity' => 1,
            'purchase_price' => 100,
            'currency' => 'EUR',
            'delivery_cost' => 0,
            'delivery_currency' => 'EUR',
            'imei' => '123456789012345'
        ]
    ]
];

// Re-running the logic partially to see if it throws
try {
    echo "Starting test...\n";
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO purchases (supplier_id, invoice_number, purchase_date, vat_mode, `condition`, currency, notes, attachment_url, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $testData['supplier_id'],
        $testData['invoice_number'],
        $testData['purchase_date'],
        $testData['vat_mode'],
        $testData['condition'],
        $testData['currency'],
        $testData['notes'],
        '',
        $_SESSION['user_id']
    ]);
    $purchaseId = $db->lastInsertId();
    echo "Purchase created: $purchaseId\n";

    $deviceStmt = $db->prepare("
        INSERT INTO devices (
            product_id, memory_id, color_id, supplier_id, purchase_id, `condition`, grading, 
            purchase_date, quantity, quantity_available, invoice_in, purchase_price, 
            purchase_currency, delivery_cost, delivery_currency, imei, vat_mode, notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($testData['items'] as $item) {
        $deviceStmt->execute([
            $item['product_id'],
            $item['memory_id'],
            $item['color_id'],
            $testData['supplier_id'],
            $purchaseId,
            $testData['condition'],
            $item['grading'],
            $testData['purchase_date'],
            $item['quantity'],
            $item['quantity'],
            $testData['invoice_number'],
            $item['purchase_price'],
            $item['currency'],
            $item['delivery_cost'],
            $item['delivery_currency'],
            $item['imei'],
            $testData['vat_mode'],
            $testData['notes'],
            $_SESSION['user_id']
        ]);
        echo "Device inserted.\n";
    }

    $db->rollBack(); // Don't actually save
    echo "Test successful (rolled back).\n";
} catch (Exception $e) {
    if ($db->inTransaction())
        $db->rollBack();
    echo "TEST FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
