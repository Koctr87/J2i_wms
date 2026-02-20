<?php
require_once __DIR__ . '/../config/config.php';

$db = getDB();
$dryRun = false; // Set to false to execute

echo "Starting Migration: Splitting Multi-Quantity Devices...\n";
if ($dryRun)
    echo "[DRY RUN MODE] No changes will be committed.\n";

try {
    $db->beginTransaction();

    // 1. Find target devices: Quantity > 1 and NOT Accessory (Cat 5)
    // We join products to check category
    $sql = "
        SELECT d.*, p.category_id 
        FROM devices d
        JOIN products p ON d.product_id = p.id
        WHERE d.quantity > 1 
        AND p.category_id != 5
        ORDER BY d.id ASC
    ";
    $stmt = $db->query($sql);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($devices) . " devices to process.\n";

    foreach ($devices as $device) {
        $deviceId = $device['id'];
        $originalQty = (int) $device['quantity'];
        $currentAvail = (int) $device['quantity_available'];
        $baseImei = $device['imei'] ?: ('BATCH-' . $deviceId);

        echo "\nProcessing Device #$deviceId (Model: {$device['product_id']}, Qty: $originalQty, Avail: $currentAvail, IMEI: $baseImei)...\n";

        // Find linked sales
        $saleItemsStmt = $db->prepare("SELECT * FROM sale_items WHERE device_id = ?");
        $saleItemsStmt->execute([$deviceId]);
        $saleItems = $saleItemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $soldCount = 0;
        foreach ($saleItems as $sItem) {
            $soldCount += (int) $sItem['quantity'];
        }

        echo "  - Found $soldCount sold items in sales.\n";

        $totalItems = $soldCount + $currentAvail;
        if ($totalItems != $originalQty) {
            echo "  - WARNING: Mismatch! Sold ($soldCount) + Avail ($currentAvail) = $totalItems != Original ($originalQty).\n";
            echo "  - Strategy: We will create $soldCount sold rows and $currentAvail available rows. Total new: $totalItems.\n";
        }

        $counter = 1;

        // A. Process Sold Items
        foreach ($saleItems as $sItem) {
            $sQty = (int) $sItem['quantity'];
            $saleId = $sItem['sale_id'];

            // For each unit in this sale line
            for ($k = 0; $k < $sQty; $k++) {
                $suffix = str_pad($counter, 2, '0', STR_PAD_LEFT);
                $newImei = $baseImei . '-' . $suffix;

                if (!$dryRun) {
                    // 1. Create Device Row
                    // Copy data
                    $newDevice = $device;
                    unset($newDevice['id'], $newDevice['category_id']);
                    $newDevice['quantity'] = 1;
                    $newDevice['quantity_available'] = 0;
                    $newDevice['status'] = 'sold';
                    $newDevice['imei'] = $newImei;

                    // Construct INSERT
                    $cols = array_keys($newDevice);
                    $placeholders = implode(',', array_fill(0, count($cols), '?'));
                    $colNames = implode('`, `', $cols);
                    $insertSql = "INSERT INTO devices (`$colNames`) VALUES ($placeholders)";
                    $insertStmt = $db->prepare($insertSql);
                    $insertStmt->execute(array_values($newDevice));
                    $newDeviceId = $db->lastInsertId();

                    // 2. Create Sale Item Row
                    $newSaleItem = $sItem;
                    unset($newSaleItem['id']);
                    $newSaleItem['device_id'] = $newDeviceId;
                    $newSaleItem['quantity'] = 1;
                    // Recalculate totals if needed, but per-unit usually unit_price is same
                    // total_price for 1 item = unit_price
                    $newSaleItem['total_price'] = $newSaleItem['unit_price'];
                    $newSaleItem['vat_amount'] = $newSaleItem['vat_amount'] / $sQty; // Pro-rate VAT? Simplification: recalculate based on unit

                    $siCols = array_keys($newSaleItem);
                    $siPlaceholders = implode(',', array_fill(0, count($siCols), '?'));
                    $siColNames = implode('`, `', $siCols);
                    $siSql = "INSERT INTO sale_items (`$siColNames`) VALUES ($siPlaceholders)";
                    $siStmt = $db->prepare($siSql);
                    $siStmt->execute(array_values($newSaleItem));
                }
                echo "    -> Created SOLD device $newImei (ID: " . ($dryRun ? '??' : $newDeviceId) . ")\n";
                $counter++;
            }

            // Delete original Sale Item
            if (!$dryRun) {
                $db->prepare("DELETE FROM sale_items WHERE id = ?")->execute([$sItem['id']]);
            }
        }

        // B. Process Available Items
        for ($k = 0; $k < $currentAvail; $k++) {
            $suffix = str_pad($counter, 2, '0', STR_PAD_LEFT);
            $newImei = $baseImei . '-' . $suffix;

            if (!$dryRun) {
                $newDevice = $device;
                unset($newDevice['id'], $newDevice['category_id']);
                $newDevice['quantity'] = 1;
                $newDevice['quantity_available'] = 1;
                $newDevice['status'] = 'in_stock';
                $newDevice['imei'] = $newImei;

                $cols = array_keys($newDevice);
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $colNames = implode('`, `', $cols);
                $insertSql = "INSERT INTO devices (`$colNames`) VALUES ($placeholders)";
                $insertStmt = $db->prepare($insertSql);
                $insertStmt->execute(array_values($newDevice));
                $newDeviceId = $db->lastInsertId();
            }
            echo "    -> Created STOCK device $newImei\n";
            $counter++;
        }

        // C. Clean up Original Device
        if (!$dryRun) {
            // Verify no sale items link to it anymore
            $chk = $db->prepare("SELECT COUNT(*) FROM sale_items WHERE device_id = ?");
            $chk->execute([$deviceId]);
            if ($chk->fetchColumn() == 0) {
                $db->prepare("DELETE FROM devices WHERE id = ?")->execute([$deviceId]);
                echo "    -> Deleted original device #$deviceId\n";
            } else {
                echo "    -> ERROR: Could not delete device #$deviceId, sale items still linked!\n";
                throw new Exception("Migration safety check failed for device #$deviceId");
            }
        }
    }

    if ($dryRun) {
        $db->rollBack();
        echo "\n[DRY RUN] Rolled back changes.\n";
    } else {
        $db->commit();
        echo "\n[SUCCESS] Migration committed.\n";
    }

} catch (Exception $e) {
    $db->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
