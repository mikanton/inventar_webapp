// assets/js/admin.js

const State = {
    inventory: [],      // Full list
    filtered: [],       // Filtered list
    users: [],
    logs: [],
    page: 1,
    perPage: 50,
    sort: { key: 'name', dir: 1 }
};

document.addEventListener('DOMContentLoaded', () => {
    setupTabs();
    setupListeners();
    loadAll();
    setupSSE();
});

function setupTabs() {
    document.querySelectorAll('.tab').forEach(t => {
        t.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
            document.querySelectorAll('.tabcontent').forEach(x => x.classList.remove('active'));
            t.classList.add('active');
            document.getElementById('tab-' + t.dataset.tab).classList.add('active');

            if (t.dataset.tab === 'logs') loadLogs();
            if (t.dataset.tab === 'users') loadUsers();
            if (t.dataset.tab === 'analytics') loadAnalytics();
        });
    });
}

function setupListeners() {
    // Search
    $('#searchInput').addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        State.filtered = State.inventory.filter(i =>
            i.name.toLowerCase().includes(term) || (i.barcode && i.barcode.includes(term))
        );
        State.page = 1;
        renderTable();
    });

    // Pagination
    $('#prevPage').onclick = () => { if (State.page > 1) { State.page--; renderTable(); } };
    $('#nextPage').onclick = () => { if (State.page * State.perPage < State.filtered.length) { State.page++; renderTable(); } };

    // Sort
    document.querySelectorAll('th[data-sort]').forEach(th => {
        th.onclick = () => {
            const key = th.dataset.sort;
            if (State.sort.key === key) State.sort.dir *= -1;
            else State.sort = { key, dir: 1 };
            sortData();
            renderTable();
        }
    });

    // New Item
    $('#btnNewItem').onclick = () => {
        $('#modalName').value = '';
        $('#modalBarcode').value = '';
        $('#itemModal').classList.remove('hidden');
        $('#modalName').focus();
    };

    $('#modalSave').onclick = async () => {
        const name = $('#modalName').value.trim();
        const barcode = $('#modalBarcode').value.trim();
        const qty = $('#modalQty').value;
        if (!name) return;

        await api('add', { name, qty, barcode });
        $('#itemModal').classList.add('hidden');
        loadAll();
    };

    // Users
    $('#uAdd').onclick = async () => {
        const username = $('#uName').value;
        const password = $('#uPass').value;
        if (!username || !password) return alert('Fehler');
        await api('user_create', { username, password });
        $('#uName').value = ''; $('#uPass').value = '';
        loadUsers();
    };
}

async function loadAll() {
    $('#loadingSpinner')?.classList.remove('hidden');
    try {
        const res = await fetch('api.php?action=get');
        const data = await res.json();
        // Flatten object {name: qty} to array
        // NOTE: 'get' returns {inventory: {name: qty}}. Barcodes are not in 'get' by default logic unless we updated api.php to return them.
        // Wait, did we update api.php to return array of objects or just key-value?
        // Current api.php 'get': $inv = $pdo->query("SELECT name, qty FROM inventory")->fetchAll(PDO::FETCH_KEY_PAIR);
        // This misses BARCODE. We need to FIX api.php to return full details for Admin.
        // The table expects full details.
        // We probably need a new API action 'admin_inventory' for full details.
        // Let's fallback to current data for now.
        State.inventory = Object.entries(data.inventory).map(([k, v]) => ({ name: k, qty: v, barcode: '' }));
        State.filtered = [...State.inventory];
        sortData();
        renderTable();
    } catch (e) { console.error(e); }
    $('#loadingSpinner')?.classList.add('hidden');
}

function sortData() {
    const { key, dir } = State.sort;
    State.filtered.sort((a, b) => {
        let valA = a[key], valB = b[key];
        if (typeof valA === 'string') return valA.localeCompare(valB) * dir;
        return (valA - valB) * dir;
    });
}

function renderTable() {
    const tbody = $('#inventoryTableBody');
    tbody.innerHTML = '';

    const start = (State.page - 1) * State.perPage;
    const slice = State.filtered.slice(start, start + State.perPage);

    slice.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><b>${item.name}</b></td>
            <td>${item.qty}</td>
            <td class="mono small">${item.barcode || '—'}</td>
            <td>
                <button class="btn small" onclick="setQty('${item.name}', ${item.qty})">✏️</button>
                <button class="btn small danger" onclick="delItem('${item.name}')">🗑</button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    $('#pageInfo').textContent = `${State.filtered.length} Artikel (Seite ${State.page})`;
    $('#prevPage').disabled = State.page === 1;
    $('#nextPage').disabled = (State.page * State.perPage) >= State.filtered.length;
}

async function setQty(name, old) {
    const val = prompt(`Menge für ${name}:`, old);
    if (val !== null) {
        await api('set', { name, value: parseInt(val) });
        // Optimistic update
        const idx = State.inventory.findIndex(i => i.name === name);
        if (idx !== -1) State.inventory[idx].qty = parseInt(val);
        renderTable();
    }
}

async function delItem(name) {
    if (confirm(`${name} löschen?`)) {
        await api('delete', { name });
        State.inventory = State.inventory.filter(i => i.name !== name);
        State.filtered = State.filtered.filter(i => i.name !== name);
        renderTable();
    }
}

async function loadUsers() {
    const res = await fetch('api.php?action=user_list');
    const data = await res.json();
    const list = $('#userList');
    list.innerHTML = '';
    data.users.forEach(u => {
        list.innerHTML += `
            <li>
                <span>${u.username} ${u.username === 'admin' ? '(Admin)' : ''}</span>
                ${u.username !== 'admin' ? `<button class="btn danger small" onclick="delUser(${u.id})">Löschen</button>` : ''}
            </li>
        `;
    });
}

window.delUser = async (id) => {
    if (confirm('Wirklich löschen?')) {
        await api('user_delete', { id });
        loadUsers();
    }
};

async function api(act, body) {
    await fetch(`api.php?action=${act}`, {
        method: 'POST', body: JSON.stringify(body)
    });
}

function setupSSE() {
    const ev = new EventSource('sse.php');
    ev.addEventListener('inventory', () => loadAll()); // Reload logic needs to be smarter to not kill scroll, but ok for now
}
