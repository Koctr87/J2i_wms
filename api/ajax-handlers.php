<?php
/**
 * J2i Warehouse Management System
 * AJAX Handlers
 */
require_once __DIR__ . '/../config/config.php';

// Only accept AJAX requests
if (!isAjax() && !isset($_GET['action'])) {
    http_response_code(403);
    exit;
}

$db = getDB();
$action = $_GET['action'] ?? ($_POST['action'] ?? null);

// For POST requests, get JSON body
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $action = $input['action'] ?? $action;
    }
}

switch ($action) {
    case 'get_products':
        // Get products for a brand
        $brandId = $_GET['brand_id'] ?? 0;
        $stmt = $db->prepare("SELECT id, name FROM products WHERE brand_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$brandId]);
        jsonResponse($stmt->fetchAll());
        break;

    case 'create_product':
        // ... (existing code)
        break;

    case 'add_client_comment':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $clientId = $input['client_id'] ?? 0;
        $comment = trim($input['comment'] ?? '');

        if (!$clientId || !$comment) {
            jsonResponse(['success' => false, 'message' => 'Missing data'], 400);
        }

        try {
            $stmt = $db->prepare("INSERT INTO client_comments (client_id, comment, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$clientId, $comment, $_SESSION['user_id']]);
            $id = $db->lastInsertId();

            $userName = $_SESSION['user_name'] ?? 'User'; // Assuming we have this or fetch it
            // Actually session might store user info. Let's assume standard behavior.

            jsonResponse(['success' => true, 'id' => $id, 'date' => date('d.m.Y H:i')]);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'message' => 'Database error'], 500);
        }
        break;

    case 'get_devices':
        // Get devices for sale selection
        $status = $_GET['status'] ?? 'in_stock';
        $search = $_GET['search'] ?? '';

        $sql = "SELECT d.id, d.quantity_available, d.retail_price, d.vat_mode, d.purchase_price, d.purchase_currency, d.delivery_cost,
                       p.name as product_name, b.name as brand_name, m.size as memory, c.name_en as color
                FROM devices d
                JOIN products p ON d.product_id = p.id
                JOIN brands b ON p.brand_id = b.id
                LEFT JOIN memory_options m ON d.memory_id = m.id
                LEFT JOIN color_options c ON d.color_id = c.id
                WHERE d.status = ? AND d.quantity_available > 0";
        $params = [$status];

        if ($search) {
            $sql .= " AND (p.name LIKE ? OR b.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY d.created_at DESC LIMIT 50";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
        break;

    case 'get_accessories':
        // Get accessories for sale selection
        $search = $_GET['search'] ?? '';

        $sql = "SELECT a.id, a.name, a.quantity_available, a.selling_price, t.name_en as type_name
                FROM accessories a
                JOIN accessory_types t ON a.type_id = t.id
                WHERE a.status = 'in_stock' AND a.quantity_available > 0";
        $params = [];

        if ($search) {
            $sql .= " AND a.name LIKE ?";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY a.created_at DESC LIMIT 50";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
        break;

    case 'get_clients':
        // Get clients for sale selection
        $search = $_GET['search'] ?? '';

        $sql = "SELECT id, company_name, ico, dic FROM clients WHERE is_active = 1";
        $params = [];

        if ($search) {
            $sql .= " AND (company_name LIKE ? OR ico LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY company_name LIMIT 50";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
        break;

    case 'get_cnb_rate':
        // Get CNB currency rate
        $currency = $_GET['currency'] ?? 'EUR';
        $date = $_GET['date'] ?? date('Y-m-d');

        $rate = getCNBRate($currency, $date);

        if ($rate !== null) {
            jsonResponse(['success' => true, 'rate' => $rate, 'currency' => $currency, 'date' => $date]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Could not fetch rate'], 500);
        }
        break;

    case 'calculate_vat':
        // Calculate VAT for sale
        $purchasePrice = (float) ($input['purchase_price'] ?? 0);
        $sellingPrice = (float) ($input['selling_price'] ?? 0);
        $vatMode = $input['vat_mode'] ?? 'no';
        $currencyRate = (float) ($input['currency_rate'] ?? 1);

        $result = calculateVAT($purchasePrice, $sellingPrice, $vatMode, $currencyRate);
        jsonResponse(['success' => true, 'data' => $result]);
        break;

    case 'create_sale':
        // Create a new sale
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $clientId = $input['client_id'] ?? 0;
        $saleDate = $input['sale_date'] ?? date('Y-m-d');
        $invoiceNumber = $input['invoice_number'] ?? null;
        $eurRate = $input['eur_rate'] ?? null;
        $usdRate = $input['usd_rate'] ?? null;
        $items = $input['items'] ?? [];

        if (!$clientId || empty($items)) {
            jsonResponse(['success' => false, 'message' => 'Missing required fields'], 400);
        }

        try {
            $db->beginTransaction();

            $subtotal = 0;
            $vatTotal = 0;

            // Insert sale
            $stmt = $db->prepare("
                INSERT INTO sales (client_id, sale_date, invoice_number, currency_rate_eur, currency_rate_usd, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$clientId, $saleDate, $invoiceNumber, $eurRate, $usdRate, $_SESSION['user_id']]);
            $saleId = $db->lastInsertId();

            // Insert sale items
            $itemStmt = $db->prepare("
                INSERT INTO sale_items (sale_id, device_id, accessory_id, quantity, unit_price, vat_mode, vat_amount, total_price)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $itemTotal;
                $vatTotal += $item['vat_amount'] ?? 0;

                $itemStmt->execute([
                    $saleId,
                    $item['device_id'] ?? null,
                    $item['accessory_id'] ?? null,
                    $item['quantity'],
                    $item['unit_price'],
                    $item['vat_mode'],
                    $item['vat_amount'] ?? 0,
                    $itemTotal
                ]);

                // Update device/accessory quantity
                if (!empty($item['device_id'])) {
                    $db->prepare("UPDATE devices SET quantity_available = quantity_available - ?, status = CASE WHEN quantity_available - ? <= 0 THEN 'sold' ELSE status END WHERE id = ?")
                        ->execute([$item['quantity'], $item['quantity'], $item['device_id']]);
                }
                if (!empty($item['accessory_id'])) {
                    $db->prepare("UPDATE accessories SET quantity_available = quantity_available - ?, status = CASE WHEN quantity_available - ? <= 0 THEN 'sold' ELSE status END WHERE id = ?")
                        ->execute([$item['quantity'], $item['quantity'], $item['accessory_id']]);
                }
            }

            // Update sale totals
            $total = $subtotal + $vatTotal;
            $db->prepare("UPDATE sales SET subtotal = ?, vat_amount = ?, total = ? WHERE id = ?")
                ->execute([$subtotal, $vatTotal, $total, $saleId]);

            $db->commit();

            logActivity('sale_created', 'sale', $saleId, ['total' => $total]);

            jsonResponse(['success' => true, 'id' => $saleId, 'total' => $total]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'update_sale':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $saleId = $input['sale_id'] ?? 0;
        $clientId = $input['client_id'] ?? 0;
        $saleDate = $input['sale_date'] ?? date('Y-m-d');
        $invoiceNumber = $input['invoice_number'] ?? null;
        $eurRate = $input['eur_rate'] ?? null;
        $usdRate = $input['usd_rate'] ?? null;
        $items = $input['items'] ?? [];

        if (!$saleId || !$clientId || empty($items)) {
            jsonResponse(['success' => false, 'message' => 'Missing required fields'], 400);
        }

        try {
            $db->beginTransaction();

            // 1. Restore stock from existing items
            $existingItems = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            $existingItems->execute([$saleId]);
            $oldItems = $existingItems->fetchAll();

            foreach ($oldItems as $oldItem) {
                if ($oldItem['device_id']) {
                    $db->prepare("UPDATE devices SET quantity_available = quantity_available + ?, status = 'in_stock' WHERE id = ?")
                        ->execute([$oldItem['quantity'], $oldItem['device_id']]);
                }
                if ($oldItem['accessory_id']) {
                    $db->prepare("UPDATE accessories SET quantity_available = quantity_available + ?, status = CASE WHEN quantity_available + ? > 0 THEN 'in_stock' ELSE status END WHERE id = ?")
                        ->execute([$oldItem['quantity'], $oldItem['quantity'], $oldItem['accessory_id']]);
                }
            }

            // 2. Delete old items
            $db->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$saleId]);

            // 3. Update sale header
            $stmt = $db->prepare("
                UPDATE sales SET client_id = ?, sale_date = ?, invoice_number = ?, currency_rate_eur = ?, currency_rate_usd = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$clientId, $saleDate, $invoiceNumber, $eurRate, $usdRate, $saleId]);

            // 4. Insert new items (Copy from create_sale logic)
            $subtotal = 0;
            $vatTotal = 0;

            $itemStmt = $db->prepare("
                INSERT INTO sale_items (sale_id, device_id, accessory_id, quantity, unit_price, vat_mode, vat_amount, total_price)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $itemTotal;
                $vatTotal += $item['vat_amount'] ?? 0;

                $itemStmt->execute([
                    $saleId,
                    $item['device_id'] ?? null,
                    $item['accessory_id'] ?? null,
                    $item['quantity'],
                    $item['unit_price'],
                    $item['vat_mode'],
                    $item['vat_amount'] ?? 0,
                    $itemTotal
                ]);

                // Update stock again
                if (!empty($item['device_id'])) {
                    $db->prepare("UPDATE devices SET quantity_available = quantity_available - ?, status = CASE WHEN quantity_available - ? <= 0 THEN 'sold' ELSE 'in_stock' END WHERE id = ?")
                        ->execute([$item['quantity'], $item['quantity'], $item['device_id']]);
                }
                if (!empty($item['accessory_id'])) {
                    $db->prepare("UPDATE accessories SET quantity_available = quantity_available - ?, status = CASE WHEN quantity_available - ? <= 0 THEN 'sold' ELSE 'in_stock' END WHERE id = ?")
                        ->execute([$item['quantity'], $item['quantity'], $item['accessory_id']]);
                }
            }

            // 5. Update totals
            $total = $subtotal + $vatTotal;
            $db->prepare("UPDATE sales SET subtotal = ?, vat_amount = ?, total = ? WHERE id = ?")
                ->execute([$subtotal, $vatTotal, $total, $saleId]);

            $db->commit();

            logActivity('sale_updated', 'sale', $saleId, ['total' => $total]);

            jsonResponse(['success' => true, 'id' => $saleId]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;
        jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
}
