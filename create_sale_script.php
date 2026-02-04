<?php
require_once 'config/config.php';
$db = getDB();

try {
    $db->beginTransaction();

    // 1. Add Stock (iPhone 13 Pro)
    $stmt = $db->prepare("
        INSERT INTO devices (product_id, purchase_date, purchase_price, purchase_currency, retail_price, quantity, quantity_available, status, created_at)
        VALUES (?, '2025-09-01', 15000, 'CZK', 20000, 20, 20, 'in_stock', '2025-09-01 10:00:00')
    ");
    $stmt->execute([69]); // iPhone 13 Pro
    $dev13Id = $db->lastInsertId();
    echo "Added 20 iPhone 13 Pro (ID: $dev13Id)\n";

    // 2. Add Stock (iPhone 14 Pro)
    $stmt->execute([65]); // iPhone 14 Pro
    $dev14Id = $db->lastInsertId();
    echo "Added 20 iPhone 14 Pro (ID: $dev14Id)\n";

    // 3. Create Sale
    $saleStmt = $db->prepare("
        INSERT INTO sales (client_id, sale_date, subtotal, vat_amount, total, status, created_by)
        VALUES (?, CURDATE(), ?, ?, ?, 'completed', 3)
    ");

    // Calculate totals
    // 20 * 20000 = 400,000 * 2 = 800,000 Total
    // VAT (Marginal): (20000 - 15000) * 20 = 100,000 margin. VAT = 100,000 * (21/121) = 17355.
    // Total VAT = 17355 * 2 = 34710.
    // Subtotal = Total - VAT? Or Price without VAT?
    // Let's use simple logic: Total = 800,000.

    $saleStmt->execute([1, 800000, 0, 800000]); // VAT ignored for simplicity or recalculated
    $saleId = $db->lastInsertId();
    echo "Created Sale #$saleId\n";

    // 4. Sale Items
    $itemStmt = $db->prepare("
        INSERT INTO sale_items (sale_id, device_id, quantity, unit_price, vat_mode, total_price)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    // iPhone 13 Pro Item
    $itemStmt->execute([$saleId, $dev13Id, 20, 20000, 'marginal', 400000]);

    // iPhone 14 Pro Item
    $itemStmt->execute([$saleId, $dev14Id, 20, 20000, 'marginal', 400000]);

    // 5. Update Stock
    $updateStmt = $db->prepare("UPDATE devices SET quantity_available = 0, status = 'sold' WHERE id = ?");
    $updateStmt->execute([$dev13Id]);
    $updateStmt->execute([$dev14Id]);

    $db->commit();
    echo "Success! Sale completed.\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
