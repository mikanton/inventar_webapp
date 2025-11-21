/* app.js — Inventory UI Logic */

const state = { inv: {}, requests: [], location: null }; // Added location to state

// Init
document.addEventListener('DOMContentLoaded', () => {
    load(); // Renamed init to load
    setupListeners();
    setupSSE();
});

async function load() { // Renamed init to load
    try {
        const res = await fetch('api.php?action=get');
        const data = await res.json();
        state.inv = data.inventory || {};
        state.requests = data.requests || [];
        state.location = data.location; // Store location info
        render();
    } catch (e) {
        console.error(e);
        toast('Fehler beim Laden', 'error');
    }
}

function setupListeners() {
    $('#search').addEventListener('input', render);
    $('#sort').addEventListener('change', render);

    // FAB
    const fab = $('#fab');
    const fabMenu = $('#fabMenu');
    fab.addEventListener('click', () => {
        fab.classList.toggle('open');
        fabMenu.classList.toggle('hidden');
    });

    $('#fabAdd').addEventListener('click', () => {
        $('#modalAdd').classList.remove('hidden');
        $('#addName').value = '';
        $('#addQty').value = '0';
        closeFab();
    });

    $('#fabRequest').addEventListener('click', () => {
        openRequestModal();
        closeFab();
    });

    $('#fabSupplier').addEventListener('click', () => {
        openSupplierModal();
        closeFab();
    });

    // Add Modal
    $('#addCancel').addEventListener('click', () => $('#modalAdd').classList.add('hidden'));
    $('#addSave').addEventListener('click', saveItem);

    // Request Modal
    $('#reqCancel').addEventListener('click', () => $('#modalRequest').classList.add('hidden'));
    $('#reqSearch').addEventListener('input', renderReqItems);
    $('#reqSubmit').addEventListener('click', submitRequest);

    // Supplier Modal
    $('#closeSupplier').addEventListener('click', () => $('#modalSupplier').classList.add('hidden'));
    $('#refreshRequests').addEventListener('click', loadRequests);
    $('#aggBtn').addEventListener('click', createAggregate);

    // Keyboard
    document.addEventListener('keydown', (ev) => {
        if (ev.key === '/' && document.activeElement !== $('#search')) {
            ev.preventDefault();
            $('#search').focus();
        }
    });
}

function closeFab() {
    $('#fabMenu').classList.add('hidden');
    $('#fab').classList.remove('open');
}

// Render Inventory
function render() {
    const q = ($('#search').value || '').trim().toLowerCase();
    const sort = $('#sort').value || 'nameAsc';
    const list = $('#list');
    list.innerHTML = '';

    const entries = Object.entries(state.inv || {}).filter(([n]) => n.toLowerCase().includes(q));

    entries.sort((a, b) => {
        if (sort === 'nameAsc') return a[0].localeCompare(b[0], 'de');
        if (sort === 'nameDesc') return b[0].localeCompare(a[0], 'de');
        if (sort === 'qtyAsc') return a[1] - b[1];
        if (sort === 'qtyDesc') return b[1] - a[1];
        return 0;
    });

    for (const [name, qty] of entries) {
        const card = create('div', 'card item-row');

        const left = create('div', 'item-left');
        const title = create('div', 'item-name'); title.textContent = name;
        left.appendChild(title);

        const right = create('div', 'item-right');

        const btnMinus = create('button', 'btn-circle minus');
        btnMinus.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line></svg>';
        btnMinus.onclick = () => updateItem(name, -1);

        const qtyEl = create('div', 'item-qty'); qtyEl.textContent = qty;
        if (qty === 0) {
            qtyEl.innerHTML = '⚠︎ 0';
            card.classList.add('empty');
        }

        const btnPlus = create('button', 'btn-circle plus');
        btnPlus.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>';
        btnPlus.onclick = () => updateItem(name, 1);

        right.append(btnMinus, qtyEl, btnPlus);
        card.append(left, right);
        list.appendChild(card);
    }
}

async function updateItem(name, delta) {
    try {
        // Optimistic update
        const current = state.inv[name] || 0;
        const next = Math.max(0, current + delta);
        state.inv[name] = next;
        render();

        const res = await fetch('api.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, delta })
        });

        if (!res.ok) throw new Error();
    } catch (e) {
        console.error(e);
        toast('Fehler beim Speichern', 'error');
        // Revert? For now just reload
        init();
    }
}

async function saveItem() {
    const name = $('#addName').value.trim();
    const qty = parseInt($('#addQty').value || '0', 10);
    if (!name) return toast('Name benötigt', 'error');

    try {
        await fetch('api.php?action=add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, qty })
        });
        $('#modalAdd').classList.add('hidden');
        toast('Artikel hinzugefügt');
        // SSE will trigger update, but we can also reload
    } catch (e) {
        toast('Fehler beim Hinzufügen', 'error');
    }
}

// Request Logic
let reqSelected = {};

function openRequestModal() {
    reqSelected = {};
    $('#reqLocation').value = '';
    $('#reqSelected').textContent = 'Noch nichts ausgewählt';
    renderReqItems();
    $('#modalRequest').classList.remove('hidden');
    $('#reqSubmit').disabled = true;
}

function renderReqItems() {
    const q = ($('#reqSearch').value || '').toLowerCase().trim();
    const wrap = $('#reqItems');
    wrap.innerHTML = '';
    const entries = Object.entries(state.inv).filter(([n]) => n.toLowerCase().includes(q));

    for (const [name, have] of entries) {
        const row = create('div', 'req-row');
        const n = create('div', 'req-name'); n.textContent = name;

        const controls = create('div', 'req-controls');
        const input = create('input', 'req-input');
        input.type = 'number'; input.min = '0';
        input.value = reqSelected[name] || 0;

        input.addEventListener('input', () => {
            const val = Math.max(0, parseInt(input.value || '0', 10));
            if (val === 0) delete reqSelected[name]; else reqSelected[name] = val;
            updateReqSelectedBox();
        });

        controls.append(input);
        const haveEl = create('div', 'req-have'); haveEl.textContent = '● ' + have;
        row.append(n, controls, haveEl);
        wrap.appendChild(row);
    }
}

function updateReqSelectedBox() {
    const box = $('#reqSelected');
    box.innerHTML = '';
    let any = false;

    Object.entries(reqSelected).forEach(([name, qty]) => {
        any = true;
        const li = create('li', 'item');
        if (qty < 5) li.classList.add('low-stock'); // Add class for low stock

        li.innerHTML = `
            <div class="info">
                <span class="name">${name}</span>
                <span class="qty">${qty}</span>
            </div>
            <div class="actions">
                <button onclick="updateItem('${name}', -1)">-</button>
                <button onclick="updateItem('${name}', 1)">+</button>
            </div>
        `;
        box.appendChild(li);
    });

    if (!any) box.textContent = 'Noch nichts ausgewählt';
    $('#reqSubmit').disabled = !any;
}

async function submitRequest() {
    const location = $('#reqLocation').value.trim();
    if (!location) return toast('Bitte Lieferort angeben', 'error');

    const items = Object.entries(reqSelected).map(([name, qty]) => ({ name, qty }));

    try {
        await fetch('api.php?action=request_create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ location, items })
        });
        toast('Request erstellt');
        $('#modalRequest').classList.add('hidden');
    } catch (e) {
        toast('Fehler beim Erstellen', 'error');
    }
}

// Supplier / Requests
async function loadRequests() {
    $('#requestsList').innerHTML = 'Lade…';
    try {
        const res = await fetch('api.php?action=request_list');
        const data = await res.json();
        state.requests = data.requests || [];
        renderRequests();
    } catch (e) {
        $('#requestsList').textContent = 'Fehler';
    }
}

function renderRequests() {
    const wrap = $('#requestsList');
    wrap.innerHTML = '';
    if (!state.requests.length) { wrap.textContent = 'Keine Requests'; return; }

    state.requests.forEach(r => {
        const card = create('div', 'request-card');
        const date = new Date(r.created_at || r.created).toLocaleString();

        let html = `<div class="request-hdr"><strong>${r.location}</strong> <small>${date}</small></div>`;
        html += `<div class="request-body">`;

        // Handle both new DB format and old JSON format if needed, but we migrated so DB format
        // items is likely a separate fetch or joined, but for now let's assume we get it nested
        // Actually in the new API we need to make sure we return items.
        // Let's check how we implemented api.php later.

        const items = r.items || [];
        items.forEach(it => {
            html += `<div class="request-item">${it.name} — ${it.qty}</div>`;
        });
        html += `</div>`;

        const foot = create('div', 'request-foot');
        const status = create('div', 'request-status'); status.textContent = r.status;
        foot.appendChild(status);

        if (r.status === 'open') {
            const btnOk = create('button', 'btn small primary'); btnOk.textContent = 'Erledigen';
            btnOk.onclick = () => fulfillRequest(r.id);
            foot.appendChild(btnOk);
        }

        // Delete
        const btnDel = create('button', 'btn small danger'); btnDel.textContent = 'Löschen';
        btnDel.style.marginLeft = '8px';
        btnDel.onclick = () => deleteRequest(r.id);
        foot.appendChild(btnDel);

        card.innerHTML = html;
        card.appendChild(foot);
        wrap.appendChild(card);
    });
}

async function fulfillRequest(id) {
    if (!confirm('Inventar wird reduziert. Fortfahren?')) return;
    try {
        const res = await fetch('api.php?action=request_fulfill', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const j = await res.json();
        if (j.ok) {
            toast('Erledigt');
            loadRequests();
        } else if (j.shortages) {
            alert('Nicht genug Bestand:\n' + j.shortages.map(s => `${s.name}: ${s.have}/${s.want}`).join('\n'));
        }
    } catch (e) { toast('Fehler', 'error'); }
}

async function deleteRequest(id) {
    if (!confirm('Wirklich löschen?')) return;
    try {
        await fetch('api.php?action=request_delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        loadRequests();
    } catch (e) { toast('Fehler', 'error'); }
}

function openSupplierModal() {
    $('#modalSupplier').classList.remove('hidden');
    loadRequests();
}

async function createAggregate() {
    try {
        const res = await fetch('api.php?action=request_aggregate');
        const j = await res.json();
        const wrap = $('#picklist');
        wrap.innerHTML = '';
        if (!j.picklist || !j.picklist.length) { wrap.textContent = 'Leer'; return; }

        j.picklist.forEach(p => {
            const row = create('div', 'pick-row');
            row.textContent = `${p.name} — ${p.qty}`;
            wrap.appendChild(row);
        });
    } catch (e) { console.error(e); }
}

// SSE
function setupSSE() {
    const es = new EventSource('sse.php');
    es.addEventListener('inventory', ev => {
        state.inv = JSON.parse(ev.data);
        render();
    });
    es.addEventListener('requests', ev => {
        // Optional: refresh requests if modal open
        if (!$('#modalSupplier').classList.contains('hidden')) loadRequests();
    });
}
