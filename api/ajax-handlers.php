<?php
ob_start();
/**
 * J2i Warehouse Management System
 * AJAX Handlers
 */
require_once __DIR__ . '/../config/config.php';

// Only accept AJAX requests
// Only accept AJAX requests or requests with an action
if (!isAjax() && !isset($_GET['action']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
}

$db = getDB();

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);
if (empty($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? ($_GET['action'] ?? ($_POST['action'] ?? null));

// Debug logging to a local file
$debugFile = '/tmp/j2i_debug.log';
if ($action) {
    $logMsg = "[" . date('Y-m-d H:i:s') . "] Action: $action | JSON Error: " . json_last_error_msg() . "\n";
    file_put_contents($debugFile, $logMsg, FILE_APPEND);
}

// Initialize missing tables if needed (one-time check)
static $tablesInitialized = false;
if (!$tablesInitialized) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS supplier_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT UNSIGNED NOT NULL,
            comment TEXT NOT NULL,
            created_by INT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS client_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT UNSIGNED NOT NULL,
            comment TEXT NOT NULL,
            created_by INT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $tablesInitialized = true;
    } catch (Exception $e) {
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

    case 'get_brands':
        $stmt = $db->query("SELECT id, name FROM brands ORDER BY name");
        jsonResponse($stmt->fetchAll());
        break;

    case 'create_product':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $name = trim($input['name'] ?? '');
        $brandId = (int) ($input['brand_id'] ?? 0);
        $categoryId = (int) ($input['category_id'] ?? 0);

        if (!$name || !$brandId || !$categoryId) {
            jsonResponse(['success' => false, 'message' => 'Missing product data'], 400);
        }

        try {
            $stmt = $db->prepare("INSERT INTO products (name, brand_id, category_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $brandId, $categoryId]);
            $id = $db->lastInsertId();
            jsonResponse(['success' => true, 'id' => $id]);
        } catch (PDOException $e) {
            // Probably unique constraint violation
            if ($e->getCode() == 23000) {
                jsonResponse(['success' => false, 'message' => 'Product already exists for this brand'], 400);
            }
            jsonResponse(['success' => false, 'message' => 'Database error'], 500);
        }
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

            jsonResponse(['success' => true, 'id' => $id, 'date' => date('d.m.Y H:i')]);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'message' => 'Database error'], 500);
        }
        break;

    case 'add_supplier_comment':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $supplierId = $input['supplier_id'] ?? 0;
        $comment = trim($input['comment'] ?? '');

        if (!$supplierId || !$comment) {
            jsonResponse(['success' => false, 'message' => 'Missing data'], 400);
        }

        try {
            $stmt = $db->prepare("INSERT INTO supplier_comments (supplier_id, comment, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$supplierId, $comment, $_SESSION['user_id']]);
            $id = $db->lastInsertId();

            jsonResponse(['success' => true, 'id' => $id, 'date' => date('d.m.Y H:i')]);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'message' => 'Database error'], 500);
        }
        break;

    case 'get_client_comments':
        $clientId = (int) ($_GET['client_id'] ?? 0);
        if (!$clientId) {
            jsonResponse([]);
        }

        $stmt = $db->prepare("
            SELECT cc.*, u.first_name, u.last_name 
            FROM client_comments cc
            LEFT JOIN users u ON cc.created_by = u.id
            WHERE cc.client_id = ?
            ORDER BY cc.created_at DESC
        ");
        $stmt->execute([$clientId]);
        jsonResponse($stmt->fetchAll());
        break;

    case 'get_client_details':
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch();
        if ($client) {
            jsonResponse(['success' => true, 'data' => $client]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Client not found'], 404);
        }
        break;

    case 'get_supplier_details':
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        $supplier = $stmt->fetch();
        if ($supplier) {
            jsonResponse(['success' => true, 'data' => $supplier]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Supplier not found'], 404);
        }
        break;

    case 'get_supplier_comments':
        $supplierId = (int) ($_GET['supplier_id'] ?? 0);
        if (!$supplierId) {
            jsonResponse([]);
        }

        $stmt = $db->prepare("
            SELECT sc.*, u.first_name, u.last_name 
            FROM supplier_comments sc
            LEFT JOIN users u ON sc.created_by = u.id
            WHERE sc.supplier_id = ?
            ORDER BY sc.created_at DESC
        ");
        $stmt->execute([$supplierId]);
        jsonResponse($stmt->fetchAll());
        break;

    case 'get_client_history':
        $clientId = (int) ($_GET['id'] ?? 0);
        $stmt = $db->prepare("
            SELECT s.id, s.sale_date, s.invoice_number, s.total, s.vat_amount, s.type, s.status,
                   (SELECT SUM(quantity) FROM sale_items si WHERE si.sale_id = s.id) as items_count
            FROM sales s
            WHERE s.client_id = ?
            ORDER BY s.sale_date DESC
            LIMIT 10
        ");
        $stmt->execute([$clientId]);
        jsonResponse($stmt->fetchAll());
        break;

    case 'get_supplier_history':
        $supplierId = (int) ($_GET['id'] ?? 0);
        $stmt = $db->prepare("
            SELECT p.id, p.purchase_date, p.invoice_number, p.total_amount, p.currency, p.vat_mode,
                   (SELECT SUM(quantity) FROM devices d WHERE d.purchase_id = p.id) as items_count,
                   (SELECT SUM(purchase_price * quantity * 0.21) FROM devices d WHERE d.purchase_id = p.id AND d.vat_mode = 'full') as vat_amount_est
            FROM purchases p
            WHERE p.supplier_id = ?
            ORDER BY p.purchase_date DESC
            LIMIT 10
        ");
        $stmt->execute([$supplierId]);
        jsonResponse($stmt->fetchAll());
        break;

    case 'update_ticket_status':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $ticketId = (int) ($input['id'] ?? 0);
        $status = $input['status'] ?? 'open';

        if (!$ticketId) {
            jsonResponse(['success' => false, 'message' => 'Missing ticket ID'], 400);
        }

        $stmt = $db->prepare("UPDATE supplier_tickets SET status = ? WHERE id = ?");
        $stmt->execute([$status, $ticketId]);
        jsonResponse(['success' => true]);
        break;

    case 'get_devices':
        // Get devices for sale selection
        $status = $_GET['status'] ?? 'in_stock';
        $search = $_GET['search'] ?? '';
        $brandId = $_GET['brand_id'] ?? '';
        $imei = $_GET['imei'] ?? '';
        $model = $_GET['model'] ?? '';
        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 15);
        $offset = ($page - 1) * $limit;

        $sql = "FROM devices d
                JOIN products p ON d.product_id = p.id
                JOIN brands b ON p.brand_id = b.id
                LEFT JOIN memory_options m ON d.memory_id = m.id
                LEFT JOIN color_options c ON d.color_id = c.id
                WHERE d.status = ? AND d.quantity_available > 0";
        $params = [$status];

        if ($search) {
            $sql .= " AND (p.name LIKE ? OR b.name LIKE ? OR d.imei LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($brandId) {
            $sql .= " AND p.brand_id = ?";
            $params[] = $brandId;
        }
        if ($imei) {
            $sql .= " AND d.imei LIKE ?";
            $params[] = "%$imei%";
        }
        if ($model) {
            $sql .= " AND p.name LIKE ?";
            $params[] = "%$model%";
        }

        // Count total
        $countStmt = $db->prepare("SELECT COUNT(*) " . $sql);
        $countStmt->execute($params);
        $totalItems = (int) $countStmt->fetchColumn();

        // Fetch data
        $dataSql = "SELECT d.id, d.quantity_available, d.retail_price, d.vat_mode, d.purchase_price, d.purchase_currency, 
                       d.delivery_cost, d.condition, d.grading, d.product_id, d.memory_id, d.imei,
                       p.name as product_name, b.name as brand_name, m.size as memory, c.name_en as color " . $sql;
        $dataSql .= " ORDER BY d.created_at DESC LIMIT ? OFFSET ?";

        // PDO needs integers for LIMIT/OFFSET if passed as params, or we can just append them safely since they are cast to int
        $dataSql = str_replace("LIMIT ? OFFSET ?", "LIMIT $limit OFFSET $offset", $dataSql);

        $stmt = $db->prepare($dataSql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll();

        jsonResponse([
            'devices' => $devices,
            'total' => $totalItems,
            'page' => $page,
            'total_pages' => ceil($totalItems / $limit)
        ]);
        break;

        break;

    case 'get_device_group_details':
        $productId = $_GET['product_id'] ?? 0;

        // Sanitize optional params that might come as "null" string from JS
        $memoryId = $_GET['memory_id'] ?? null;
        if ($memoryId === 'null' || $memoryId === '')
            $memoryId = null;

        $colorId = $_GET['color_id'] ?? null;
        if ($colorId === 'null' || $colorId === '')
            $colorId = null;

        $condition = $_GET['condition'] ?? 'used';

        $grading = $_GET['grading'] ?? null;
        if ($grading === 'null' || $grading === '')
            $grading = null;

        $status = $_GET['status'] ?? 'in_stock';

        $vatMode = $_GET['vat_mode'] ?? null;
        if ($vatMode === 'null' || $vatMode === '')
            $vatMode = null;

        $sql = "
            SELECT d.*, 
                   p.name as product_name, 
                   b.name as brand_name,
                   m.size as memory, 
                   c.name_cs as color_cs,
                   c.name_en as color_en,
                   c.name_ru as color_ru,
                   pur.invoice_number,
                   pur.attachment_url,
                   s.company_name as supplier_name
            FROM devices d
            JOIN products p ON d.product_id = p.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN memory_options m ON d.memory_id = m.id
            LEFT JOIN color_options c ON d.color_id = c.id
            LEFT JOIN purchases pur ON d.purchase_id = pur.id
            LEFT JOIN suppliers s ON d.supplier_id = s.id
            WHERE d.product_id = ? 
            AND d.condition = ? 
            AND d.status = ?
        ";

        $params = [$productId, $condition, $status];

        if ($memoryId) {
            $sql .= " AND d.memory_id = ?";
            $params[] = $memoryId;
        } else {
            $sql .= " AND d.memory_id IS NULL";
        }

        if ($colorId) {
            $sql .= " AND d.color_id = ?";
            $params[] = $colorId;
        } else {
            $sql .= " AND d.color_id IS NULL";
        }

        if ($grading) {
            $sql .= " AND d.grading = ?";
            $params[] = $grading;
        }

        if ($vatMode) {
            $sql .= " AND d.vat_mode = ?";
            $params[] = $vatMode;
        }

        $sql .= " ORDER BY d.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse($devices);
        break;

    case 'get_master_price':
        $productId = (int) ($_GET['product_id'] ?? 0);
        $memoryId = $_GET['memory_id'] ?? 0;
        $condition = $_GET['condition'] ?? 'used';
        $grade = $_GET['grade'] ?? 'A'; // Default to A if not specified

        // Normalize memoryId (if it's 'null', null, or 0, find N/A)
        if (!$memoryId || $memoryId === 'null') {
            $mem = $db->query("SELECT id FROM memory_options WHERE size = 'N/A' LIMIT 1")->fetch();
            $memoryId = $mem ? $mem['id'] : 1;
        }

        // If condition is 'new', grade is always 'A' in prices table
        if ($condition === 'new') {
            $grade = 'A';
        }

        $stmt = $db->prepare("
            SELECT * FROM product_prices 
            WHERE product_id = ? AND memory_id = ? AND `condition` = ? AND vat_mode = ? AND grade = ?
        ");
        $stmt->execute([$productId, $memoryId, $condition, $vatMode, $grade]);
        $priceData = $stmt->fetch();

        if ($priceData) {
            jsonResponse(['success' => true, 'price' => $priceData]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Price not found in master list']);
        }
        break;

    case 'get_accessories_by_type':
        $typeKey = $_GET['type_key'] ?? '';
        $typeMap = [
            'charging_cable' => 1,
            'transport_box' => 2,
            'packaging_box' => 3,
            'sim_tool' => 5,
            'charging_brick' => 7
        ];
        $typeId = $typeMap[$typeKey] ?? 0;

        if (!$typeId) {
            jsonResponse(['success' => false, 'message' => 'Invalid type key']);
        }

        $sql = "SELECT a.*, t.name_en as type_name
                FROM accessories a
                JOIN accessory_types t ON a.type_id = t.id
                WHERE a.type_id = ? AND a.status = 'in_stock' AND a.quantity_available > 0
                ORDER BY a.created_at ASC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$typeId]);
        $accessory = $stmt->fetch();

        if ($accessory) {
            jsonResponse(['success' => true, 'accessory' => $accessory]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Out of stock']);
        }
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

        $clientId = (int) ($input['client_id'] ?? 0);
        $saleDate = $input['sale_date'] ?? date('Y-m-d');
        $invoiceNumber = $input['invoice_number'] ?? null;
        $eurRate = (float) ($input['eur_rate'] ?? 25.0);
        $usdRate = (float) ($input['usd_rate'] ?? 23.0);
        $saleDeliveryCost = (float) ($input['sale_delivery_cost'] ?? 0);
        $saleDeliveryCurrency = $input['sale_delivery_currency'] ?? 'CZK';
        $saleType = $input['sale_type'] ?? 'wholesale';
        $platformId = !empty($input['platform_id']) ? (int) $input['platform_id'] : null;

        $items = $input['items'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        if (!$clientId || empty($items)) {
            jsonResponse(['success' => false, 'message' => 'Missing required fields'], 400);
        }

        // Handle File Upload
        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            $filename = $_FILES['attachment']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../uploads/sales_invoices/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'sale_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $newFileName)) {
                    $attachmentPath = '/uploads/sales_invoices/' . $newFileName;
                }
            }
        }

        try {
            // Auto-migration: Check if columns exist, if not add them
            try {
                $db->query("SELECT sale_delivery_cost FROM sales LIMIT 1");
            } catch (PDOException $e) {
                // Column missing, add it
                $db->exec("ALTER TABLE sales ADD COLUMN sale_delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER currency_rate_usd");
                $db->exec("ALTER TABLE sales ADD COLUMN sale_delivery_currency VARCHAR(3) NOT NULL DEFAULT 'CZK' AFTER sale_delivery_cost");
            }

            try {
                $db->query("SELECT sale_currency FROM sale_items LIMIT 1");
            } catch (PDOException $e) {
                $db->exec("ALTER TABLE sale_items ADD COLUMN sale_currency VARCHAR(3) NULL AFTER unit_price");
            }

            try {
                $db->query("SELECT item_delivery_cost FROM sale_items LIMIT 1");
            } catch (PDOException $e) {
                // Add delivery cost and currency for each item (as an expense)
                $db->exec("ALTER TABLE sale_items ADD COLUMN item_delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0");
                $db->exec("ALTER TABLE sale_items ADD COLUMN item_delivery_currency VARCHAR(3) NOT NULL DEFAULT 'CZK'");
            }

            $db->beginTransaction();

            $subtotalCZK = 0;
            $subtotal = 0;
            $vatTotal = 0;

            $saleDelCZK = ((float) ($saleDeliveryCost)) * ($saleDeliveryCurrency === 'EUR' ? $eurRate : ($saleDeliveryCurrency === 'USD' ? $usdRate : 1));

            // Insert sale
            $stmt = $db->prepare("
                INSERT INTO sales (type, platform_id, client_id, sale_date, invoice_number, currency_rate_eur, currency_rate_usd, sale_delivery_cost, sale_delivery_currency, sale_delivery_cost_czk, attachment_path, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$saleType, $platformId, $clientId, $saleDate, $invoiceNumber, $eurRate, $usdRate, $saleDeliveryCost, $saleDeliveryCurrency, $saleDelCZK, $attachmentPath, $_SESSION['user_id']]);
            $saleId = $db->lastInsertId();

            // Insert sale items
            $itemStmt = $db->prepare("
                INSERT INTO sale_items (sale_id, device_id, accessory_id, quantity, unit_price, sale_currency, vat_mode, vat_amount, total_price, total_price_czk, item_delivery_cost, item_delivery_currency, item_delivery_cost_czk)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                // Failsafe: Retail accessories are 0 price
                $unitPrice = $item['unit_price'];
                if ($saleType === 'retail' && !empty($item['accessory_id'])) {
                    $unitPrice = 0;
                }

                $itemTotal = $item['quantity'] * $unitPrice;

                $sc = $item['sale_currency'] ?? 'CZK';
                $itemRate = 1;
                if ($sc === 'EUR')
                    $itemRate = $eurRate;
                elseif ($sc === 'USD')
                    $itemRate = $usdRate;

                $subtotalCZK += ($itemTotal * $itemRate);

                $vatTotal += $item['vat_amount'] ?? 0;
                $subtotal += $itemTotal; // Original subtotal (mixed currencies)

                $itemTotalCZK = $itemTotal * $itemRate;

                $idcEnv = $item['item_delivery_currency'] ?? 'CZK';
                $idcRate = 1;
                if ($idcEnv === 'EUR')
                    $idcRate = $eurRate;
                elseif ($idcEnv === 'USD')
                    $idcRate = $usdRate;

                $itemDeliveryCostCZK = ($item['item_delivery_cost'] ?? 0) * $idcRate;

                $itemStmt->execute([
                    $saleId,
                    $item['device_id'] ?? null,
                    $item['accessory_id'] ?? null,
                    $item['quantity'],
                    $unitPrice,
                    $item['sale_currency'] ?? 'CZK',
                    $item['vat_mode'],
                    $item['vat_amount'] ?? 0,
                    $itemTotal,
                    $itemTotalCZK,
                    $item['item_delivery_cost'] ?? 0,
                    $item['item_delivery_currency'] ?? 'CZK',
                    $itemDeliveryCostCZK
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
            $total = $subtotal + $vatTotal + $saleDeliveryCost;
            $totalCZK = $subtotalCZK + $vatTotal + $saleDelCZK;

            // Calculate total platform commission
            $platformCommissionTotal = 0;
            if ($platformId && $saleType === 'retail') {
                $platform = $db->query("SELECT commission_percentage FROM sales_platforms WHERE id = $platformId")->fetch();
                if ($platform) {
                    $platformCommissionTotal = $subtotal * ($platform['commission_percentage'] / 100);
                }
            }

            $db->prepare("UPDATE sales SET subtotal = ?, vat_amount = ?, total = ?, total_czk = ?, platform_commission_amount = ? WHERE id = ?")
                ->execute([$subtotal, $vatTotal, $total, $totalCZK, $platformCommissionTotal, $saleId]);

            $db->commit();

            logActivity('sale_created', 'sale', $saleId, ['total' => $total]);

            jsonResponse(['success' => true, 'id' => $saleId, 'total' => $total]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'create_purchase':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $items = $input['items'] ?? [];
        if (isset($input['items_json'])) {
            $items = json_decode($input['items_json'], true);
        }

        $supplierId = $input['supplier_id'] ?? 0;

        if (!$supplierId || empty($items)) {
            jsonResponse(['success' => false, 'message' => 'Missing required fields'], 400);
        }

        // Handle File Upload
        $attachmentUrl = $input['attachment_url'] ?? '';
        if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            $filename = $_FILES['attachment_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../uploads/invoices/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'inv_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment_file']['tmp_name'], $uploadDir . $newFileName)) {
                    $attachmentUrl = '/uploads/invoices/' . $newFileName;
                }
            }
        }

        try {
            $db->beginTransaction();

            // 1. Create Purchase record
            $stmt = $db->prepare("
                INSERT INTO purchases (supplier_id, invoice_number, purchase_date, vat_mode, `condition`, currency, notes, attachment_url, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $purchaseDate = $input['purchase_date'] ?? date('Y-m-d');
            $invoiceNumber = $input['invoice_number'] ?? null;
            $vatMode = $input['vat_mode'] ?? 'marginal';
            $condition = $input['condition'] ?? 'used';
            $mainCurrency = $input['currency'] ?? 'EUR'; // This might be missing in basic form data, check if needed
            $notes = $input['notes'] ?? '';

            $stmt->execute([
                $supplierId,
                $invoiceNumber,
                $purchaseDate,
                $vatMode,
                $condition,
                $mainCurrency,
                $notes,
                $attachmentUrl,
                $_SESSION['user_id']
            ]);
            $purchaseId = $db->lastInsertId();

            // Fetch CNB rates for the purchase date
            $purchaseDateStr = date('Y-m-d', strtotime($purchaseDate));
            $rateEUR = getCNBRate('EUR', $purchaseDateStr) ?? 25.00;
            $rateUSD = getCNBRate('USD', $purchaseDateStr) ?? 23.00;

            // 2. Insert Devices
            $deviceStmt = $db->prepare("
                INSERT INTO devices (
                    product_id, memory_id, color_id, supplier_id, purchase_id, `condition`, grading, 
                    purchase_date, quantity, quantity_available, invoice_in, purchase_price, 
                    purchase_currency, purchase_price_czk, delivery_cost, delivery_currency, delivery_cost_czk,
                    imei, vat_mode, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            // Fetch Categories for splitting logic
            $productIds = array_column($items, 'product_id');
            $prodCats = [];
            if (!empty($productIds)) {
                $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
                $catStmt = $db->prepare("SELECT id, category_id FROM products WHERE id IN ($placeholders)");
                $catStmt->execute($productIds);
                while ($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
                    $prodCats[$row['id']] = $row['category_id'];
                }
            }

            foreach ($items as $item) {
                $pid = $item['product_id'];
                $catId = $prodCats[$pid] ?? 0;
                $isAccessory = ($catId == 5); // 5 = Accessories

                $qty = (int) ($item['quantity'] ?? 1);

                // Calculate CZK values
                $p_curr = $item['currency'] ?? 'EUR';
                $d_curr = $item['delivery_currency'] ?? 'EUR';
                $p_rate = ($p_curr === 'EUR') ? $rateEUR : (($p_curr === 'USD') ? $rateUSD : 1);
                $d_rate = ($d_curr === 'EUR') ? $rateEUR : (($d_curr === 'USD') ? $rateUSD : 1);

                $purchasePriceCZK = ((float) ($item['purchase_price'] ?? 0)) * $p_rate;
                $deliveryCostCZK = ((float) ($item['delivery_cost'] ?? 0)) * $d_rate;

                // If it's a Device (not Accessory), split into individual rows
                if (!$isAccessory && $qty > 0) {
                    // Base IMEI logic
                    $baseImei = $item['imei'] ?: ('BATCH-' . strtoupper(substr(md5(uniqid()), 0, 6)));

                    for ($i = 1; $i <= $qty; $i++) {
                        $suffix = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $newImei = $baseImei . '-' . $suffix;

                        $deviceStmt->execute([
                            $pid,
                            $item['memory_id'] ?: null,
                            $item['color_id'] ?: null,
                            $supplierId,
                            $purchaseId,
                            $condition,
                            $item['grading'] ?? 'A',
                            $purchaseDate,
                            1, // Quantity 1
                            1, // Available 1
                            $invoiceNumber,
                            (float) ($item['purchase_price'] ?? 0),
                            $p_curr,
                            $purchasePriceCZK,
                            (float) ($item['delivery_cost'] ?? 0),
                            $d_curr,
                            $deliveryCostCZK,
                            $newImei,
                            $vatMode,
                            $notes,
                            $_SESSION['user_id']
                        ]);
                    }
                } else {
                    // Accessories or 0 qty: Single row
                    $deviceStmt->execute([
                        $pid,
                        $item['memory_id'] ?: null,
                        $item['color_id'] ?: null,
                        $supplierId,
                        $purchaseId,
                        $condition,
                        $item['grading'] ?? 'A',
                        $purchaseDate,
                        $qty,
                        $qty,
                        $invoiceNumber,
                        (float) ($item['purchase_price'] ?? 0),
                        $p_curr,
                        $purchasePriceCZK,
                        (float) ($item['delivery_cost'] ?? 0),
                        $d_curr,
                        $deliveryCostCZK,
                        $item['imei'] ?: null,
                        $vatMode,
                        $notes,
                        $_SESSION['user_id']
                    ]);
                }
            }

            $db->commit();
            logActivity('purchase_created', 'purchase', $purchaseId, ['invoice' => $invoiceNumber]);
            jsonResponse(['success' => true, 'purchase_id' => $purchaseId]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'import_prices':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $items = $input['items'] ?? [];
        if (empty($items)) {
            jsonResponse(['success' => false, 'message' => 'No items provided'], 400);
        }

        try {
            // Pre-fetch maps for faster lookup
            $products = $db->query("SELECT id, name, brand_id FROM products")->fetchAll();
            $productMap = [];
            foreach ($products as $p) {
                $productMap[strtolower(trim($p['name']))] = $p['id'];
            }

            $memories = $db->query("SELECT id, size FROM memory_options")->fetchAll();
            $memoryMap = [];
            foreach ($memories as $m) {
                $memoryMap[strtolower(trim($m['size']))] = $m['id'];
            }

            $db->beginTransaction();
            $count = 0;

            $stmt = $db->prepare("
                INSERT INTO product_prices (product_id, memory_id, `condition`, vat_mode, grade, wholesale_price, retail_price, currency)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    wholesale_price = VALUES(wholesale_price),
                    retail_price = VALUES(retail_price),
                    currency = VALUES(currency)
            ");

            foreach ($items as $item) {
                // Normalize item keys (case-insensitive search)
                $data = [];
                foreach ($item as $k => $v) {
                    $data[strtolower(trim($k))] = $v;
                }

                // Header mapping
                $modelName = $data['model'] ?? $data['product'] ?? $data['name'] ?? null;
                $memSize = $data['memory'] ?? $data['mem'] ?? $data['capacity'] ?? 'n/a';
                $condition = strtolower($data['condition'] ?? $data['status'] ?? 'used');
                $vatMode = strtolower($data['vat mode'] ?? $data['vat'] ?? $data['tax'] ?? 'marginal');
                $wholesale = (float) ($data['wholesale'] ?? $data['purchasing'] ?? $data['opt'] ?? 0);
                $retail = (float) ($data['retail'] ?? $data['selling'] ?? $data['roznica'] ?? 0);
                $currency = strtoupper($data['currency'] ?? $data['curr'] ?? 'CZK');
                $grade = strtoupper(trim($data['grade'] ?? $data['grading'] ?? 'A'));

                // Validations & Conversions
                if (!$modelName)
                    continue;

                $productId = $productMap[strtolower(trim($modelName))] ?? null;
                if (!$productId)
                    continue; // Skip unknown products

                $memoryId = $memoryMap[strtolower(trim($memSize))] ?? $memoryMap['n/a'] ?? 1;

                // Condition normalization
                if (strpos($condition, 'new') !== false || strpos($condition, 'ново') !== false) {
                    $condition = 'new';
                } else {
                    $condition = 'used';
                }

                // VAT normalization
                if (strpos($vatMode, 'reverse') !== false || strpos($vatMode, 'реверс') !== false) {
                    $vatMode = 'reverse';
                } else {
                    $vatMode = 'marginal';
                }

                if (!in_array($currency, ['EUR', 'USD', 'CZK'])) {
                    $currency = 'CZK';
                }

                // Grade normalization: A, B, C only; NEW items always grade A
                if (!in_array($grade, ['A', 'B', 'C'])) {
                    $grade = 'A';
                }
                if ($condition === 'new') {
                    $grade = 'A'; // NEW items don't have grades
                }

                if ($wholesale > 0 || $retail > 0) {
                    $stmt->execute([$productId, $memoryId, $condition, $vatMode, $grade, $wholesale, $retail, $currency]);
                    $count++;
                }
            }

            $db->commit();
            jsonResponse(['success' => true, 'count' => $count]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'update_sale':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $saleId = (int) ($input['sale_id'] ?? 0);
        $clientId = (int) ($input['client_id'] ?? 0);
        $saleDate = $input['sale_date'] ?? date('Y-m-d');
        $invoiceNumber = $input['invoice_number'] ?? null;
        $eurRate = (float) ($input['eur_rate'] ?? 25.0);
        $usdRate = (float) ($input['usd_rate'] ?? 23.0);
        $saleDeliveryCost = (float) ($input['sale_delivery_cost'] ?? 0);
        $saleDeliveryCurrency = $input['sale_delivery_currency'] ?? 'CZK';
        $platformId = !empty($input['platform_id']) ? (int) $input['platform_id'] : null;

        $items = $input['items'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        if (!$saleId || !$clientId || empty($items)) {
            jsonResponse(['success' => false, 'message' => 'Missing required fields'], 400);
        }

        // Handle File Upload
        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            $filename = $_FILES['attachment']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../uploads/sales_invoices/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'sale_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $newFileName)) {
                    $attachmentPath = '/uploads/sales_invoices/' . $newFileName;
                }
            }
        }

        try {
            // Auto-migration: Check if columns exist
            try {
                $db->query("SELECT sale_delivery_cost FROM sales LIMIT 1");
            } catch (PDOException $e) {
                $db->exec("ALTER TABLE sales ADD COLUMN sale_delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER currency_rate_usd");
                $db->exec("ALTER TABLE sales ADD COLUMN sale_delivery_currency VARCHAR(3) NOT NULL DEFAULT 'CZK' AFTER sale_delivery_cost");
            }
            try {
                $db->query("SELECT sale_currency FROM sale_items LIMIT 1");
            } catch (PDOException $e) {
                $db->exec("ALTER TABLE sale_items ADD COLUMN sale_currency VARCHAR(3) NULL AFTER unit_price");
            }

            try {
                $db->query("SELECT item_delivery_cost FROM sale_items LIMIT 1");
            } catch (PDOException $e) {
                $db->exec("ALTER TABLE sale_items ADD COLUMN item_delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0");
                $db->exec("ALTER TABLE sale_items ADD COLUMN item_delivery_currency VARCHAR(3) NOT NULL DEFAULT 'CZK'");
            }

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

            $saleDelCZK = ((float) $saleDeliveryCost) * ($saleDeliveryCurrency === 'EUR' ? $eurRate : ($saleDeliveryCurrency === 'USD' ? $usdRate : 1));

            // 3. Update sale header
            $sql = "UPDATE sales SET client_id = ?, platform_id = ?, sale_date = ?, invoice_number = ?, currency_rate_eur = ?, currency_rate_usd = ?, sale_delivery_cost = ?, sale_delivery_currency = ?, sale_delivery_cost_czk = ?, updated_at = NOW()";
            $params = [$clientId, $platformId, $saleDate, $invoiceNumber, $eurRate, $usdRate, $saleDeliveryCost, $saleDeliveryCurrency, $saleDelCZK];

            if ($attachmentPath) {
                $sql .= ", attachment_path = ?";
                $params[] = $attachmentPath;
            }

            $sql .= " WHERE id = ?";
            $params[] = $saleId;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            // 4. Insert new items (Copy from create_sale logic)
            $subtotalCZK = 0;
            $subtotal = 0;
            $vatTotal = 0;

            // Fetch sale type for rule enforcement
            $st = $db->prepare("SELECT type FROM sales WHERE id = ?");
            $st->execute([$saleId]);
            $saleType = $st->fetchColumn();

            $itemStmt = $db->prepare("
                INSERT INTO sale_items (sale_id, device_id, accessory_id, quantity, unit_price, sale_currency, vat_mode, vat_amount, total_price, total_price_czk, item_delivery_cost, item_delivery_currency, item_delivery_cost_czk)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                // Rule: Retail accessories are 0 price
                $unitPrice = $item['unit_price'];
                if ($saleType === 'retail' && !empty($item['accessory_id'])) {
                    $unitPrice = 0;
                }

                $itemTotal = $item['quantity'] * $unitPrice;

                $sc = $item['sale_currency'] ?? 'CZK';
                $itemRate = 1;
                if ($sc === 'EUR')
                    $itemRate = $eurRate;
                elseif ($sc === 'USD')
                    $itemRate = $usdRate;

                $subtotalCZK += ($itemTotal * $itemRate);
                $vatTotal += $item['vat_amount'] ?? 0;
                $subtotal += $itemTotal; // Original subtotal

                $itemTotalCZK = $itemTotal * $itemRate;

                $idcEnv = $item['item_delivery_currency'] ?? 'CZK';
                $idcRate = 1;
                if ($idcEnv === 'EUR')
                    $idcRate = $eurRate;
                elseif ($idcEnv === 'USD')
                    $idcRate = $usdRate;

                $itemDeliveryCostCZK = ($item['item_delivery_cost'] ?? 0) * $idcRate;

                $itemStmt->execute([
                    $saleId,
                    $item['device_id'] ?? null,
                    $item['accessory_id'] ?? null,
                    $item['quantity'],
                    $unitPrice,
                    $item['sale_currency'] ?? 'CZK',
                    $item['vat_mode'],
                    $item['vat_amount'] ?? 0,
                    $itemTotal,
                    $itemTotalCZK,
                    $item['item_delivery_cost'] ?? 0,
                    $item['item_delivery_currency'] ?? 'CZK',
                    $itemDeliveryCostCZK
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
            $total = $subtotal + $vatTotal + $saleDeliveryCost;
            $totalCZK = $subtotalCZK + $vatTotal + $saleDelCZK;

            // Calculate platform commission
            $platformCommissionTotal = 0;
            if ($platformId) {
                // Fetch percentage
                $pStmt = $db->prepare("SELECT commission_percentage FROM sales_platforms WHERE id = ?");
                $pStmt->execute([$platformId]);
                $pct = (float) ($pStmt->fetchColumn() ?: 0);
                $platformCommissionTotal = $subtotal * ($pct / 100);
            }

            $db->prepare("UPDATE sales SET subtotal = ?, vat_amount = ?, total = ?, total_czk = ?, platform_commission_amount = ? WHERE id = ?")
                ->execute([$subtotal, $vatTotal, $total, $totalCZK, $platformCommissionTotal, $saleId]);

            $db->commit();

            logActivity('sale_updated', 'sale', $saleId, ['total' => $total]);

            jsonResponse(['success' => true, 'id' => $saleId]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'get_device_details':
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Missing ID'], 400);
        }

        $stmt = $db->prepare("
            SELECT d.*, 
                   p.name as product_name, 
                   b.name as brand_name, 
                   m.size as memory, 
                   c.name_en as color_en, c.name_ru as color_ru, c.name_cs as color_cs, c.name_uk as color_uk,
                   pur.attachment_url, pur.invoice_number as purchase_invoice, pur.purchase_date as purchase_created,
                   sup.company_name as supplier_name
            FROM devices d
            LEFT JOIN products p ON d.product_id = p.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN memory_options m ON d.memory_id = m.id
            LEFT JOIN color_options c ON d.color_id = c.id
            LEFT JOIN purchases pur ON d.purchase_id = pur.id
            LEFT JOIN suppliers sup ON pur.supplier_id = sup.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($device) {
            $lang = $_SESSION['language'] ?? 'en';
            $device['color'] = $device['color_' . $lang] ?? $device['color_en'];

            // Fetch other devices from the same purchase
            if (!empty($device['purchase_id'])) {
                $relatedStmt = $db->prepare("
                    SELECT d.quantity, p.name as product_name, b.name as brand_name, 
                           m.size as memory, c.name_en as color_en
                    FROM devices d
                    JOIN products p ON d.product_id = p.id
                    JOIN brands b ON p.brand_id = b.id
                    LEFT JOIN memory_options m ON d.memory_id = m.id
                    LEFT JOIN color_options c ON d.color_id = c.id
                    WHERE d.purchase_id = ? 
                    ORDER BY d.id ASC
                ");
                $relatedStmt->execute([$device['purchase_id']]);
                $device['related_items'] = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $device['related_items'] = [];
            }

            jsonResponse(['success' => true, 'data' => $device]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Not found'], 404);
        }
        break;

    case 'get_sale_details':
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Missing ID'], 400);
        }

        $stmt = $db->prepare("
            SELECT s.*, 
                   c.company_name as client_name, c.ico as client_ico,
                   u.full_name as creator_name
            FROM sales s
            LEFT JOIN clients c ON s.client_id = c.id
            LEFT JOIN users u ON s.created_by = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sale) {
            $itemsStmt = $db->prepare("
                SELECT si.*, 
                       p.name as product_name, b.name as brand_name,
                       a.name as accessory_name, t.name_en as accessory_type
                FROM sale_items si
                LEFT JOIN devices d ON si.device_id = d.id
                LEFT JOIN products p ON d.product_id = p.id
                LEFT JOIN brands b ON p.brand_id = b.id
                LEFT JOIN accessories a ON si.accessory_id = a.id
                LEFT JOIN accessory_types t ON a.type_id = t.id
                WHERE si.sale_id = ?
            ");
            $itemsStmt->execute([$id]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $subtotal = 0;
            $itemsCount = 0;
            foreach ($items as &$item) {
                if ($item['device_id']) {
                    $item['name'] = $item['brand_name'] . ' ' . $item['product_name'];
                } else {
                    $item['name'] = ($item['accessory_type'] ? $item['accessory_type'] . ': ' : '') . $item['accessory_name'];
                    // Rule: Retail accessories are 0 price
                    if ($sale['type'] === 'retail') {
                        $item['unit_price'] = 0;
                        $item['total_price'] = 0;
                    }
                }

                // Recalculate subtotal in CZK
                $rate = 1;
                if ($item['sale_currency'] === 'EUR')
                    $rate = $sale['currency_rate_eur'];
                elseif ($item['sale_currency'] === 'USD')
                    $rate = $sale['currency_rate_usd'];

                $subtotal += ($item['unit_price'] * $item['quantity'] * $rate);
            }

            // Recalculate Total
            $delRate = 1;
            if (($sale['sale_delivery_currency'] ?? 'CZK') === 'EUR')
                $delRate = $sale['currency_rate_eur'];
            elseif (($sale['sale_delivery_currency'] ?? 'CZK') === 'USD')
                $delRate = $sale['currency_rate_usd'];
            $deliveryCZK = ($sale['sale_delivery_cost'] ?? 0) * $delRate;

            $sale['subtotal'] = $subtotal;
            $sale['total'] = $subtotal + $deliveryCZK;
            $sale['items'] = $items;
            jsonResponse(['success' => true, 'data' => $sale]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Not found'], 404);
        }
        break;

    case 'delete_sale':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $saleId = (int) ($input['sale_id'] ?? 0);
        if (!$saleId) {
            jsonResponse(['success' => false, 'message' => 'Missing sale ID'], 400);
        }

        try {
            $db->beginTransaction();

            // 1. Restore stock for each item
            $itemsStmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            $itemsStmt->execute([$saleId]);
            $saleItems = $itemsStmt->fetchAll();

            foreach ($saleItems as $item) {
                if (!empty($item['device_id'])) {
                    $db->prepare("
                        UPDATE devices 
                        SET quantity_available = quantity_available + ?, 
                            status = CASE WHEN quantity_available + ? > 0 THEN 'in_stock' ELSE status END 
                        WHERE id = ?
                    ")->execute([$item['quantity'], $item['quantity'], $item['device_id']]);
                }
                if (!empty($item['accessory_id'])) {
                    $db->prepare("
                        UPDATE accessories 
                        SET quantity_available = quantity_available + ?, 
                            status = CASE WHEN quantity_available + ? > 0 THEN 'in_stock' ELSE status END 
                        WHERE id = ?
                    ")->execute([$item['quantity'], $item['quantity'], $item['accessory_id']]);
                }
            }

            // 2. Delete sale items
            $db->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$saleId]);

            // 3. Delete sale
            $db->prepare("DELETE FROM sales WHERE id = ?")->execute([$saleId]);

            $db->commit();

            logActivity('sale_deleted', 'sale', $saleId, []);
            jsonResponse(['success' => true]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'add_repair_cost':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $deviceId = (int) ($_POST['device_id'] ?? 0);
        $cost = (float) ($_POST['repair_cost'] ?? 0);
        $curr = $_POST['repair_currency'] ?? 'CZK';
        if (!$deviceId || $cost < 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid data'], 400);
        }
        try {
            $rate = 1;
            if ($curr === 'EUR') {
                $rate = getCNBRate('EUR') ?? 25.00;
            } elseif ($curr === 'USD') {
                $rate = getCNBRate('USD') ?? 23.00;
            }
            $costCzk = $cost * $rate;

            $stmt = $db->prepare("UPDATE devices SET repair_cost = ?, repair_currency = ?, repair_cost_czk = ? WHERE id = ?");
            $stmt->execute([$cost, $curr, $costCzk, $deviceId]);

            logActivity('device_repair_added', 'device', $deviceId, ['cost' => $cost, 'currency' => $curr]);
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'return_device_rma':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $deviceId = (int) ($_POST['device_id'] ?? 0);
        if (!$deviceId) {
            jsonResponse(['success' => false, 'message' => 'Invalid data'], 400);
        }
        try {
            $db->beginTransaction();
            $attachmentUrl = null;
            if (isset($_FILES['credit_note']) && $_FILES['credit_note']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
                $filename = $_FILES['credit_note']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $uploadDir = __DIR__ . '/../uploads/rma/';
                    if (!is_dir($uploadDir))
                        mkdir($uploadDir, 0777, true);
                    $newFilename = 'rma_' . $deviceId . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['credit_note']['tmp_name'], $uploadDir . $newFilename)) {
                        $attachmentUrl = '/uploads/rma/' . $newFilename;
                    }
                }
            }

            $stmt = $db->prepare("UPDATE devices SET status = 'returned', quantity_available = 0, credit_note_file = ? WHERE id = ?");
            $stmt->execute([$attachmentUrl, $deviceId]);

            $db->commit();
            logActivity('device_rma', 'device', $deviceId, ['attachment' => $attachmentUrl]);
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action: ' . $action], 400);
}
