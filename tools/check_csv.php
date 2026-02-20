<?php
$file = __DIR__ . '/../import_example.csv';
$handle = fopen($file, 'r');
if (!$handle)
    die("Cannot open file");

// Try to auto-detect line 1
$line1 = fgets($handle);
rewind($handle);

$delimiter = (strpos($line1, ';') !== false) ? ';' : ',';
echo "Detected delimiter: [$delimiter]\n";

$headers = fgetcsv($handle, 0, $delimiter);
echo "Headers: " . implode(" | ", $headers) . "\n";

// Validation
$required = ['model', 'memory', 'condition', 'vat mode', 'wholesale', 'retail', 'currency']; // grade optional
$map = [];
foreach ($headers as $i => $h) {
    $map[strtolower(trim($h))] = $i;
}

$missing = [];
foreach ($required as $r) {
    if (!isset($map[$r]))
        $missing[] = $r;
}

if (!empty($missing)) {
    echo "MISSING columns: " . implode(", ", $missing) . "\n";
    echo "Available: " . implode(", ", array_keys($map)) . "\n";
} else {
    echo "All required columns found.\n";
}

// Check first row data
$row1 = fgetcsv($handle, 0, $delimiter);
if ($row1) {
    echo "Row 1 data: " . implode(" | ", $row1) . "\n";
}
fclose($handle);
