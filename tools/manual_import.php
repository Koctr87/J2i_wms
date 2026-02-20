<?php
require_once __DIR__ . '/../config/config.php';
$csvFile = __DIR__ . '/../import_example.csv';

if (!file_exists($csvFile))
    die("File not found: $csvFile\n");

$file = fopen($csvFile, 'r');
if (!$file)
    die("Cannot open file.\n");

// Read first few lines to detect structure
$line1 = fgets($file);
$line2 = fgets($file);
rewind($file);

$headerLine = $line1;
$offset = 0;

// Heuristic: If line 1 has no separators but line 2 does, line 1 is title
$sep1 = (strpos($line1, ';') !== false) || (strpos($line1, ',') !== false);
$sep2 = (strpos($line2, ';') !== false) || (strpos($line2, ',') !== false);

if (!$sep1 && $sep2) {
    echo "Detected title row. Skipping line 1.\n";
    $headerLine = $line2;
    $offset = 1; // Skip 1 line before CSV parsing
}

// Detect delimiter on header line
$delimiter = (strpos($headerLine, ';') !== false) ? ';' : ',';
echo "Detected delimiter: [$delimiter]\n";

// Skip title if needed
if ($offset > 0)
    fgets($file);

// Read Headers
$headers = fgetcsv($file, 0, $delimiter);
if (!$headers)
    die("Error reading headers.\n");

// BOM removal
if (substr($headers[0], 0, 3) === "\xEF\xBB\xBF") {
    $headers[0] = substr($headers[0], 3);
}
$headers = array_map(function ($h) {
    return strtolower(trim($h)); }, $headers);
echo "Headers: " . implode(', ', $headers) . "\n";

// Map columns (same logic as before)
$map = [];
foreach ($headers as $i => $h) {
    if (in_array($h, ['model', 'product', 'name']))
        $map['model'] = $i;
    if (in_array($h, ['memory', 'mem', 'capacity']))
        $map['memory'] = $i;
    if (in_array($h, ['condition', 'status']))
        $map['condition'] = $i;
    // vat mode aliases
    if (in_array($h, ['vat mode', 'vat', 'tax']))
        $map['vat_mode'] = $i;
    if (in_array($h, ['grade', 'grading']))
        $map['grade'] = $i;
    if (in_array($h, ['wholesale', 'purchase', 'buy', 'opt']))
        $map['wholesale'] = $i;
    if (in_array($h, ['retail', 'sell', 'price']))
        $map['retail'] = $i;
    if (in_array($h, ['currency', 'curr']))
        $map['currency'] = $i;
}

if (!isset($map['model']) || !isset($map['wholesale'])) {
    die("Error: Missing required columns. Found: " . implode(', ', $headers) . "\n");
}

$db = getDB();
$db->beginTransaction();

try {
    // Load Product Map
    $stmt = $db->query("SELECT id, name FROM products");
    $productMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productMap[strtolower(trim($row['name']))] = $row['id'];
    }

    // Load Memory Map
    $stmt = $db->query("SELECT id, size FROM memory_options");
    $memoryMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = str_replace(' ', '', strtolower(trim($row['size'])));
        $memoryMap[$key] = $row['id'];
    }
    // N/A fallback
    $naKey = 'n/a';
    if (!isset($memoryMap[$naKey]))
        $memoryMap[$naKey] = 1;

    // Prepare Insert
    $stmt = $db->prepare("
        INSERT INTO product_prices (product_id, memory_id, `condition`, vat_mode, grade, wholesale_price, retail_price, currency)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            wholesale_price = VALUES(wholesale_price), 
            retail_price = VALUES(retail_price), 
            currency = VALUES(currency)
    ");

    $count = 0;
    while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
        if (empty(array_filter($row)))
            continue;

        // Helper
        $val = function ($k) use ($map, $row) {
            return isset($map[$k]) && isset($row[$map[$k]]) ? trim($row[$map[$k]]) : null;
        };

        $model = $val('model');
        if (!$model)
            continue;

        $pid = $productMap[strtolower($model)] ?? null;
        if (!$pid) {
            echo "Skipping unknown product: $model\n";
            continue;
        }

        $memRaw = $val('memory') ?? 'n/a';
        $memKey = str_replace(' ', '', strtolower($memRaw));
        $mid = $memoryMap[$memKey] ?? $memoryMap['n/a'] ?? 1;

        $cond = 'used';
        if (strpos(strtolower($val('condition') ?? ''), 'new') !== false)
            $cond = 'new';

        $vat = 'marginal';
        if (strpos(strtolower($val('vat_mode') ?? ''), 'reverse') !== false)
            $vat = 'reverse';

        $grade = 'A';
        if ($cond === 'used') {
            $grade = strtoupper($val('grade') ?? 'A');
            if (!in_array($grade, ['A', 'B', 'C']))
                $grade = 'A';
        } else {
            $grade = 'A'; // New always A
        }

        $wh = (float) str_replace(',', '.', $val('wholesale') ?? 0);
        $rt = (float) str_replace(',', '.', $val('retail') ?? 0);
        $cur = strtoupper($val('currency') ?? 'CZK');
        if (!in_array($cur, ['EUR', 'USD', 'CZK']))
            $cur = 'CZK';

        if ($wh > 0 || $rt > 0) {
            $stmt->execute([$pid, $mid, $cond, $vat, $grade, $wh, $rt, $cur]);
            $count++;
        }
    }

    $db->commit();
    echo "Success! Imported $count prices.\n";

} catch (Exception $e) {
    if ($db->inTransaction())
        $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
fclose($file);
