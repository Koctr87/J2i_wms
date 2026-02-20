<?php
require_once __DIR__ . '/config/config.php';
$db = getDB();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $stmt = $db->query("
            SELECT COALESCE(SUM(
                (COALESCE(purchase_price_czk, 0) + COALESCE(delivery_cost_czk, 0)) * quantity_available
            ), 0) as value 
            FROM devices 
            WHERE status = 'in_stock'
        ");
    echo "test1 ok\n";

    $stmt = $db->query("
SELECT 
                SUM(
                    si.total_price_czk - 
                    (
                        COALESCE(d.purchase_price_czk, 0) +
                        COALESCE(d.delivery_cost_czk, 0) +
                        COALESCE(si.item_delivery_cost_czk, 0)
                    ) * si.quantity - 
                    CASE 
                        WHEN si.vat_mode = 'reverse' THEN 0 
                        WHEN si.vat_mode = 'marginal' THEN 
                            GREATEST(0, (
                                si.total_price_czk - 
                                (COALESCE(d.purchase_price_czk, 0) + COALESCE(d.delivery_cost_czk, 0)) * si.quantity
                            ) * 21/121)
                        ELSE si.vat_amount 
                    END
                ) as item_profit
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            LEFT JOIN devices d ON si.device_id = d.id
            WHERE s.status = 'completed'
");
    echo "test2 ok\n";

} catch (PDOException $e) {
    echo $e->getMessage() . "\n";
}
