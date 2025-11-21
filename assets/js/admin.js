/* admin.js */

const A = {
    inv: {},
    sort: 'alpha',
    charts: {}
};

document.addEventListener('DOMContentLoaded', () => {
    initAdmin();
    setupTabs();
    setupListeners();
    setupSSE();
});

async function initAdmin() {
    try {
        const res = await fetch('api.php?action=get');
        const data = await res.json();
        A.inv = data.inventory || {};
        renderAdmin();
    } catch (e) { console.error(e); }
}

function setupTabs() {
    document.querySelectorAll('.tab').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t === b));
            const target = b.dataset.tab;
            document.querySelectorAll('.tabcontent').forEach(c => c.classList.toggle('active', c.id === 'tab-' + target));

            if (target === 'logs') loadLogs();
            if (target === 'analytics') loadAnalytics();
        });
    });
}

function setupListeners() {
    $('#aSortAlpha').onclick = () => { A.sort = 'alpha'; toggleSort('aSortAlpha'); renderAdmin(); };
    $('#aSortQty').onclick = () => { A.sort = 'qty'; toggleSort('aSortQty'); renderAdmin(); };

    $('#aAdd').onclick = async () => {
        const name = $('#aName').value.trim();
        const barcode = $('#aBarcode').value.trim();
        const value = parseInt($('#aQty').value || '0', 10);
        if (!name) return;

        // Check if exists to decide add vs set
        const action = (A.inv[name] === undefined) ? 'add' : 'set';
        // If adding, we can include barcode. If setting (updating qty), we might want to update barcode too?
        // The 'set' action in API currently only updates qty.
        // But 'update' action updates qty by delta and can update barcode.
        // Let's use 'add' if new, and maybe 'update' if we want to set barcode?
        // Actually, for Admin "Set/Add", if it exists, we probably just want to set Qty.
        // But if user entered a barcode, we should probably update it.
        // Let's change logic: if it exists, use 'update' with delta=0 just to set barcode? No, 'set' sets absolute value.
        // Let's just send barcode with 'add'. For 'set', we need to update API to accept barcode or make a separate call.
        // Simplest: If new, send barcode. If exists, maybe ignore barcode or add a specific "Update Barcode" button?
        // User asked to "assign a barcode to an existing object".
        // So if I type Name + Barcode + Qty, it should update the barcode.

        // Let's use 'add' if it's new.
        if (A.inv[name] === undefined) {
            await api('add', { name, qty: value, barcode });
        } else {
            // It exists. We want to set Qty AND maybe update barcode.
            // 'set' action currently only does Qty.
            // Let's use 'update' to set barcode if provided?
            // Or just call 'set' for qty, and if barcode is there, call 'update' (with delta 0) to set barcode?
            // The API 'update' action handles barcode update if provided.
            // But 'update' adds to qty.
            // Let's just call 'set' for Qty. And if barcode is present, we need a way to set it.
            // Let's blindly call 'update' with delta=0 and barcode to set barcode, THEN 'set' for qty.
            if (barcode) {
                await api('update', { name, delta: 0, barcode });
            }
            await api('set', { name, value });
        }

        $('#aName').value = ''; $('#aBarcode').value = ''; $('#aQty').value = '0';
    };
}

function toggleSort(id) {
    $$('.seg').forEach(b => b.classList.remove('active'));
    $('#' + id).classList.add('active');
}

function renderAdmin() {
    const wrap = $('#adminList');
    wrap.innerHTML = '';
    let entries = Object.entries(A.inv);

    if (A.sort === 'alpha') entries.sort((a, b) => a[0].localeCompare(b[0], 'de'));
    else entries.sort((a, b) => b[1] - a[1]);

    entries.forEach(([name, qty]) => {
        const card = create('div', 'card');
        const left = create('div', 'name'); left.textContent = name;

        const controls = create('div', 'controls');
        const input = create('input'); input.type = 'number'; input.min = '0'; input.value = qty;

        const setB = create('button', 'btn primary'); setB.textContent = 'Setzen';
        setB.onclick = () => api('set', { name, value: parseInt(input.value || '0', 10) });

        const delB = create('button', 'btn danger'); delB.textContent = 'Löschen';
        delB.onclick = () => {
            if (confirm('Löschen?')) api('delete', { name });
        };

        controls.append(input, setB, delB);
        card.append(left, controls);
        wrap.appendChild(card);
    });
}

async function api(action, payload = {}) {
    try {
        await fetch(`api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        toast('Gespeichert');
    } catch (e) { toast('Fehler', 'error'); }
}

async function loadLogs() {
    try {
        const res = await fetch('api.php?action=logs');
        const j = await res.json();
        const ul = $('#logs'); ul.innerHTML = '';
        (j.logs || []).slice(0, 200).forEach(row => {
            const li = create('li');
            li.textContent = `[${new Date(row.created_at).toLocaleString()}] ${row.action} ${row.item || ''} ${row.value || ''}`;
            ul.appendChild(li);
        });
    } catch (e) { }
}

async function loadAnalytics() {
    try {
        const res = await fetch('api.php?action=analytics');
        const data = await res.json();

        // Activity Chart
        const ctxActivity = document.getElementById('chartActivity');
        if (ctxActivity && window.Chart) {
            if (A.charts.activity) A.charts.activity.destroy();
            A.charts.activity = new Chart(ctxActivity, {
                type: 'line',
                data: {
                    labels: Object.keys(data.byDay),
                    datasets: [{
                        label: 'Änderungen',
                        data: Object.values(data.byDay),
                        borderColor: '#2a9df4',
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        // We could add more charts for Top Users and Items if we add canvas elements for them
        // For now, let's just list them or add more charts if the user asked for "graphics" (plural)
        // The prompt said "fully working analytics with graphics".
        // Let's add another chart for Top Items if we can find a place, or just stick to one main chart.
        // The current admin.php only has chartActivity. Let's stick to that for now to avoid layout changes,
        // or add a second chart dynamically?
        // The user asked for "fully working analytics".
        // Let's try to add a second chart for items.

        // Users Chart
        const ctxUsers = document.getElementById('chartUsers');
        if (ctxUsers && window.Chart) {
            if (A.charts.users) A.charts.users.destroy();
            A.charts.users = new Chart(ctxUsers, {
                type: 'bar',
                data: {
                    labels: Object.keys(data.topUsers),
                    datasets: [{
                        label: 'Aktionen',
                        data: Object.values(data.topUsers),
                        backgroundColor: '#7bd389'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        // Items Chart
        const ctxItems = document.getElementById('chartItems');
        if (ctxItems && window.Chart) {
            if (A.charts.items) A.charts.items.destroy();
            A.charts.items = new Chart(ctxItems, {
                type: 'bar',
                data: {
                    labels: Object.keys(data.topItems),
                    datasets: [{
                        label: 'Änderungen',
                        data: Object.values(data.topItems),
                        backgroundColor: '#ff7b88'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y' }
            });
        }

        // Load Productivity Stats
        const resProd = await fetch('api.php?action=analytics_productivity');
        const dataProd = await resProd.json();

        // Rebalancing
        const rebList = document.getElementById('rebalancingList');
        if (rebList) {
            rebList.innerHTML = '';
            if (dataProd.rebalancing.length === 0) {
                rebList.innerHTML = '<li>Keine Vorschläge.</li>';
            } else {
                dataProd.rebalancing.forEach(r => {
                    const li = document.createElement('li');
                    li.innerHTML = `<b>${r.name}</b>: Hier ${r.local_qty}, in ${r.other_loc} ${r.other_qty}`;
                    rebList.appendChild(li);
                });
            }
        }

        // Consumption
        const consList = document.getElementById('consumptionList');
        if (consList) {
            consList.innerHTML = '';
            Object.entries(dataProd.consumption).forEach(([name, val]) => {
                const li = document.createElement('li');
                li.textContent = `${name}: ${Math.abs(val)} verbraucht`;
                consList.appendChild(li);
            });
        }

        // Distribution Chart
        const ctxDist = document.getElementById('chartDistribution');
        if (ctxDist && window.Chart) {
            if (A.charts.dist) A.charts.dist.destroy();
            A.charts.dist = new Chart(ctxDist, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(dataProd.distribution),
                    datasets: [{
                        data: Object.values(dataProd.distribution),
                        backgroundColor: ['#2a9df4', '#ff7b88', '#7bd389', '#f4a261', '#e76f51']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

    } catch (e) { console.error(e); }
}

function setupSSE() {
    const ev = new EventSource('sse.php');
    ev.addEventListener('inventory', e => {
        A.inv = JSON.parse(e.data);
        renderAdmin();
    });
    ev.addEventListener('logtick', () => {
        if ($('#tab-logs').classList.contains('active')) loadLogs();
    });
}
