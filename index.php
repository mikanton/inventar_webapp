<?php /* index.php – Mobile-first Inventar + Requests UI */ ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inventar — Requests</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
  <div class="search-wrap">
    <input id="search" type="search" placeholder="Artikel suchen …">
  </div>
  <select id="sort" class="sort-select">
    <option value="nameAsc">Name A–Z</option>
    <option value="nameDesc">Name Z–A</option>
    <option value="qtyDesc">Menge ↓</option>
    <option value="qtyAsc">Menge ↑</option>
  </select>
</header>

<main class="main">
  <div id="list" class="list"></div>
</main>

<!-- Floating Action Button + Menu -->
<div class="fab-wrapper">
  <button id="fab" class="fab">＋</button>

  <div id="fabMenu" class="fab-menu hidden" aria-hidden="true">
    <button class="fab-mini" id="fabAdd" title="Neuen Artikel">＋</button>
    <button class="fab-mini" id="fabRequest" title="Neue Request">📦</button>
    <button class="fab-mini" id="fabSupplier" title="Lieferantenansicht">🚚</button>
  </div>
</div>

<!-- Add / Set Modal (existing add) -->
<div id="modalAdd" class="modal hidden">
  <div class="modal-card">
    <h3>Artikel hinzufügen / setzen</h3>
    <input id="addName" placeholder="Artikelname">
    <input id="addQty" type="number" min="0" value="0">
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
    <label class="field">
      <div class="label">Lieferort</div>
      <input id="reqLocation" placeholder="z. B. Lager A">
    </label>

    <div class="field">
      <div class="label">Artikel auswählen</div>
      <input id="reqSearch" placeholder="Artikel suchen …">
      <div id="reqItems" class="req-items"></div>
    </div>

    <div class="field">
      <div class="label">Ausgewählt</div>
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
    <div class="row top-actions">
      <button id="aggBtn" class="btn">Gesamtliste erzeugen</button>
      <button id="refreshRequests" class="btn">Aktualisieren</button>
      <button id="closeSupplier" class="btn">Schließen</button>
    </div>
    <div id="requestsList" class="requests-list"></div>

    <h4>Aggregierte Pickliste</h4>
    <div id="picklist" class="picklist"></div>
  </div>
</div>

<!-- include small inline script for functionality -->
<script>
/*
  index.js (inline) — Inventory UI + Requests UI + SSE + FAB
  - state.inv : map name -> qty
  - state.requests : array of requests
*/
const state = { inv: {}, requests: [], clientId: (crypto.randomUUID?crypto.randomUUID():String(Math.random()).slice(2)) };

// -------- DOM helpers
const $ = sel => document.querySelector(sel);
const $$ = sel => Array.from(document.querySelectorAll(sel));
const create = (t,c)=> { const e=document.createElement(t); if(c) e.className=c; return e; };

document.getElementById('search').addEventListener('input', render);
document.getElementById('sort').addEventListener('change', render);

// -------- render inventory list (mobile-first, big touch targets)
function render() {
  const q = ($('#search').value || '').trim().toLowerCase();
  const sort = $('#sort').value || 'nameAsc';
  const list = $('#list');
  list.innerHTML = '';

  // entries from state.inv
  const entries = Object.entries(state.inv || {}).filter(([n]) => n.toLowerCase().includes(q));
  entries.sort((a,b)=>{
    if (sort==='nameAsc') return a[0].localeCompare(b[0], 'de');
    if (sort==='nameDesc') return b[0].localeCompare(a[0], 'de');
    if (sort==='qtyAsc') return a[1]-b[1];
    if (sort==='qtyDesc') return b[1]-a[1];
    return 0;
  });

  for (const [name, qty] of entries) {
    const card = create('div','card item-row');

    const left = create('div','item-left');
    const title = create('div','item-name'); title.textContent = name;
    left.appendChild(title);

    const right = create('div','item-right');

    const btnMinus = create('button','btn-circle minus'); btnMinus.textContent='−';
    btnMinus.onclick = async ()=> {
      try {
        await api('update', {name, delta:-1});
        // optimistic update
        state.inv[name] = Math.max(0, (state.inv[name]||0) - 1);
        render();
      } catch(e) { console.error(e); }
    };

    const qtyEl = create('div','item-qty'); qtyEl.textContent = qty;

    // if zero, show warning symbol inline
    if (qty === 0) {
      qtyEl.innerHTML = '⚠︎ 0';
      card.classList.add('empty');
    }

    const btnPlus = create('button','btn-circle plus'); btnPlus.textContent='+';
    btnPlus.onclick = async ()=> {
      try {
        await api('update', {name, delta:1});
        state.inv[name] = (state.inv[name]||0) + 1;
        render();
      } catch(e) { console.error(e); }
    };

    right.append(btnMinus, qtyEl, btnPlus);
    card.append(left, right);
    list.appendChild(card);
  }
}

// --------- API wrapper
async function api(action, payload={}) {
  // Some actions are read-only; we use POST for mutating actions.
  const res = await fetch(`api.php?action=${action}`, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({...payload, clientId: state.clientId})
  });
  if (!res.ok) throw new Error('API error '+res.status);
  const j = await res.json();
  // update local state if server returned data
  if (j.inventory) state.inv = j.inventory;
  if (j.requests) state.requests = j.requests;
  return j;
}

// -------- SSE – live updates
(function startSSE(){
  try {
    const es = new EventSource('sse.php');
    es.addEventListener('sync', ev=>{
      try {
        const p = JSON.parse(ev.data);
        if (p.inventory) state.inv = p.inventory;
        if (p.requests) state.requests = p.requests;
        render();
      } catch(e){ console.warn('sync parse', e); }
    });
    es.addEventListener('inventory', ev=>{
      try { state.inv = JSON.parse(ev.data); render(); } catch(e){}
    });
    es.addEventListener('requests', ev=>{
      try { state.requests = JSON.parse(ev.data); /* optionally update requests UI */ } catch(e){}
    });
    es.addEventListener('ping', ev => {
      // keepalive
    });
    es.onerror = function(e) { console.warn('SSE error', e); };
  } catch(e) {
    console.warn('SSE not available', e);
  }
})();

// -------- UI: FAB menu
const fab = $('#fab');
const fabMenu = $('#fabMenu');
fab.addEventListener('click', ()=> {
  fab.classList.toggle('open');
  fabMenu.classList.toggle('hidden');
});

// FAB item actions
$('#fabAdd').addEventListener('click', ()=> {
  $('#modalAdd').classList.remove('hidden');
  $('#addName').value='';
  $('#addQty').value='0';
  fabMenu.classList.add('hidden'); fab.classList.remove('open');
});
$('#fabRequest').addEventListener('click', ()=> {
  openRequestModal();
  fabMenu.classList.add('hidden'); fab.classList.remove('open');
});
$('#fabSupplier').addEventListener('click', ()=> {
  openSupplierModal();
  fabMenu.classList.add('hidden'); fab.classList.remove('open');
});

// -------- Add modal actions
$('#addCancel').addEventListener('click', ()=> $('#modalAdd').classList.add('hidden'));
$('#addSave').addEventListener('click', async ()=>{
  const name = $('#addName').value.trim();
  const qty = parseInt($('#addQty').value||'0',10);
  if (!name) return alert('Name benötigt');
  try {
    await api('add', {name, qty});
    $('#modalAdd').classList.add('hidden');
    render();
  } catch(e) { alert('Fehler beim Hinzufügen'); console.error(e); }
});

// -------- Request modal: selection UI
let reqSelected = {}; // {name: qty}
function openRequestModal() {
  reqSelected = {};
  $('#reqLocation').value='';
  $('#reqSelected').textContent = 'Noch nichts ausgewählt';
  renderReqItems();
  $('#modalRequest').classList.remove('hidden');
  $('#reqSubmit').disabled = true;
}

function closeRequestModal() {
  $('#modalRequest').classList.add('hidden');
}

$('#reqCancel').addEventListener('click', ()=> closeRequestModal());
$('#reqSearch').addEventListener('input', renderReqItems);

function renderReqItems() {
  const q = ($('#reqSearch').value||'').toLowerCase().trim();
  const wrap = $('#reqItems');
  wrap.innerHTML = '';
  const inv = state.inv || {};
  const entries = Object.entries(inv).filter(([n]) => n.toLowerCase().includes(q));
  // show touch-friendly list with an input per item (or add button)
  for (const [name, have] of entries) {
    const row = create('div','req-row');
    const n = create('div','req-name'); n.textContent = name;
    const controls = create('div','req-controls');
    const input = create('input','req-input'); input.type='number'; input.min='0'; input.value = reqSelected[name] || 0;
    input.addEventListener('input', () => {
      const val = Math.max(0, parseInt(input.value||'0',10));
      if (val === 0) delete reqSelected[name]; else reqSelected[name] = val;
      updateReqSelectedBox();
    });
    controls.append(input);
    const haveEl = create('div','req-have'); haveEl.textContent = '● '+have;
    row.append(n, controls, haveEl);
    wrap.appendChild(row);
  }
}

function updateReqSelectedBox() {
  const box = $('#reqSelected');
  box.innerHTML = '';
  let any=false; let warn=false;
  const inv = state.inv || {};
  Object.entries(reqSelected).forEach(([name, qty])=>{
    any = true;
    const row = create('div','sel-row');
    const left = create('div','sel-name'); left.textContent = name;
    const right = create('div','sel-q'); right.textContent = qty;
    row.append(left, right);
    if ((inv[name] || 0) < qty) {
      const w = create('div','sel-warn'); w.textContent = '⚠︎ nur '+(inv[name]||0)+' vorhanden';
      row.appendChild(w);
      warn = true;
    }
    box.appendChild(row);
  });
  if (!any) box.textContent = 'Noch nichts ausgewählt';
  $('#reqSubmit').disabled = !any;
  if (warn) box.classList.add('warn'); else box.classList.remove('warn');
}

// request submit
$('#reqSubmit').addEventListener('click', async ()=>{
  const place = ($('#reqLocation').value || '').trim();
  if (!place) return alert('Bitte Lieferort angeben');
  const items = Object.entries(reqSelected).map(([name,qty]) => ({name, qty}));
  try {
    const res = await api('request_create', {location: place, items});
    alert('Request erstellt');
    closeRequestModal();
    render(); // inventory may not change yet
  } catch(e) {
    alert('Fehler beim Erstellen der Request'); console.error(e);
  }
});

// -------- Supplier modal
function openSupplierModal(){
  $('#requestsList').innerHTML = 'Lade…';
  $('#modalSupplier').classList.remove('hidden');
  loadRequests();
}
function closeSupplierModal(){ $('#modalSupplier').classList.add('hidden'); }

$('#closeSupplier').addEventListener('click', ()=> closeSupplierModal());
$('#refreshRequests').addEventListener('click', ()=> loadRequests());
$('#aggBtn').addEventListener('click', ()=> createAggregate());

// load requests and render
async function loadRequests(){
  try {
    const r = await fetch('api.php?action=request_list', {method:'POST'});
    const j = await r.json();
    state.requests = j.requests || [];
    renderRequests();
  } catch(e) { console.error(e); $('#requestsList').textContent='Fehler'; }
}

function renderRequests(){
  const wrap = $('#requestsList');
  wrap.innerHTML = '';
  if (!state.requests || state.requests.length===0) { wrap.textContent='Keine Requests'; return; }
  state.requests.forEach(r=>{
    const card = create('div','request-card');
    const hdr = create('div','request-hdr'); hdr.innerHTML = `<strong>${r.location}</strong> <small>${new Date(r.created).toLocaleString()}</small>`;
    const body = create('div','request-body');
    r.items.forEach(it=>{
      const itEl = create('div','request-item');
      itEl.textContent = `${it.name} — ${it.qty}`;
      body.appendChild(itEl);
    });
    const foot = create('div','request-foot');
    const status = create('div','request-status'); status.textContent = r.status || 'open';
    foot.appendChild(status);

    // Fulfill button
    if ((r.status || '') === 'open') {
      const btnOk = create('button','btn small primary'); btnOk.textContent='Erledigen';
      btnOk.addEventListener('click', async ()=>{
        if (!confirm('Request als erfüllt markieren? Inventar wird reduziert.')) return;
        try {
          const res = await fetch('api.php?action=request_fulfill', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({id: r.id})
          });
          const j = await res.json();
          if (j.ok) {
            alert('Erledigt');
            loadRequests();
          } else if (j.shortages) {
            let msg = 'Nicht ausreichend Bestand für:\n';
            j.shortages.forEach(s => msg += `${s.name}: ${s.have} / ${s.want}\n`);
            alert(msg);
          } else alert('Fehler');
        } catch(e) { console.error(e); alert('Fehler'); }
      });
      foot.appendChild(btnOk);
    }

    // Delete
    const btnDel = create('button','btn small danger'); btnDel.textContent='Löschen';
    btnDel.addEventListener('click', async ()=>{
      if (!confirm('Request löschen?')) return;
      try {
        await fetch('api.php?action=request_delete', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({id: r.id})
        });
        loadRequests();
      } catch(e){ console.error(e); }
    });
    foot.appendChild(btnDel);

    card.append(hdr, body, foot);
    wrap.appendChild(card);
  });
}

// aggregate picklist
async function createAggregate(){
  try {
    const r = await fetch('api.php?action=request_aggregate', {method:'POST'});
    const j = await r.json();
    const pick = j.picklist || [];
    const wrap = $('#picklist');
    wrap.innerHTML = '';
    if (!pick.length) { wrap.textContent='Keine offenen Positionen'; return; }
    pick.forEach(p=>{
      const row = create('div','pick-row');
      row.textContent = `${p.name} — ${p.qty}`;
      wrap.appendChild(row);
    });
  } catch(e){ console.error(e); }
}

// heartbeat presence (optional)
setInterval(()=> {
  fetch('presence.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({clientId: state.clientId})
  }).catch(()=>{});
}, 20000);

// init: fetch initial state
(async function init(){
  try {
    const r = await fetch('api.php?action=get', {method:'POST'});
    const j = await r.json();
    state.inv = j.inventory || {};
    state.requests = j.requests || [];
    render();
  } catch(e) { console.error(e); }
})();


// small keyboard bindings: / to focus search
document.addEventListener('keydown', (ev)=>{
  if (ev.key==='/' && document.activeElement !== $('#search')) {
    ev.preventDefault();
    $('#search').focus();
  }
});
</script>
</body>
</html>
