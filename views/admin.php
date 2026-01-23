<?php
Auth::requireLogin();
if (!Auth::isAdmin()) {
    die("<h1>Access Denied</h1><p>Nur für Administratoren.</p><a href='index.php'>Zurück</a>");
}
$title = 'Inventar – Admin';
$active = 'admin';
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="assets/js/admin.js"></script><script>const IS_ADMIN = true;</script><script src="assets/js/scanner.js"></script>';

// IP/Port Logic
$ip = getHostByName(getHostName());
if ($ip == '127.0.0.1')
    $ip = $_SERVER['SERVER_ADDR'] ?? 'localhost';
$port = file_exists(__DIR__ . '/../proxy.js') ? 8443 : 8000;
$protocol = $port === 8443 ? 'https' : 'http';
$mobileUrl = "$protocol://$ip:$port";

ob_start();
?>
<div class="admin-wrapper">
    <nav class="tabs">
        <button class="tab active btn" data-tab="inv">📦 Inventar</button>
        <button class="tab btn" data-tab="users">👥 Benutzer</button>
        <button class="tab btn" data-tab="logs">📜 Logs</button>
        <button class="tab btn" data-tab="analytics">📊 Analytics</button>
    </nav>

    <!-- INVENTORY TAB -->
    <section id="tab-inv" class="tabcontent active">
        <div class="toolbar">
            <div class="group">
                <input id="searchInput" type="text" placeholder="Suche..." class="search-input">
            </div>
            <div class="group">
                <button class="btn" onclick="scanner.start(true)">📷 Scan</button>
                <button class="btn primary" id="btnNewItem">+ Neu</button>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th data-sort="name">Name ↕</th>
                        <th data-sort="qty">Menge ↕</th>
                        <th>Barcode</th>
                        <th style="width: 150px">Aktionen</th>
                    </tr>
                </thead>
                <tbody id="inventoryTableBody">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
            <div id="loadingSpinner" class="spinner hidden"></div>
        </div>
        <div class="pagination">
            <span id="pageInfo">0 Artikel</span>
            <div class="btn-group">
                <button id="prevPage" class="btn small" disabled>←</button>
                <button id="nextPage" class="btn small" disabled>→</button>
            </div>
        </div>
    </section>

    <!-- USERS TAB -->
    <section id="tab-users" class="tabcontent">
        <div class="split-view">
            <div class="card form-card">
                <h3>Neuer Benutzer</h3>
                <form id="userForm" onsubmit="return false;">
                    <div class="field">
                        <label>Benutzername</label>
                        <input id="uName" required>
                    </div>
                    <div class="field">
                        <label>Passwort</label>
                        <input id="uPass" type="password" required>
                    </div>
                    <button id="uAdd" class="btn primary full">Erstellen</button>
                </form>
            </div>
            <div class="list-card">
                <h3>Vorhandene Benutzer</h3>
                <ul id="userList" class="user-list"></ul>
            </div>
        </div>
    </section>

    <!-- LOGS TAB -->
    <section id="tab-logs" class="tabcontent">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Zeit</th>
                        <th>User</th>
                        <th>Aktion</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody id="logsTableBody"></tbody>
            </table>
        </div>
    </section>

    <!-- ANALYTICS TAB -->
    <section id="tab-analytics" class="tabcontent">
        <div class="grid2">
            <div class="card graph-card">
                <h3>Aktivität (30 Tage)</h3>
                <div class="chart-box"><canvas id="chartActivity"></canvas></div>
            </div>
            <div class="card graph-card">
                <h3>Verteilung</h3>
                <div class="chart-box"><canvas id="chartDistribution"></canvas></div>
            </div>
        </div>
    </section>
</div>

<!-- Modal for New Item -->
<div id="itemModal" class="modal hidden">
    <div class="modal-card">
        <h2>Neuer Artikel</h2>
        <div class="field">
            <label>Name</label>
            <input id="modalName" autofocus>
        </div>
        <div class="field">
            <label>Barcode (Optional)</label>
            <input id="modalBarcode">
        </div>
        <div class="field">
            <label>Startbestand</label>
            <input id="modalQty" type="number" value="1">
        </div>
        <div class="row right">
            <button class="btn" onclick="$('#itemModal').classList.add('hidden')">Abbrechen</button>
            <button class="btn primary" id="modalSave">Speichern</button>
        </div>
    </div>
</div>

<style>
    /* Modern Admin CSS */
    .admin-wrapper {
        max-width: 1200px;
        margin: 0 auto;
    }

    .tabs {
        display: flex;
        gap: 10px;
        border-bottom: 2px solid var(--border);
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .tab.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .toolbar {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
    }

    .group {
        display: flex;
        gap: 10px;
    }

    .search-input {
        width: 300px;
        padding: 10px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: rgba(0, 0, 0, 0.2);
        color: white;
    }

    /* Table */
    .table-container {
        background: var(--bg-card);
        border-radius: 12px;
        border: 1px solid var(--border);
        overflow: hidden;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th,
    .data-table td {
        padding: 12px 16px;
        text-align: left;
        border-bottom: 1px solid var(--border);
    }

    .data-table th {
        background: rgba(255, 255, 255, 0.05);
        font-weight: 600;
        cursor: pointer;
        user-select: none;
    }

    .data-table th:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .data-table tr:hover {
        background: rgba(255, 255, 255, 0.02);
    }

    /* Users */
    .split-view {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 20px;
    }

    .user-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .user-list li {
        display: flex;
        justify-content: space-between;
        padding: 10px;
        border-bottom: 1px solid var(--border);
        align-items: center;
    }

    .spinner {
        width: 30px;
        height: 30px;
        border: 3px solid var(--border);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 1s infinite;
        margin: 20px auto;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>
<style>
    /* Hotfix for tabs */
    .tabcontent {
        display: none;
    }

    .tabcontent.active {
        display: block;
    }
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
