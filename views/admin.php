<?php
Auth::requireLogin();
$title = 'Inventar – Admin';
$active = 'admin';
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="assets/js/admin.js"></script><script>const IS_ADMIN = true;</script><script src="assets/js/scanner.js"></script>';

// Get Local IP
$ip = getHostByName(getHostName());
if ($ip == '127.0.0.1') {
    $ip = $_SERVER['SERVER_ADDR'] ?? 'localhost';
}
// Check if running on HTTPS port (proxy)
$port = 8000;
$protocol = 'http';

// If proxy.js exists, we assume the user should use the secure link
if (file_exists(__DIR__ . '/../proxy.js')) {
    $port = 8443;
    $protocol = 'https';
}

$mobileUrl = "$protocol://$ip:$port";

ob_start();
?>

<nav class="tabs"
    style="margin-bottom: 20px; display: flex; gap: 10px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
    <button class="tab active btn" data-tab="inv">Inventar</button>
    <button class="tab btn" data-tab="logs">Logs</button>
    <button class="tab btn" data-tab="analytics">Analytics</button>
    <button class="tab btn" data-tab="users">Benutzer</button>
</nav>

<!-- Inventar -->
<section id="tab-inv" class="panel tabcontent active">
    <div class="toolbar" style="background: var(--panel); padding: 16px; border-radius: 12px; margin-bottom: 20px;">
        <div class="row gap" style="justify-content: flex-start; align-items: center;">
            <div class="segmented" style="display: flex; gap: 8px;">
                <button id="aSortAlpha" class="seg btn active">A–Z</button>
                <button id="aSortQty" class="seg btn">Menge</button>
            </div>

            <div style="flex:1; text-align: center;">
                <small
                    style="color: var(--text-muted); background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 4px;">
                    Mobile: <a href="<?php echo $mobileUrl; ?>" target="_blank"
                        style="color: var(--primary);"><?php echo $mobileUrl; ?></a>
                </small>
            </div>

            <input id="aName" type="text" placeholder="Artikelname" style="width: auto;">
            <input id="aBarcode" type="text" placeholder="Barcode" style="width: 120px;">
            <input id="aQty" type="number" min="0" value="0" style="width: 80px;">
            <button id="aAdd" class="btn primary">Hinzufügen/Setzen</button>
            <button class="btn" onclick="scanner.start(true)" title="Setup Scanner">📷</button>
        </div>
    </div>
    <div id="adminList" class="list"></div>
</section>

<!-- Logs -->
<section id="tab-logs" class="panel tabcontent">
    <ul id="logs" class="logs" style="list-style: none; padding: 0;"></ul>
</section>

<!-- Analytics -->
<section id="tab-analytics" class="panel tabcontent">
    <div class="grid2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div>
            <h3>Änderungen pro Tag</h3>
            <div style="height: 300px;"><canvas id="chartActivity"></canvas></div>
        </div>
        <div>
            <h3>Top User</h3>
            <div style="height: 300px;"><canvas id="chartUsers"></canvas></div>
        </div>
        <div style="grid-column: 1 / -1;">
            <h3>Top Artikel</h3>
            <div style="height: 300px;"><canvas id="chartItems"></canvas></div>
        </div>
    </div>

    <div style="margin-top: 40px;">
        <h2>Produktivität & Optimierung</h2>
        <div class="grid2"
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div>
                <h3>Umlagerungsvorschläge</h3>
                <ul id="rebalancingList" class="list">
                    <li>Lade Daten...</li>
                </ul>
            </div>
            <div>
                <h3>Verbrauch (Top 5)</h3>
                <ul id="consumptionList" class="list">
                    <li>Lade Daten...</li>
                </ul>
            </div>
            <div style="grid-column: 1 / -1;">
                <h3>Bestandsverteilung (Global)</h3>
                <div style="height: 300px;"><canvas id="chartDistribution"></canvas></div>
            </div>
        </div>
    </div>
</section>

<style>
    .tabcontent {
        display: none;
    }

    .tabcontent.active {
        display: block;
    }

    .tab.active {
        background: var(--primary);
        color: white;
    }
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
