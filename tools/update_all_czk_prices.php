<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "Starting update of devices with CZK prices...\n";

try {
    $db = getDB();

    // Select all devices that need update
    $stmt = $db->query("SELECT id, purchase_date, created_at, purchase_price, purchase_currency, delivery_cost, delivery_currency FROM devices");
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updatedCount = 0;

    $db->beginTransaction();

    $updateStmt = $db->prepare("UPDATE devices SET purchase_price_czk = ?, delivery_cost_czk = ? WHERE id = ?");

    // Cache array to avoid repeating API/DB requests for the same date
    $rateCache = [];

    foreach ($devices as $d) {
        $dateStr = $d['purchase_date'];
        if (!$dateStr) {
            $dateStr = $d['created_at'] ? date('Y-m-d', strtotime($d['created_at'])) : date('Y-m-d');
        } else {
            $dateStr = date('Y-m-d', strtotime($dateStr));
        }

        if (!isset($rateCache[$dateStr])) {
            $rateCache[$dateStr] = [
                'EUR' => getCNBRate('EUR', $dateStr) ?? 25.00,
                'USD' => getCNBRate('USD', $dateStr) ?? 23.00,
                'CZK' => 1.00
            ];
            echo "Fetched rate for $dateStr - EUR: {$rateCache[$dateStr]['EUR']}, USD: {$rateCache[$dateStr]['USD']}\n";
        }

        $p_curr = $d['purchase_currency'] ?? 'EUR';
        $d_curr = $d['delivery_currency'] ?? 'EUR';

        $p_rate = $rateCache[$dateStr][$p_curr] ?? 1.00;
        $d_rate = $rateCache[$dateStr][$d_curr] ?? 1.00;

        $p_czk = ((float) $d['purchase_price']) * $p_rate;
        $d_czk = ((float) $d['delivery_cost']) * $d_rate;

        $updateStmt->execute([$p_czk, $d_czk, $d['id']]);
        $updatedCount++;
    }

    $db->commit();
    echo "Successfully updated $updatedCount devices with static CZK prices.\n";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
