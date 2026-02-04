<?php
require_once __DIR__ . '/../config/config.php';
$db = getDB();

try {
    $db->beginTransaction();

    $saleId = 4;
    $rate = 24.325; // 09.09.2025

    echo "Updating Sale #$saleId (Adding iPhone 14 Pro)...\n";

    // 1. Update iPhone 13 Pro Memory
    // Find device linked to sale item (13 Pro)
    $stmt = $db->query("SELECT d.id FROM sale_items si JOIN devices d ON si.device_id = d.id WHERE si.sale_id = $saleId AND d.product_id = 69");
    $dev13Id = $stmt->fetchColumn();
    if ($dev13Id) {
        $db->prepare("UPDATE devices SET memory_id = 4 WHERE id = ?")->execute([$dev13Id]);
        echo "Updated iPhone 13 Pro memory to 128GB.\n";
    }

    // 2. Prepare iPhone 14 Pro Device
    // Find available iPhone 14 Pro (product_id=65) or create if not exists
    // We expect one to be 'in_stock' because we returned it in previous step.
    $stmt = $db->query("SELECT id FROM devices WHERE product_id = 65 AND status = 'in_stock' LIMIT 1");
    $dev14Id = $stmt->fetchColumn();

    if (!$dev14Id) {
        // If not found (maybe sold?), check sold and return? No, create new.
        // Or specific logic? Let's create new just in case.
        $db->prepare("INSERT INTO devices (product_id, quantity, quantity_available, status, created_at) VALUES (65, 20, 20, 'in_stock', NOW())")->execute();
        $dev14Id = $db->lastInsertId();
        echo "Created new iPhone 14 Pro device batch.\n";
    }

    // Update Device Data
    $db->prepare("UPDATE devices SET 
        purchase_price = 470, 
        delivery_cost = 4, 
        purchase_currency = 'EUR', 
        memory_id = 4, 
        purchase_date = '2025-09-01' 
        WHERE id = ?")->execute([$dev14Id]);

    // 3. Add to Sale Items
    // Calculate values
    $qty = 20;
    $unitPrice = 12160;

    // Cost per unit calculation for Verification
    $costPerUnit = (470 + 4) * $rate;
    $marginPerUnit = $unitPrice - $costPerUnit;
    $vatPerUnit = $marginPerUnit * (21 / 121);

    $totalVat = $vatPerUnit * $qty;
    $totalPrice = $unitPrice * $qty;

    // Check if item already exists (avoid dupes if re-run)
    $exists = $db->query("SELECT id FROM sale_items WHERE sale_id = $saleId AND device_id = $dev14Id")->fetchColumn();

    if ($exists) {
        $db->prepare("UPDATE sale_items SET quantity=?, unit_price=?, vat_amount=?, total_price=? WHERE id=?")
            ->execute([$qty, $unitPrice, $totalVat, $totalPrice, $exists]);
        echo "Updated existing 14 Pro sale item.\n";
    } else {
        $db->prepare("INSERT INTO sale_items (sale_id, device_id, quantity, unit_price, vat_mode, vat_amount, total_price) VALUES (?, ?, ?, ?, 'marginal', ?, ?)")
            ->execute([$saleId, $dev14Id, $qty, $unitPrice, $totalVat, $totalPrice]);
        echo "Added 14 Pro sale item.\n";
    }

    // Update Stock Status
    $db->prepare("UPDATE devices SET quantity_available = 0, status = 'sold' WHERE id = ?")->execute([$dev14Id]);

    // 4. Update Sale Totals
    // Sum from all items
    $totals = $db->query("SELECT SUM(total_price) as subtotal, SUM(vat_amount) as vat FROM sale_items WHERE sale_id = $saleId")->fetch();
    $db->prepare("UPDATE sales SET subtotal = ?, vat_amount = ?, total = ? WHERE id = ?")
        ->execute([$totals['subtotal'], $totals['vat'], $totals['subtotal'], $saleId]);

    echo "Sale #4 totals updated. Total: {$totals['subtotal']}\n";

    $db->commit();
    echo "Done.\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
