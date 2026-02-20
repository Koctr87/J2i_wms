<?php
header('Content-Type: text/plain');
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "Error Log Path: " . ini_get('error_log') . "\n\n";

$logFile = ini_get('error_log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    echo "Last 50 lines of $logFile:\n";
    echo implode("", array_slice($lines, -50));
} else {
    echo "Log file not found at $logFile\n";
    // Check if it's relative
    $absPath = realpath($logFile);
    if ($absPath) {
        echo "Resolved Absolute Path: $absPath\n";
    }
}
