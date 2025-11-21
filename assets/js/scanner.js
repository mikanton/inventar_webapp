// assets/js/scanner.js

class Scanner {
    constructor() {
        this.html5QrcodeScanner = null;
        this.isScanning = false;
        this.lastResult = null;
        this.modal = null;
        this.actionSheet = null;
    }

    init() {
        this.createUI();
        this.attachListeners();
    }

    createUI() {
        // Scanner Modal
        const modalHtml = `
        <div id="scannerModal" class="modal hidden scanner-modal">
            <div class="scanner-container">
                <div id="reader"></div>
                <button id="closeScanner" class="btn-close-scanner">✕</button>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Action Sheet
        const sheetHtml = `
        <div id="actionSheet" class="action-sheet hidden">
            <div class="sheet-content">
                <div class="sheet-header">
                    <h3 id="sheetTitle">Gefunden</h3>
                    <button id="closeSheet" class="btn-text">Schließen</button>
                </div>
                <div id="sheetBody"></div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', sheetHtml);

        this.modal = document.getElementById('scannerModal');
        this.actionSheet = document.getElementById('actionSheet');
    }

    attachListeners() {
        document.getElementById('closeScanner').addEventListener('click', () => this.stop());
        document.getElementById('closeSheet').addEventListener('click', () => this.closeSheet());

        // Add FAB listener if it exists
        const fabScan = document.getElementById('fabScan');
        if (fabScan) fabScan.addEventListener('click', () => this.start());
    }

    async start() {
        if (this.isScanning) return;
        this.modal.classList.remove('hidden');
        this.isScanning = true;

        this.html5QrcodeScanner = new Html5Qrcode("reader");
        const config = {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            },
            formatsToSupport: [
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.QR_CODE
            ]
        };

        try {
            await this.html5QrcodeScanner.start(
                { facingMode: "environment" },
                config,
                (decodedText) => this.onScanSuccess(decodedText),
                (errorMessage) => { /* ignore */ }
            );
        } catch (err) {
            console.error(err);
            alert('Kamera konnte nicht gestartet werden.');
            this.stop();
        }
    }

    async stop() {
        if (this.html5QrcodeScanner) {
            try {
                await this.html5QrcodeScanner.stop();
                this.html5QrcodeScanner.clear();
            } catch (e) { console.error(e); }
        }
        this.modal.classList.add('hidden');
        this.isScanning = false;
    }

    async onScanSuccess(decodedText) {
        if (this.lastResult === decodedText) return; // Debounce
        this.lastResult = decodedText;

        // Pause scanning
        await this.html5QrcodeScanner.pause();

        // Fetch item
        try {
            const res = await fetch(`api.php?action=get_by_barcode&barcode=${encodeURIComponent(decodedText)}`);
            const data = await res.json();
            this.showActionSheet(decodedText, data);
        } catch (e) {
            console.error(e);
            alert('Fehler beim Abrufen des Artikels');
            this.resume();
        }
    }

    resume() {
        this.lastResult = null;
        this.html5QrcodeScanner.resume();
    }

    showActionSheet(barcode, data) {
        this.actionSheet.classList.remove('hidden');
        const body = document.getElementById('sheetBody');
        const title = document.getElementById('sheetTitle');

        if (data.found && data.in_location) {
            // Existing Item
            const item = data.item;
            title.textContent = item.name;
            body.innerHTML = `
                <div class="sheet-info">
                    <div class="sheet-qty">Aktuell: <b>${item.qty}</b></div>
                    <div class="sheet-actions">
                        <button class="btn-sheet" onclick="scanner.update('${item.name}', -1)">-1</button>
                        <button class="btn-sheet" onclick="scanner.update('${item.name}', 1)">+1</button>
                        <button class="btn-sheet primary" onclick="scanner.update('${item.name}', 5)">+5</button>
                        <button class="btn-sheet primary" onclick="scanner.update('${item.name}', 10)">+10</button>
                    </div>
                </div>
            `;
        } else {
            // New Item (or known name but not in this loc)
            const preName = data.found ? data.item.name : '';
            title.textContent = 'Neuer Artikel';
            body.innerHTML = `
                <div class="sheet-form">
                    <p>Barcode: ${barcode}</p>
                    <input id="scanName" placeholder="Name" value="${preName}">
                    <input id="scanQty" type="number" placeholder="Menge" value="1">
                    <button class="btn primary full" onclick="scanner.add('${barcode}')">Hinzufügen</button>
                </div>
            `;
        }
    }

    closeSheet() {
        this.actionSheet.classList.add('hidden');
        this.resume();
    }

    async update(name, delta) {
        try {
            await fetch('api.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, delta })
            });
            showToast(`Bestand ${delta > 0 ? '+' : ''}${delta}`, 'success');
            this.closeSheet();
            // Optionally reload list in background
            if (window.loadInventory) window.loadInventory();
        } catch (e) {
            alert('Fehler beim Aktualisieren');
        }
    }

    async add(barcode) {
        const name = document.getElementById('scanName').value;
        const qty = document.getElementById('scanQty').value;

        if (!name) return alert('Name fehlt');

        try {
            await fetch('api.php?action=add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, qty, barcode })
            });
            showToast('Artikel hinzugefügt', 'success');
            this.closeSheet();
            if (window.loadInventory) window.loadInventory();
        } catch (e) {
            alert('Fehler beim Hinzufügen');
        }
    }
}

const scanner = new Scanner();
document.addEventListener('DOMContentLoaded', () => scanner.init());
