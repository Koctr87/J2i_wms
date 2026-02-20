<?php
require_once __DIR__ . '/../config/config.php';
$db = getDB();

try {
    $db->beginTransaction();

    $saleId = 4;

    echo "Updating Sale #$saleId...\n";

    // 1. Update Sale Header
    $stmt = $db->prepare("UPDATE sales SET sale_date = '2025-09-09', currency_rate_eur = 24.325, client_id = 1 WHERE id = ?");
    $stmt->execute([$saleId]);

    // 2. Identify Items
    // Find iPhone 13 Pro items in this sale
    $stmt = $db->prepare("SELECT si.id, si.device_id, d.product_id FROM sale_items si JOIN devices d ON si.device_id = d.id WHERE si.sale_id = ?");
    $stmt->execute([$saleId]);
    $items = $stmt->fetchAll();

    $iphone13ProItem = null;
    $itemsToDelete = [];

    foreach ($items as $item) {
        // We know from previous steps iPhone 13 Pro has product_id=69. Or simply check logic.
        // Let's assume we keep the first generic item or specifically find iPhone 13 Pro.
        // In create_sale_script.php we inserted product_id 69 (iPhone 13 Pro) first.
        // Let's verify product name.
        $p = $db->query("SELECT name FROM products WHERE id = " . $item['product_id'])->fetch();
        if (strpos($p['name'], '13 Pro') !== false) {
            $iphone13ProItem = $item;
        } else {
            $itemsToDelete[] = $item;
        }
    }

    // 3. Delete other items (iPhone 14 Pro) and restore stock
    foreach ($itemsToDelete as $delItem) {
        // Return to stock
        $db->prepare("UPDATE devices SET quantity_available = quantity_available + 20, status = 'in_stock' WHERE id = ?")->execute([$delItem['device_id']]);
        // Delete item
        $db->prepare("DELETE FROM sale_items WHERE id = ?")->execute([$delItem['id']]);
        echo "Removed item ID {$delItem['id']} (returned to stock)\n";
    }

    // 4. Update iPhone 13 Pro Device Data (Purchase Price & Delivery)
    if ($iphone13ProItem) {
        $deviceId = $iphone13ProItem['device_id'];
        // Update Device: Purchase 358 EUR, Delivery 4 EUR
        // Note: quantity is 20 in device record from previous script
        $db->prepare("UPDATE devices SET purchase_price = 358, delivery_cost = 4, purchase_currency = 'EUR', purchase_date = '2025-09-01' WHERE id = ?")
            ->execute([$deviceId]);

        // Update Sale Item: Price 9443 CZK
        // Margin Calculation Logic for DB storage (marginal)
        // This 'vat_amount' in DB is often used for simple display, but we will rely on calculation in view. 
        // However, we should store correct values.
        // Margin = (9443 * 20) - ((358 + 4) * 24.325 * 20)
        // Sales = 188860. Cost = 362 * 24.325 * 20 = 176113. Margin = 12747.
        // VAT = 12747 * (21/121) = 2212.29

        $db->prepare("UPDATE sale_items SET unit_price = 9443, quantity = 20, vat_amount = 2212.29, total_price = 188860, vat_mode = 'marginal' WHERE id = ?")
            ->execute([$iphone13ProItem['id']]);

        // Update Sale Totals
        $db->prepare("UPDATE sales SET subtotal = 188860, vat_amount = 2212.29, total = 188860 WHERE id = ?")
            ->execute([$saleId]);

        echo "Updated iPhone 13 Pro item and device data.\n";
    }

    $db->commit();
    echo "Sale #4 updated successfully.\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
