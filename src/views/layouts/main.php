<?php

use App\Support\Asset;
use App\Support\Html;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Html::e($pageTitle ?? 'Client Portal') ?></title>
    <link rel="icon" type="image/png" href="/images/LogoFolded.png">
    <?= Asset::cdnTag('bootstrap_css') . "\n" ?>
    <?= Asset::cdnTag('bootstrap_icons') . "\n" ?>
    <!-- Flatpickr 4.6.13 + monthSelect (pinned + SRI) -->
    <?= Asset::cdnTag('flatpickr_css') . "\n" ?>
    <?= Asset::cdnTag('flatpickr_month_css') . "\n" ?>
    <?= Asset::cdnTag('flatpickr_js', 'js') . "\n" ?>
    <?= Asset::cdnTag('flatpickr_month_js', 'js') . "\n" ?>
    <link rel="stylesheet" href="<?= Html::e(Asset::url('/css/main.css')) ?>">
</head>

<?php
$activePage  = $activePage ?? 'dashboard';
$userName    = $_SESSION['user_name'] ?? 'Client';
$initials    = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', trim($userName)), 0, 2)));
$_darkMode   = ($_SESSION['dark_mode']    ?? '0') === '1';
$_sideRight  = ($_SESSION['sidebar_side'] ?? 'left') === 'right';
?>

<body class="<?= $_darkMode  ? 'dark-theme' : '' ?>"
    data-dark-mode="<?= $_darkMode  ? '1' : '0' ?>"
    data-sidebar-side="<?= $_sideRight ? 'right' : 'left' ?>">
    <?php

    $navItems = [
        ['page' => 'dashboard', 'icon' => 'bi-speedometer2',  'label' => 'Dashboard'],
        ['page' => 'payments',  'icon' => 'bi-credit-card',   'label' => 'Payments'],
        ['page' => 'services',  'icon' => 'bi-briefcase',     'label' => 'Services'],
        ['page' => 'cif',       'icon' => 'bi-file-person',   'label' => 'CIF'],
        ['page' => 'documents', 'icon' => 'bi-folder2-open',  'label' => 'Documents / Inbox'],
        ['page' => 'profile',   'icon' => 'bi-person',        'label' => 'Profile'],
        ['page' => 'faq',       'icon' => 'bi-question-circle', 'label' => 'FAQ'],
    ];
    ?>

    <div class="portal-layout<?= $_sideRight ? ' sidebar-right' : '' ?>" id="wrapper">

        <!-- ══════════════════ SIDEBAR ══════════════════ -->
        <nav id="sidebar" class="portal-sidebar">

            <!-- Logo block (expanded) -->
            <div class="sidebar-logo-wrap">
                <div class="sidebar-logo-box">
                    <img src="/images/Logo.png" alt="FPWS" class="sidebar-logo-img">
                </div>
                <button class="sidebar-menu-btn" id="sidebarToggle" type="button" title="Collapse menu">
                    <i class="bi bi-layout-sidebar-reverse"></i>
                    <span class="sidebar-menu-label">Menu</span>
                </button>
            </div>

            <!-- Logo block (collapsed) -->
            <div class="sidebar-logo-wrap-collapsed">
                <div class="sidebar-logo-box-sm">
                    <img src="/images/LogoFolded.png" alt="FPWS" class="sidebar-logo-img-sm">
                </div>
                <button class="sidebar-menu-btn-sm" id="sidebarToggleCollapsed" type="button" title="Expand menu">
                    <i class="bi bi-layout-sidebar"></i>
                </button>
            </div>

            <!-- Section: Main -->
            <div class="sidebar-section-label">Main</div>

            <!-- Main nav: each row = rail dot + link (CSS layout, no JS positioning) -->
            <div class="sidebar-nav-timeline" id="navTimeline">
                <ul class="sidebar-nav-list" id="navRail">
                    <?php foreach ($navItems as $i => $item): ?>
                        <li class="sidebar-nav-row<?= $i === 0 ? ' is-first' : '' ?><?= $i === count($navItems) - 1 ? ' is-last' : '' ?>">
                            <a href="/<?= $item['page'] ?>"
                                class="sidebar-link <?= $activePage === $item['page'] ? 'is-active' : '' ?>"
                                data-page="<?= $item['page'] ?>">
                                <i class="bi <?= $item['icon'] ?> sidebar-icon"></i>
                                <span class="sidebar-label"><?= $item['label'] ?></span>
                            </a>
                            <span class="nav-rail-cell" aria-hidden="true">
                                <button type="button"
                                    class="nav-rail-dot <?= $activePage === $item['page'] ? 'is-active' : '' ?>"
                                    data-page="<?= $item['page'] ?>"
                                    tabindex="-1"
                                    title="<?= Html::e($item['label']) ?>"></button>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div><!-- /sidebar-nav-timeline -->

            <!-- Section: Account -->
            <div class="sidebar-section-label mt-auto">Account</div>
            <ul class="sidebar-nav">
                <li>
                    <a href="/settings"
                        class="sidebar-link <?= $activePage === 'settings' ? 'is-active' : '' ?>"
                        data-page="settings">
                        <i class="bi bi-gear sidebar-icon"></i>
                        <span class="sidebar-label">Settings</span>
                    </a>
                </li>
                <li>
                    <a href="/logout" class="sidebar-link sidebar-link--danger">
                        <i class="bi bi-box-arrow-left sidebar-icon"></i>
                        <span class="sidebar-label">Log Out</span>
                    </a>
                </li>
            </ul>

            <!-- User card -->
            <div class="sidebar-user-card">
                <div class="sidebar-user-avatar"><?= Html::e($initials) ?></div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= Html::e($userName) ?></div>
                    <div class="sidebar-user-role">Client</div>
                </div>
            </div>

        </nav><!-- /sidebar -->

        <!-- ══════════════════ CONTENT AREA ══════════════════ -->
        <div class="portal-content-wrap">

            <!-- TOP BAR -->
            <header class="portal-topbar">
                <span class="portal-topbar-title"><?= Html::e($pageTitle ?? '') ?></span>
                <div class="portal-topbar-right">
                    <span class="topbar-user-badge">
                        <div class="topbar-avatar"><?= Html::e($initials) ?></div>
                        <?= Html::e($userName) ?>
                    </span>
                </div>
            </header>

            <!-- CONTENT STAGE — SPA container -->
            <main class="portal-stage" id="portalStage">
                <div class="portal-page is-current" id="pageContent" data-page="<?= Html::e($activePage) ?>">
                    <?= $content ?? '' ?>
                </div>
            </main>

            <footer class="portal-footer">
                &copy; <?= date('Y') ?> Lev Tiutiunyk (Rimoker). MIT License.
            </footer>
        </div>

    </div><!-- /wrapper -->

    <!-- Shared Bootstrap toast host -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="portalToastContainer" style="z-index: 1100;"></div>

    <?= Asset::cdnTag('bootstrap_js', 'js') . "\n" ?>
    <script src="<?= Html::e(Asset::url('/js/toast.js')) ?>"></script>
    <script src="<?= Html::e(Asset::url('/js/dashboard.js')) ?>"></script>
</body>

</html>
