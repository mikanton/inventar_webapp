<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?? 'Inventar' ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?= Auth::getCsrfToken() ?>">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <?php if (isset($extraHead))
        echo $extraHead; ?>
</head>

<body>
    <header class="topbar">
        <div class="brand">
            Inventar
        </div>
        <div class="spacer"></div>

        <!-- Location Display & Switcher -->
        <?php if (Auth::isLocationSelected()): ?>
            <a href="index.php?route=change_location" class="location-badge" title="Standort wechseln">
                📍 <?= htmlspecialchars($_SESSION['location_name'] ?? 'Standort') ?>
            </a>
        <?php endif; ?>

        <button id="menuBtn" class="btn-icon" aria-label="Menu">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
    </header>

    <div id="drawerBackdrop" class="backdrop"></div>
    <aside id="drawer" class="drawer">
        <div class="drawer-header">
            <h2>Menu</h2>
            <button id="drawerClose" class="btn-icon">✕</button>
        </div>
        <nav class="drawer-nav">
            <?php if (Auth::isLocationSelected()): ?>
                <div class="drawer-section" style="padding: 0 12px; margin-bottom: 10px;">
                    <span
                        style="font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px;">Standort</span>
                    <a href="index.php?route=change_location" class="drawer-item"
                        style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                        📍 <?= htmlspecialchars($_SESSION['location_name'] ?? 'Standort') ?>
                        <span style="font-size: 12px; color: var(--primary); margin-left: auto;">Wechseln</span>
                    </a>
                </div>
                <hr style="border: 0; border-top: 1px solid var(--border); margin: 0 12px 10px;">
            <?php endif; ?>

            <a href="index.php" class="<?= $router->is('home') ? 'active' : '' ?>">Liste</a>
            <a href="#" data-trigger="modalRequest">Neue Request</a>
            <a href="#" data-trigger="modalSupplier">Lieferanten / Pickliste</a>
            <a href="#" data-trigger="modalSupplier">Lieferanten / Pickliste</a>
            
            <?php if (Auth::isAdmin()): ?>
                <a href="index.php?route=admin" class="<?= $router->is('admin') ? 'active' : '' ?>">Admin</a>
            <?php endif; ?>

            <a href="index.php?route=status" class="<?= $router->is('status') ? 'active' : '' ?>" target="_blank">Status
                Monitor ⬈</a>

            <?php if (Auth::isLoggedIn()): ?>
                <a href="index.php?route=logout" class="logout">Logout</a>
            <?php else: ?>
                <a href="index.php?route=login">Login</a>
            <?php endif; ?>
        </nav>
    </aside>

    <main class="main">
        <?= $content ?? '' ?>
    </main>

    <?= $overlays ?? '' ?>

    <script src="assets/js/common.js"></script>
    <?php if (isset($extraScripts))
        echo $extraScripts; ?>
</body>

</html>