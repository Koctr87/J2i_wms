<?php
/**
 * J2i Warehouse Management System
 * Header Template - ShipNow Style
 */
if (!isset($_SESSION))
    session_start();
require_once __DIR__ . '/../config/config.php';

if (!isLoggedIn()) {
    redirect('users/login.php');
}

$currentUser = getCurrentUser();
$current_lang = getCurrentLanguage();

// Get page for active nav
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Count notifications (example)
$messageCount = 0;
$notificationCount = 0;

$flags = ['ru' => '🇷🇺', 'cs' => '🇨🇿', 'uk' => '🇺🇦', 'en' => '🇬🇧'];

// Polyfill for mb_substr if not available
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null, $encoding = null)
    {
        return substr($str, $start, $length);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> - J2i WMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>
</head>

<body>
    <div class="app">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <!-- Logo -->
            <div class="sidebar-header">
                <a href="<?= APP_URL ?>/pages/dashboard.php" class="sidebar-logo">
                    <img src="<?= APP_URL ?>/assets/img/logo.png" alt="J2i Logo"
                        style="height: 40px; width: auto; margin-right: 10px;">
                    J2i<span>WMS</span>
                </a>
            </div>

            <!-- User Profile -->
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?php
                    $names = explode(' ', $currentUser['full_name'] ?? 'User');
                    $first = $names[0] ?? 'U';
                    $last = $names[1] ?? '';
                    echo mb_substr($first, 0, 1) . mb_substr($last, 0, 1);
                    ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">
                        <?= e($currentUser['full_name'] ?? 'User') ?>
                    </div>
                    <div class="sidebar-user-role"><?= ucfirst($currentUser['role'] ?? 'user') ?></div>
                </div>
                <div class="sidebar-user-toggle">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="sidebar-nav">
                <!-- Main Menu -->
                <div class="nav-section">
                    <a href="<?= APP_URL ?>/pages/dashboard.php"
                        class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <rect x="3" y="3" width="7" height="7" />
                            <rect x="14" y="3" width="7" height="7" />
                            <rect x="14" y="14" width="7" height="7" />
                            <rect x="3" y="14" width="7" height="7" />
                        </svg>
                        <span class="nav-item-text"><?= __('dashboard') ?></span>
                    </a>

                    <a href="<?= APP_URL ?>/pages/inventory/devices.php"
                        class="nav-item <?= $currentDir === 'inventory' ? 'active' : '' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path
                                d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                        </svg>
                        <span class="nav-item-text"><?= __('warehouse') ?></span>
                    </a>

                    <a href="<?= APP_URL ?>/pages/catalog/brands.php"
                        class="nav-item <?= $currentDir === 'catalog' ? 'active' : '' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                            <line x1="7" y1="7" x2="7.01" y2="7" />
                        </svg>
                        <span class="nav-item-text"><?= __('catalog') ?></span>
                    </a>

                    <a href="<?= APP_URL ?>/pages/clients/list.php"
                        class="nav-item <?= $currentDir === 'clients' ? 'active' : '' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                        <span class="nav-item-text"><?= __('clients') ?></span>
                    </a>

                    <a href="<?= APP_URL ?>/pages/sales/history.php"
                        class="nav-item <?= $currentDir === 'sales' ? 'active' : '' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23" />
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                        </svg>
                        <span class="nav-item-text"><?= __('sales') ?></span>
                    </a>

                    <a href="<?= APP_URL ?>/pages/prices/index.php"
                        class="nav-item <?= $currentDir === 'prices' ? 'active' : '' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                        </svg>
                        <span class="nav-item-text"><?= __('prices') ?></span>
                    </a>
                </div>

                <!-- System -->
                <div class="nav-section">
                    <div class="nav-section-title"><?= __('settings') ?></div>

                    <?php if ($currentUser['role'] === 'director'): ?>
                        <a href="<?= APP_URL ?>/pages/users/list.php"
                            class="nav-item <?= $currentDir === 'users' && $currentPage !== 'login.php' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="8.5" cy="7" r="4" />
                                <line x1="20" y1="8" x2="20" y2="14" />
                                <line x1="23" y1="11" x2="17" y2="11" />
                            </svg>
                            <span class="nav-item-text"><?= __('users') ?></span>
                        </a>
                    <?php endif; ?>

                    <a href="<?= APP_URL ?>/pages/users/login.php?logout=1" class="nav-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16 17 21 12 16 7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                        <span class="nav-item-text"><?= __('logout') ?></span>
                    </a>
                </div>
            </nav>

            <!-- Language Promo -->
            <div class="sidebar-promo">
                <h4>🌐 <?= __('language') ?></h4>
                <p>Dostupné jazyky / Available languages</p>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <?php foreach (SUPPORTED_LANGUAGES as $lang): ?>
                        <a href="?lang=<?= $lang ?>"
                            class="<?= $current_lang === $lang ? 'sidebar-promo-btn' : 'btn btn-sm btn-secondary' ?>"
                            style="flex: 1; min-width: 40px;">
                            <?= $flags[$lang] ?? $lang ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main">
            <!-- Page Header -->
            <header class="page-header">
                <div class="page-header-left">
                    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <line x1="3" y1="12" x2="21" y2="12" />
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <line x1="3" y1="18" x2="21" y2="18" />
                        </svg>
                    </button>
                    <h1 class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
                    <div class="page-breadcrumb">
                        <a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a>
                        <span>/</span>
                        <span><?= e($pageTitle ?? 'Page') ?></span>
                    </div>
                </div>

                <div class="page-header-right">
                    <!-- Filter Tabs (like in design) -->
                    <div class="filter-tabs">
                        <a href="<?= APP_URL ?>/pages/inventory/devices.php"
                            class="filter-tab <?= basename($_SERVER['PHP_SELF']) === 'devices.php' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="5" y="2" width="14" height="20" rx="2" ry="2" />
                                <line x1="12" y1="18" x2="12.01" y2="18" />
                            </svg>
                            <?= __('devices') ?>
                        </a>
                        <a href="<?= APP_URL ?>/pages/inventory/accessories.php"
                            class="filter-tab <?= basename($_SERVER['PHP_SELF']) === 'accessories.php' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                                <path
                                    d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z">
                                </path>
                            </svg>
                            <?= __('accessories') ?>
                        </a>
                    </div>

                    <!-- Language dropdown -->
                    <!-- Language dropdown -->
                    <div class="lang-selector">
                        <div class="current-lang">
                            <?php echo $flags[$current_lang] ?? '🌐'; ?>
                            <span><?= strtoupper($current_lang) ?></span>
                        </div>
                        <div class="lang-dropdown">
                            <?php foreach (SUPPORTED_LANGUAGES as $lang): ?>
                                <?php if ($lang !== $current_lang): ?>
                                    <a href="?lang=<?= $lang ?>" class="lang-option">
                                        <?= $flags[$lang] ?? '🌐' ?> <span><?= strtoupper($lang) ?></span>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">
                <?php if ($flash = getFlashMessage()): ?>
                    <div class="alert alert-<?= $flash['type'] ?>">
                        <?= e($flash['message']) ?>
                    </div>
                <?php endif; ?>