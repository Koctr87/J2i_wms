<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "Starting update of sales with CZK prices...\n";

try {
    $db = getDB();

    // Select all sales that need update
    $stmt = $db->query("SELECT id, sale_date, currency_rate_eur, currency_rate_usd, sale_delivery_cost, sale_delivery_currency FROM sales");
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updatedSales = 0;

    $db->beginTransaction();

    $updateSaleStmt = $db->prepare("UPDATE sales SET total_czk = ?, sale_delivery_cost_czk = ? WHERE id = ?");
    $updateItemStmt = $db->prepare("UPDATE sale_items SET total_price_czk = ?, item_delivery_cost_czk = ? WHERE id = ?");

    foreach ($sales as $sale) {
        $saleId = $sale['id'];
        $eurRate = (float) $sale['currency_rate_eur'] ?: 25.00;
        $usdRate = (float) $sale['currency_rate_usd'] ?: 23.00;

        $saleDelCost = (float) $sale['sale_delivery_cost'];
        $saleDelCurr = $sale['sale_delivery_currency'];
        $saleDelRate = ($saleDelCurr === 'EUR') ? $eurRate : (($saleDelCurr === 'USD') ? $usdRate : 1);
        $saleDelCZK = $saleDelCost * $saleDelRate;

        // Fetch items
        $iStmt = $db->prepare("SELECT id, total_price, sale_currency, vat_amount, item_delivery_cost, item_delivery_currency FROM sale_items WHERE sale_id = ?");
        $iStmt->execute([$saleId]);
        $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        $subtotalCZK = 0;
        $vatTotalCZK = 0; // Assuming vat_amount is already saved in the correct currency equivalent or CZK as per your local law. In typical setups, VAT is calculated and recorded in local currency. The current logic in ajax-handler just adds it directly.

        foreach ($items as $item) {
            $sc = $item['sale_currency'] ?? 'CZK';
            $itemRate = ($sc === 'EUR') ? $eurRate : (($sc === 'USD') ? $usdRate : 1);
            $itemTotalCZK = ((float) $item['total_price']) * $itemRate;

            $idc = $item['item_delivery_currency'] ?? 'CZK';
            $idcRate = ($idc === 'EUR') ? $eurRate : (($idc === 'USD') ? $usdRate : 1);
            $itemDelCZK = ((float) $item['item_delivery_cost']) * $idcRate;

            $updateItemStmt->execute([$itemTotalCZK, $itemDelCZK, $item['id']]);

            $subtotalCZK += $itemTotalCZK;
            $vatTotalCZK += (float) $item['vat_amount'];
        }

        $totalCZK = $subtotalCZK + $vatTotalCZK + $saleDelCZK;
        $updateSaleStmt->execute([$totalCZK, $saleDelCZK, $saleId]);

        $updatedSales++;
    }

    $db->commit();
    echo "Successfully updated $updatedSales sales with static CZK prices.\n";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
