<?php
$title = 'Inventar — Requests';
$extraScripts = '<script src="assets/js/app.js"></script>';
ob_start();
?>

<div class="toolbar" style="margin-bottom: 20px;">
    <div class="row gap">
        <div class="search-wrap" style="flex: 1;">
            <input id="search" type="search" placeholder="Artikel suchen …" style="width: 100%;">
        </div>
        <select id="sort" class="sort-select">
            <option value="nameAsc">Name A–Z</option>
            <option value="nameDesc">Name Z–A</option>
            <option value="qtyDesc">Menge ↓</option>
            <option value="qtyAsc">Menge ↑</option>
        </select>
    </div>
</div>

<div id="list" class="list"></div>

<script>
    const IS_ADMIN = <?php echo Auth::isLoggedIn() ? 'true' : 'false'; ?>;
</script>
<script src="assets/js/scanner.js"></script>

<!-- Add / Set Modal -->
<div id="modalAdd" class="modal hidden">
    <div class="modal-card">
        <h3>Artikel hinzufügen / setzen</h3>
        <div class="field">
            <input id="addName" placeholder="Artikelname">
        </div>
        <div class="field">
            <input id="addQty" type="number" min="0" value="0">
        </div>
        <div class="row">
            <button id="addSave" class="btn primary">Speichern</button>
            <button id="addCancel" class="btn">Abbrechen</button>
        </div>
    </div>
</div>

<!-- Request Modal -->
<div id="modalRequest" class="modal hidden">
    <div class="modal-card">
        <h3>Neue Request</h3>
        <div class="field">
            <label>Lieferort</label>
            <input id="reqLocation" placeholder="z. B. Lager A">
        </div>

        <div class="field">
            <label>Artikel auswählen</label>
            <input id="reqSearch" placeholder="Artikel suchen …">
            <div id="reqItems" class="req-items"></div>
        </div>

        <div class="field">
            <label>Ausgewählt</label>
            <div id="reqSelected" class="req-selected">Noch nichts ausgewählt</div>
        </div>

        <div class="row">
            <button id="reqCancel" class="btn">Abbrechen</button>
            <button id="reqSubmit" class="btn primary" disabled>Absenden</button>
        </div>
    </div>
</div>

<!-- Supplier / Requests modal -->
<div id="modalSupplier" class="modal hidden">
    <div class="modal-card large">
        <h3>Requests / Lieferanten</h3>
        <div class="row top-actions" style="margin-bottom: 16px; justify-content: space-between;">
            <div style="display:flex; gap:8px;">
                <button id="aggBtn" class="btn">Gesamtliste erzeugen</button>
                <button id="refreshRequests" class="btn">Aktualisieren</button>
            </div>
            <button id="closeSupplier" class="btn">Schließen</button>
        </div>
        <div id="requestsList" class="requests-list"></div>

        <h4 style="margin-top: 24px;">Aggregierte Pickliste</h4>
        <div id="picklist" class="picklist"></div>
    </div>
</div>

<?php
$overlays = '
<!-- Floating Action Button + Menu -->
<div class="fab-wrapper">
    <button id="fab" class="fab">＋</button>

    <div id="fabMenu" class="fab-menu" aria-hidden="true">
        <button class="fab-mini" id="fabAdd" title="Neuen Artikel">＋</button>
        <button class="fab-mini" id="fabScan" title="Scannen">📷</button>
        <button class="fab-mini" id="fabRequest" title="Neue Request">📦</button>
        <button class="fab-mini" id="fabSupplier" title="Lieferantenansicht">🚚</button>
    </div>
</div>
';
$content = ob_get_clean();
require __DIR__ . '/layout.php';
