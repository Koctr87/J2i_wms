<?php
/**
 * J2i WMS - CLI Import Tool
 * Usage: php import_devices.php import.csv
 */
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line.");
}

require_once __DIR__ . '/../config/config.php';

if ($argc < 2) {
    die("Usage: php import_devices.php <filename.csv>\n");
}

$file = $argv[1];
if (!file_exists($file)) {
    die("File not found: $file\n");
}

$handle = fopen($file, "r");
$header = fgetcsv($handle, 1000, ","); // Skip header or map columns

// Expected columns: Brand, Model, IMEI, Memory, Color, Purchase Price, Currency, Status, Purchase Date
// Example: Apple, iPhone 16 Pro, 351234567890123, 256GB, Black Titanium, 28000, CZK, in_stock, 2024-02-01

$db = getDB();
$count = 0;

echo "Starting import...\n";

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    // Map data
    $brandName = trim($data[0]);
    $modelName = trim($data[1]);
    $imei = trim($data[2]);
    $memory = trim($data[3]);
    $color = trim($data[4]);
    $price = (float) $data[5];
    $currency = trim($data[6]) ?: 'CZK';
    $status = trim($data[7]) ?: 'in_stock';
    $date = trim($data[8]) ?: date('Y-m-d');

    // 1. Find Brand ID
    $stmt = $db->prepare("SELECT id FROM brands WHERE name = ?");
    $stmt->execute([$brandName]);
    $brandId = $stmt->fetchColumn();
    if (!$brandId) {
        echo "Skipping row: Brand '$brandName' not found.\n";
        continue;
    }

    // 2. Find Category (Default Phone = 1)
    $categoryId = 1;

    // 3. Find Product/Model
    $fullModelName = $brandName . ' ' . $modelName; // Or just Model Name if consistent
    $stmt = $db->prepare("SELECT id FROM products WHERE name LIKE ? OR name = ?");
    $stmt->execute(["%$modelName%", $fullModelName]);
    $productId = $stmt->fetchColumn();

    if (!$productId) {
        // Auto-create product? Or Skip? Let's skip for safety or creating new one
        // Creating:
        $stmt = $db->prepare("INSERT INTO products (brand_id, category_id, name) VALUES (?,?,?)");
        $stmt->execute([$brandId, $categoryId, $fullModelName]);
        $productId = $db->lastInsertId();
        echo "Created new product: $fullModelName\n";
    }

    // 4. Find Attributes (Memory, Color)
    // Memory
    $memId = null;
    if ($memory) {
        $stmt = $db->prepare("SELECT id FROM memory_options WHERE size = ?");
        $stmt->execute([$memory]);
        $memId = $stmt->fetchColumn();
        if (!$memId) {
            $db->prepare("INSERT INTO memory_options (size) VALUES (?)")->execute([$memory]);
            $memId = $db->lastInsertId();
        }
    }

    // Color
    $colId = null;
    if ($color) {
        $stmt = $db->prepare("SELECT id FROM color_options WHERE name_en = ? OR name_cs = ? OR name_ru = ?");
        $stmt->execute([$color, $color, $color]);
        $colId = $stmt->fetchColumn();
        if (!$colId) {
            // Simple insert defaulting to EN name
            $db->prepare("INSERT INTO color_options (name_en, name_cs, name_ru, hex_code) VALUES (?, ?, ?, '#000000')")->execute([$color, $color, $color]);
            $colId = $db->lastInsertId();
        }
    }

    // 5. Insert Device
    try {
        $stmt = $db->prepare("
            INSERT INTO devices (product_id, brand_id, ime, memory_id, color_id, purchase_price, purchase_currency, purchase_date, status, quantity_available)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        // Note: field is 'imei' usually, check schema. Assuming 'imei' based on previous context.
        // Let's verify device table columns. Assuming 'imei'.

        // Wait, schema check:
        // devices table has 'imei' column? 
        // Based on devices.php filters: d.imei LIKE ? 
        // So column is 'imei'.

        $sql = "INSERT INTO devices (product_id, imei, memory_id, color_id, purchase_price, purchase_currency, purchase_date, status, quantity_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
        $db->prepare($sql)->execute([$productId, $imei, $memId, $colId, $price, $currency, $date, $status]);

        $count++;
        echo "Imported: $fullModelName ($imei)\n";
    } catch (Exception $e) {
        echo "Error importing $fullModelName: " . $e->getMessage() . "\n";
    }
}

fclose($handle);
echo "Import complete. $count devices added.\n";
