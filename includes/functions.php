<?php
/**
 * J2i Warehouse Management System
 * Helper Functions
 */

/**
 * Get translation string
 * @param string $key
 * @return string
 */
function __($key): string
{
    global $lang;
    return $lang[$key] ?? $key;
}

/**
 * Get current language code
 * @return string
 */
function getCurrentLanguage(): string
{
    global $current_lang;
    return $current_lang ?? 'cs';
}

/**
 * Get localized field (for multilingual database fields)
 * @param array $row
 * @param string $field
 * @return string
 */
function getLocalizedField(array $row, string $field): string
{
    global $current_lang;
    $key = $field . '_' . $current_lang;
    return $row[$key] ?? $row[$field . '_en'] ?? '';
}

/**
 * Sanitize output for HTML
 * @param string|null $str
 * @return string
 */
function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency
 * @param float $amount
 * @param string $currency
 * @return string
 */
function formatCurrency(float $amount, string $currency = 'CZK'): string
{
    $symbols = ['CZK' => 'Kč', 'EUR' => '€', 'USD' => '$'];
    $symbol = $symbols[$currency] ?? $currency;

    if ($currency === 'CZK') {
        return number_format($amount, 0, ',', ' ') . ' ' . $symbol;
    }
    return number_format($amount, 2, ',', ' ') . ' ' . $symbol;
}

/**
 * Format date
 * @param string $date
 * @param bool $withTime
 * @return string
 */
function formatDate(string $date, bool $withTime = false): string
{
    $format = $withTime ? 'd.m.Y H:i' : 'd.m.Y';
    return date($format, strtotime($date));
}

/**
 * Get ČNB currency rate
 * @param string $currency EUR or USD
 * @param string|null $date Date in Y-m-d format
 * @return float|null
 */
function getCNBRate(string $currency, ?string $date = null): ?float
{
    $db = getDB();
    $date = $date ?? date('Y-m-d');

    // Check cache first
    $stmt = $db->prepare("SELECT rate FROM currency_rates WHERE rate_date = ? AND currency_code = ?");
    $stmt->execute([$date, $currency]);
    $cached = $stmt->fetchColumn();

    if ($cached !== false) {
        return (float) $cached;
    }

    // Fetch from ČNB
    $rate = fetchCNBRate($currency, $date);

    if ($rate !== null) {
        // Cache the rate
        $stmt = $db->prepare("INSERT INTO currency_rates (rate_date, currency_code, rate) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rate = VALUES(rate)");
        $stmt->execute([$date, $currency, $rate]);
    }

    return $rate;
}

/**
 * Fetch rate from ČNB API
 * @param string $currency
 * @param string $date
 * @return float|null
 */
function fetchCNBRate(string $currency, string $date): ?float
{
    $formattedDate = date('d.m.Y', strtotime($date));
    $url = CNB_API_URL . '?date=' . $formattedDate;

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'header' => "Accept: text/plain\r\n"
        ]
    ]);

    $content = @file_get_contents($url, false, $context);

    if ($content === false) {
        return null;
    }

    $lines = explode("\n", $content);

    foreach ($lines as $line) {
        if (strpos($line, '|' . $currency . '|') !== false) {
            $parts = explode('|', $line);
            if (count($parts) >= 5) {
                // Format: country|currency|amount|code|rate
                $amount = (int) $parts[2];
                $rate = (float) str_replace(',', '.', $parts[4]);
                return $rate / $amount;
            }
        }
    }

    return null;
}

/**
 * Calculate VAT based on mode
 * @param float $purchasePrice Purchase price in original currency
 * @param float $sellingPrice Selling price in CZK
 * @param string $vatMode reverse, marginal, or no
 * @param float $currencyRate Currency rate (purchase currency to CZK)
 * @return array ['vat_amount' => float, 'margin' => float]
 */
function calculateVAT(float $purchasePrice, float $sellingPrice, string $vatMode, float $currencyRate = 1): array
{
    $vatRate = VAT_RATE; // 21%

    switch ($vatMode) {
        case 'reverse':
            // Reverse charge - buyer pays VAT
            // VAT = selling price * 21%
            $vatAmount = $sellingPrice * ($vatRate / 100);
            return [
                'vat_amount' => round($vatAmount, 2),
                'margin' => $sellingPrice - ($purchasePrice * $currencyRate),
                'type' => 'reverse'
            ];

        case 'marginal':
            // Marginal VAT - VAT only from margin
            // Margin = selling price - (purchase price * rate)
            // VAT = margin * (21 / 121)
            $purchaseInCZK = $purchasePrice * $currencyRate;
            $margin = $sellingPrice - $purchaseInCZK;
            $vatAmount = $margin * ($vatRate / (100 + $vatRate));
            return [
                'vat_amount' => round($vatAmount, 2),
                'margin' => round($margin, 2),
                'type' => 'marginal'
            ];

        case 'no':
        default:
            // No VAT
            return [
                'vat_amount' => 0,
                'margin' => $sellingPrice - ($purchasePrice * $currencyRate),
                'type' => 'no'
            ];
    }
}

/**
 * Generate a random token
 * @param int $length
 * @return string
 */
function generateToken(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Get flash message
 * @return array|null
 */
function getFlashMessage(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Set flash message
 * @param string $type success, error, warning, info
 * @param string $message
 */
function setFlashMessage(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Redirect to URL
 * @param string $url
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Check if request is AJAX
 * @return bool
 */
function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 * @param array $data
 * @param int $code HTTP status code
 */
function jsonResponse(array $data, int $code = 200): void
{
    // Clear ALL output buffers to prevent corrupted JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

/**
 * Log activity
 * @param string $action
 * @param string $entityType
 * @param int|null $entityId
 * @param array|null $details
 */
function logActivity(string $action, string $entityType, ?int $entityId = null, ?array $details = null): void
{
    $db = getDB();
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $action,
        $entityType,
        $entityId,
        $details ? json_encode($details) : null,
        $ip
    ]);
}

/**
 * Get pagination data
 * @param int $total
 * @param int $page
 * @param int $perPage
 * @return array
 */
function getPagination(int $total, int $page, int $perPage = 20): array
{
    $totalPages = max(1, ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages
    ];
}
