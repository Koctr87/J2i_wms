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
    <style>
        .header-search {
            position: relative;
            margin-left: 2rem;
            max-width: 400px;
            width: 100%;
        }

        .header-search-input {
            width: 100%;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            border: 1px solid var(--gray-300);
            border-radius: 999px;
            background: var(--gray-50);
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .header-search-input:focus {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .header-search .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .header-search {
                display: none;
            }
        }
    </style>
    <script>
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
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

                    <a href="<?= APP_URL ?>/pages/clients/list.php?type=wholesale"
                        class="nav-item <?= ($currentDir === 'clients' && ($_GET['type'] ?? '') === 'wholesale') ? 'active' : '' ?>"
                        style="padding-left: 2.5rem; font-size: 0.85rem; opacity: 0.8;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M3 21h18"></path>
                            <path d="M3 7v1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7H3"></path>
                            <path d="M19 21V11"></path>
                            <path d="M5 21V11"></path>
                        </svg>
                        <span class="nav-item-text"><?= __('wholesale') ?></span>
                    </a>

                    <a href="<?= APP_URL ?>/pages/clients/list.php?type=retail"
                        class="nav-item <?= ($currentDir === 'clients' && ($_GET['type'] ?? '') === 'retail') ? 'active' : '' ?>"
                        style="padding-left: 2.5rem; font-size: 0.85rem; opacity: 0.8;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path>
                            <path d="M3 6h18"></path>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                        <span class="nav-item-text"><?= __('retail') ?></span>
                    </a>

                    <a href="<?= APP_URL ?>/pages/suppliers/list.php"
                        class="nav-item <?= $currentDir === 'suppliers' ? 'active' : '' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <polyline points="16 11 18 13 22 9" />
                        </svg>
                        <span class="nav-item-text"><?= __('suppliers') ?></span>
                    </a>

                    <a href="<?= APP_URL ?>/pages/sales/history.php"
                        class="nav-item <?= $currentDir === 'sales' ? 'active' : '' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span class="nav-item-text"><?= __('sales') ?></span>
                    </a>

                    <a href="<?= APP_URL ?>/pages/sales/platforms.php"
                        class="nav-item <?= $currentPage === 'platforms.php' ? 'active' : '' ?>"
                        style="padding-left: 2.5rem; font-size: 0.85rem; opacity: 0.8;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        <span class="nav-item-text">Marketplaces</span>
                    </a>

                    <a href="<?= APP_URL ?>/pages/prices/index.php"
                        class="nav-item <?= $currentDir === 'prices' ? 'active' : '' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23" />
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                        </svg>
                        <span class="nav-item-text"><?= __('prices') ?></span>
                    </a>

                    <a href="<?= APP_URL ?>/pages/reports/index.php"
                        class="nav-item <?= $currentDir === 'reports' ? 'active' : '' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10" />
                            <line x1="12" y1="20" x2="12" y2="4" />
                            <line x1="6" y1="20" x2="6" y2="14" />
                        </svg>
                        <span class="nav-item-text"><?= __('reports') ?></span>
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

                    <!-- Global Search Bar -->
                    <div class="header-search">
                        <form action="<?= APP_URL ?>/pages/search.php" method="GET"
                            style="display: flex; align-items: center;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" class="search-icon">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" name="q" placeholder="<?= __('search') ?>..." class="header-search-input"
                                value="<?= e($_GET['q'] ?? '') ?>">
                        </form>
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

                    <!-- Theme Toggle -->
                    <button id="themeToggle" class="btn btn-outline"
                        style="border:none; padding:8px; margin-right: 15px; border-radius:50%; display:flex; align-items:center; justify-content:center; width:40px; height:40px;"
                        title="Toggle Dark Mode">
                        <svg id="themeIconSun" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                        <svg id="themeIconMoon" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                    </button>

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

            <script>
                const themeBtn = document.getElementById('themeToggle');
                const iconSun = document.getElementById('themeIconSun');
                const iconMoon = document.getElementById('themeIconMoon');

                function updateThemeIcons() {
                    if (document.documentElement.getAttribute('data-theme') === 'dark') {
                        iconSun.style.display = 'block';
                        iconMoon.style.display = 'none';
                    } else {
                        iconSun.style.display = 'none';
                        iconMoon.style.display = 'block';
                    }
                }
                updateThemeIcons();

                themeBtn.addEventListener('click', () => {
                    if (document.documentElement.getAttribute('data-theme') === 'dark') {
                        document.documentElement.removeAttribute('data-theme');
                        localStorage.setItem('theme', 'light');
                    } else {
                        document.documentElement.setAttribute('data-theme', 'dark');
                        localStorage.setItem('theme', 'dark');
                    }
                    updateThemeIcons();
                });
            </script>

            <!-- Page Content -->
            <div class="page-content">
                <?php if ($flash = getFlashMessage()): ?>
                    <div class="alert alert-<?= $flash['type'] ?>">
                        <?= e($flash['message']) ?>
                    </div>
                <?php endif; ?>