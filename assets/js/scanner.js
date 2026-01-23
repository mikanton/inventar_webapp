// assets/js/scanner.js

class Scanner {
    constructor() {
        this.html5QrcodeScanner = null;
        this.isScanning = false;
        this.lastResult = null;
        this.modal = null;
        this.mode = 'quick'; // quick, batch-in, batch-out
        this.audioCtx = null;
        this.torchOn = false;
        this.scanCount = 0;
    }

    init() {
        this.createUI();
        this.attachListeners();
        // Init Audio Context on first interaction
        document.addEventListener('click', () => {
            if (!this.audioCtx) this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }, { once: true });
    }

    createUI() {
        // Scanner Modal with simplified Overlay (Laser Line)
        const modalHtml = `
        <div id="scannerModal" class="modal hidden scanner-modal">
            <div class="scanner-container">
                <div id="reader"></div>
                
                <!-- Static Laser Line Overlay -->
                <div class="laser-line"></div>
                <div class="scanner-guide-text">Code im Rahmen platzieren</div>
                
                <!-- Feedback Overlay -->
                <div id="scanFeedback" class="scan-feedback"></div>

                <!-- Batch Counter -->
                <div id="batchCounter" class="batch-counter hidden">Session: 0</div>

                <!-- UI Overlay -->
                <div class="scanner-overlay">
                    <button id="closeScanner" class="btn-close-scanner">✕</button>
                    
                    <div class="scanner-controls">
                        <div class="mode-switcher">
                            <button class="mode-btn active" data-mode="quick">Quick</button>
                            <button class="mode-btn" data-mode="batch-in">Batch +</button>
                            <button class="mode-btn" data-mode="batch-out">Batch -</button>
                        </div>
                        <button id="toggleFlash" class="btn-flash hidden" title="Taschenlampe">⚡</button>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Paused Overlay Modal (Centered)
        const overlayHtml = `
        <div id="scannerOverlay" class="scanner-overlay-modal hidden">
            <div class="overlay-content">
                <div class="overlay-header">
                    <h3 id="overlayTitle">Gefunden</h3>
                    <button id="closeOverlay" class="btn-icon">✕</button>
                </div>
                <div id="overlayBody"></div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', overlayHtml);

        this.modal = document.getElementById('scannerModal');
        this.overlay = document.getElementById('scannerOverlay');
        this.feedbackEl = document.getElementById('scanFeedback');
        this.batchCounterEl = document.getElementById('batchCounter');

        // Batch Feedback Container
        const batchContainer = document.createElement('div');
        batchContainer.className = 'batch-feedback-container';
        document.body.appendChild(batchContainer);
        this.batchContainer = batchContainer;
    }

    attachListeners() {
        document.getElementById('closeScanner').addEventListener('click', () => this.stop());
        document.getElementById('closeOverlay').addEventListener('click', () => this.closeOverlay());
        document.getElementById('toggleFlash').addEventListener('click', () => this.toggleFlash());

        // Mode Switcher
        document.querySelectorAll('.mode-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.mode = e.target.dataset.mode;
                this.showFeedback('Mode: ' + e.target.textContent, 'info');

                // Reset batch counter on mode switch
                this.scanCount = 0;
                this.updateBatchCounter();
            });
        });

        const fabScan = document.getElementById('fabScan');
        if (fabScan) fabScan.addEventListener('click', () => this.start());
    }

    updateBatchCounter() {
        if (this.mode.startsWith('batch')) {
            this.batchCounterEl.classList.remove('hidden');
            this.batchCounterEl.textContent = `Session: ${this.scanCount}`;
        } else {
            this.batchCounterEl.classList.add('hidden');
        }
    }

    async start() {
        if (this.isScanning) return;
        this.modal.classList.remove('hidden');
        this.isScanning = true;
        this.scanCount = 0;
        this.updateBatchCounter();

        this.html5QrcodeScanner = new Html5Qrcode("reader");

        // Optimized Config: 720p is the sweet spot
        const config = {
            fps: 15, // Higher smooth FPS
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0,
            videoConstraints: {
                width: { min: 640, ideal: 1280 }, // 720p preference
                height: { min: 480, ideal: 720 },
                facingMode: "environment"
            },
            experimentalFeatures: { useBarCodeDetectorIfSupported: true },
            formatsToSupport: [
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.QR_CODE
            ]
        };

        try {
            await this.html5QrcodeScanner.start(
                { facingMode: "environment" },
                config,
                (decodedText, decodedResult) => this.onScanSuccess(decodedText, decodedResult),
                (errorMessage) => { /* ignore */ }
            );

            // Check Torch
            setTimeout(() => this.checkTorch(), 500);
        } catch (err) {
            console.error(err);
            alert('Kamera-Fehler: ' + err);
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
        this.lastResult = null;
    }

    async onScanSuccess(decodedText, decodedResult) {
        if (this.lastResult === decodedText) return; // Debounce

        // Batch Mode: FAST
        if (this.mode.startsWith('batch')) {
            if (this.processing) return;
            this.processing = true;
            this.lastResult = decodedText;

            // Immediate Feedback
            this.playBeep('success'); // Anticipatory beep
            this.flash('green');

            // Fire and forget (mostly) - don't block UI
            this.handleBatchScan(decodedText).finally(() => {
                this.processing = false;
                // Short debounce for same item
                setTimeout(() => { if (this.lastResult === decodedText) this.lastResult = null; }, 800);
            });

        } else {
            // Quick Mode
            this.lastResult = decodedText;
            this.playBeep('neutral');
            this.showOverlay(decodedText, null);
            this.html5QrcodeScanner.pause(true);
            await this.handleQuickScan(decodedText);
        }
    }

    async handleBatchScan(barcode) {
        const delta = this.mode === 'batch-in' ? 1 : -1;

        try {
            const res = await fetch(`api.php?action=get_by_barcode&barcode=${encodeURIComponent(barcode)}`);
            const data = await res.json();

            if (data.found && data.in_location) {
                const name = data.item.name;
                await this.update(name, delta, true);
                this.scanCount++;
                this.updateBatchCounter();
            } else {
                this.playBeep('error');
                this.showBatchFeedback('Nicht gefunden / Falscher Ort', 'error');
                this.flash('red');
            }
        } catch (e) {
            this.playBeep('error');
        }
    }

    async handleQuickScan(barcode) {
        try {
            const res = await fetch(`api.php?action=get_by_barcode&barcode=${encodeURIComponent(barcode)}`);
            const data = await res.json();
            this.showOverlay(barcode, data);
        } catch (e) {
            alert('Fehler beim Abrufen');
            this.closeOverlay();
        }
    }

    showOverlay(barcode, data) {
        this.overlay.classList.remove('hidden');
        const body = document.getElementById('overlayBody');
        const title = document.getElementById('overlayTitle');

        if (!data) {
            title.textContent = 'Suche...';
            body.innerHTML = `<div class="spinner"></div><p style="text-align:center">${barcode}</p>`;
            return;
        }

        if (data.found && data.in_location) {
            const item = data.item;
            title.textContent = item.name;
            body.innerHTML = `
                <div class="overlay-info">
                    <div class="overlay-qty">Bestand: <b>${item.qty}</b></div>
                    <div class="overlay-actions">
                        <button class="btn-overlay" onclick="scanner.update('${item.name}', -1)">-1</button>
                        <button class="btn-overlay" onclick="scanner.update('${item.name}', 1)">+1</button>
                        <button class="btn-overlay primary" onclick="scanner.update('${item.name}', 5)">+5</button>
                    </div>
                </div>
            `;
        } else {
            // New Item / Link
            if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) {
                title.textContent = 'Neuer Artikel';
                body.innerHTML = `
                    <div class="overlay-form">
                        <input id="scanName" placeholder="Artikelname" autofocus>
                        <input id="scanQty" type="number" value="1">
                        <button class="btn primary full" onclick="scanner.add('${barcode}')">Erstellen</button>
                        <hr>
                        <button class="btn secondary full" onclick="scanner.showLinkSearch('${barcode}')">Verknüpfen</button>
                    </div>
                `;
            } else {
                this.showLinkSearch(barcode);
            }
        }
    }

    async showLinkSearch(barcode) {
        const body = document.getElementById('overlayBody');
        document.getElementById('overlayTitle').textContent = 'Verknüpfen';

        try {
            const res = await fetch('api.php?action=get');
            const data = await res.json();
            const items = data.inventory;

            body.innerHTML = `
                <div class="overlay-form">
                    <p style="font-size:0.9rem">Wähle Artikel für <b>${barcode}</b></p>
                    <input id="linkSearch" placeholder="Suchen..." onkeyup="scanner.filterLinkList()">
                    <div id="linkList" class="link-list">
                        ${Object.keys(items).map(n => `
                            <div class="link-item" onclick="scanner.linkBarcode('${n}', '${barcode}')">
                                <span>${n}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        } catch (e) { body.textContent = 'Fehler'; }
    }

    filterLinkList() {
        const term = document.getElementById('linkSearch').value.toLowerCase();
        document.querySelectorAll('.link-item').forEach(el => {
            el.style.display = el.textContent.toLowerCase().includes(term) ? 'flex' : 'none';
        });
    }

    async linkBarcode(name, barcode) {
        if (!confirm(`${name} verknüpfen?`)) return;
        try {
            await fetch('api.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, delta: 0, barcode })
            });
            showToast('Verknüpft');
            this.closeOverlay();
        } catch (e) { alert('Fehler'); }
    }

    closeOverlay() {
        this.overlay.classList.add('hidden');
        this.lastResult = null;
        try { this.html5QrcodeScanner.resume(); } catch (e) { }
    }

    async update(name, delta, silent = false) {
        try {
            await fetch('api.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, delta })
            });
            if (!silent) {
                showToast(`Gespeichert`);
                this.closeOverlay();
            } else {
                this.showBatchFeedback(`${name}: ${delta > 0 ? '+' : ''}${delta}`, 'success');
            }
            if (window.load) window.load(); // Refresh list
        } catch (e) { if (!silent) alert('Fehler'); }
    }

    async add(barcode) {
        const name = document.getElementById('scanName').value;
        const qty = document.getElementById('scanQty').value;
        try {
            await fetch('api.php?action=add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, qty, barcode })
            });
            this.closeOverlay();
            if (window.load) window.load();
        } catch (e) { alert('Fehler'); }
    }

    /* --- Feedback Helpers --- */
    flash(color) {
        this.feedbackEl.className = `scan-feedback flash-${color}`;
        setTimeout(() => this.feedbackEl.className = 'scan-feedback', 300);
    }

    showFeedback(text) {
        const el = document.createElement('div');
        el.className = 'scanner-toast';
        el.textContent = text;
        document.querySelector('.scanner-container').appendChild(el);
        setTimeout(() => el.remove(), 2000);
    }

    showBatchFeedback(text, type) {
        const el = document.createElement('div');
        el.className = `batch-popup ${type}`;
        el.innerHTML = `<span>${type === 'success' ? '✓' : '✕'}</span> ${text}`;
        this.batchContainer.appendChild(el);
        setTimeout(() => el.remove(), 2000);
    }

    playBeep(type) {
        if (!this.audioCtx) return;
        const osc = this.audioCtx.createOscillator();
        const gain = this.audioCtx.createGain();
        osc.connect(gain);
        gain.connect(this.audioCtx.destination);

        if (type === 'success') {
            osc.frequency.setValueAtTime(880, this.audioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1760, this.audioCtx.currentTime + 0.1);
        } else if (type === 'error') {
            osc.frequency.setValueAtTime(200, this.audioCtx.currentTime);
            osc.frequency.linearRampToValueAtTime(100, this.audioCtx.currentTime + 0.3);
        } else {
            osc.frequency.setValueAtTime(440, this.audioCtx.currentTime);
        }

        osc.start();
        gain.gain.exponentialRampToValueAtTime(0.00001, this.audioCtx.currentTime + 0.15);
        osc.stop(this.audioCtx.currentTime + 0.15);
    }

    async checkTorch() {
        try {
            const caps = this.html5QrcodeScanner.html5Qrcode.getRunningTrackCameraCapabilities();
            if (caps.torch) {
                document.getElementById('toggleFlash').classList.remove('hidden');
            }
        } catch (e) { }
    }

    async toggleFlash() {
        this.torchOn = !this.torchOn;
        try {
            await this.html5QrcodeScanner.html5Qrcode.applyVideoConstraints({
                advanced: [{ torch: this.torchOn }]
            });
            document.getElementById('toggleFlash').classList.toggle('active', this.torchOn);
        } catch (e) { this.torchOn = !this.torchOn; }
    }
}

const scanner = new Scanner();
document.addEventListener('DOMContentLoaded', () => scanner.init());
