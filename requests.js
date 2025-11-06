// requests.js — Client UI & API interaction for Requests
// Einbinden: <script src="requests.js"></script> nach deinem main index.js

// Helpers
function el(sel){ return document.querySelector(sel); }
function create(tag, cls){ const d=document.createElement(tag); if(cls) d.className=cls; return d; }

const Requests = {
  init() {
    this.cacheElements();
    this.bind();
    this.selected = {}; // {name:qty}
    this.loadRequests(); // prefetch
    // SSE listener (optional) - listens to 'requests' events
    try {
      const es = new EventSource('sse.php');
      es.addEventListener('requests', ev=>{
        console.log('SSE: requests', ev.data);
        this.renderRequestsList(JSON.parse(ev.data));
      });
    } catch(e){ console.warn('SSE not available for requests', e); }
  },

  cacheElements() {
    this.fab = el('#fab');
    this.fabMenu = el('#fabMenu');
    this.fabAdd = el('#fabAdd');
    this.fabRequest = el('#fabRequest');
    this.fabList = el('#fabList');
    this.requestModal = el('#requestModal');
    this.requestsModal = el('#requestsModal');
    this.reqCancel = el('#reqCancel');
    this.reqSubmit = el('#reqSubmit');
    this.reqLocation = el('#reqLocation');
    this.reqItemsList = el('#reqItemsList');
    this.reqSearch = el('#reqSearch');
    this.reqSelected = el('#reqSelected');
    this.requestsList = el('#requestsList');
    this.requestsClose = el('#requestsClose');
    this.fabAddModalOpener = null; // connect to your add-item modal if wanted
  },

  bind() {
    this.fab.onclick = ()=> this.toggleFab();
    this.fabAdd.onclick = ()=> { this.openAddModal(); this.toggleFab(true); };
    this.fabRequest.onclick = ()=> { this.openRequestModal(); this.toggleFab(true); };
    this.fabList.onclick = ()=> { this.openRequestsModal(); this.toggleFab(true); };

    this.reqCancel.onclick = ()=> this.closeRequestModal();
    this.reqSubmit.onclick = ()=> this.submitRequest();

    this.requestsClose.onclick = ()=> this.closeRequestsModal();

    this.reqSearch.addEventListener('input', ()=> this.renderItemsList());
  },

  toggleFab(forceClose=false){
    if (forceClose) { this.fabMenu.classList.add('hidden'); this.fab.classList.remove('open'); return; }
    this.fab.classList.toggle('open');
    this.fabMenu.classList.toggle('hidden');
  },

  // open add-modal for existing add-item flow (optional)
  openAddModal(){ 
    // if you have modal from index.php for adding a single article, trigger it here
    const addButton = document.getElementById('fab'); // fallback
    // or open existing modal logic
    const modal = document.getElementById('modal');
    if (modal) modal.classList.remove('hidden');
  },

  // ----- Request modal UI -----
  openRequestModal() {
    this.selected = {};
    this.reqLocation.value = '';
    this.renderItemsList();
    this.renderSelected();
    this.requestModal.classList.remove('hidden');
  },
  closeRequestModal() {
    this.requestModal.classList.add('hidden');
  },

  // build searchable list from state.inv (global from index.js)
  renderItemsList() {
    const q = (this.reqSearch.value||'').toLowerCase().trim();
    this.reqItemsList.innerHTML = '';
    const inv = window.state && state.inv ? state.inv : {};
    const entries = Object.entries(inv).filter(([n]) => n.toLowerCase().includes(q));
    // create rows
    for (const [name, qty] of entries) {
      const row = create('div','req-row');
      const left = create('div','req-name'); left.textContent = name;
      const right = create('div','req-controls');
      const have = create('div','req-have'); have.textContent = '✦ '+qty;
      const input = create('input','req-qty');
      input.type='number'; input.min='0'; input.value = this.selected[name] || 0;
      input.oninput = () => {
        const val = Math.max(0, parseInt(input.value||'0',10));
        if (val === 0) delete this.selected[name]; else this.selected[name] = val;
        this.renderSelected();
      };
      right.append(have, input);
      row.append(left, right);
      this.reqItemsList.appendChild(row);
    }
  },

  renderSelected() {
    this.reqSelected.innerHTML = '';
    const inv = window.state && state.inv ? state.inv : {};
    const frag = document.createDocumentFragment();
    let totalWarn = false;
    Object.entries(this.selected).forEach(([name, qty])=>{
      const item = create('div','sel-item');
      const n = create('div','sel-name'); n.textContent = name;
      const q = create('div','sel-qty'); q.textContent = qty;
      // warning if requested > inventory
      const have = inv[name] ?? 0;
      if (qty > have) {
        const warn = create('div','sel-warn'); warn.textContent = '⚠︎ nur '+have+' im Lager';
        item.append(n,q,warn);
        totalWarn = true;
      } else {
        item.append(n,q);
      }
      frag.appendChild(item);
    });
    if (!frag.childNodes.length) {
      this.reqSelected.textContent = 'Noch nichts ausgewählt';
    } else {
      this.reqSelected.appendChild(frag);
    }
    // enable/disable submit
    this.reqSubmit.disabled = (Object.keys(this.selected).length === 0);
    // optionally show a global warning
    if (totalWarn) {
      this.reqSelected.classList.add('has-warn');
    } else {
      this.reqSelected.classList.remove('has-warn');
    }
  },

  async submitRequest() {
    const loc = (this.reqLocation.value || '').trim();
    if (!loc) { alert('Bitte Lieferort angeben'); return; }
    const items = [];
    Object.entries(this.selected).forEach(([name, qty]) => items.push({name, qty}));
    // Post to requests.php
    try {
      const res = await fetch('requests.php?action=create', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({location: loc, items: items, clientId: (crypto.randomUUID?crypto.randomUUID():null)})
      });
      const j = await res.json();
      if (!res.ok) { alert('Fehler: '+(j.error||'unknown')); return; }
      console.log('Request created', j);
      // close and clear
      this.closeRequestModal();
      // Optionally show confirmation
      alert('Request erstellt');
      // the server will trigger SSE -> other clients + admin are notified
    } catch (e) {
      console.error(e);
      alert('Netzwerkfehler');
    }
  },

  // ----- Requests list Modal (user/provider view) -----
  async loadRequests() {
    try {
      const r = await fetch('requests.php?action=list');
      const j = await r.json();
      if (j.requests) this.renderRequestsList(j.requests);
    } catch(e) { console.warn(e); }
  },
  openRequestsModal() {
    this.loadRequests();
    this.requestsModal.classList.remove('hidden');
  },
  closeRequestsModal() {
    this.requestsModal.classList.add('hidden');
  },
  renderRequestsList(data) {
    // data may be array or object
    const arr = Array.isArray(data) ? data : (data.requests || []);
    this.requestsList.innerHTML = '';
    if (!arr.length) { this.requestsList.textContent = 'Keine offenen Requests'; return; }
    arr.forEach(r=>{
      const card = create('div','request-card');
      const hdr = create('div','request-hdr');
      hdr.innerHTML = `<strong>${r.location}</strong> <small>${new Date(r.created).toLocaleString()}</small>`;
      const body = create('div','request-body');
      r.items.forEach(it=>{
        const itEl = create('div','request-item');
        itEl.textContent = `${it.name} — ${it.qty}`;
        body.appendChild(itEl);
      });
      const foot = create('div','request-foot');
      const status = create('div','request-status'); status.textContent = (r.status||'open');
      foot.appendChild(status);

      // If provider/admin view: add Fulfill & Delete
      const btnFulfill = create('button','btn small primary'); btnFulfill.textContent='Erledigen';
      btnFulfill.onclick = async ()=>{
        if (!confirm('Request als erfüllt markieren? (Inventar wird reduziert)')) return;
        const res = await fetch('requests.php?action=fulfill', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({id: r.id})
        });
        const j = await res.json();
        if (j.ok) { alert('Erledigt'); this.loadRequests(); }
        else if (j.shortages) {
          let msg = 'Nicht ausreichend Bestand für:\n';
          j.shortages.forEach(s=> msg += `${s.name}: ${s.have} / ${s.want}\n`);
          alert(msg);
        } else alert('Fehler');
      };

      const btnDelete = create('button','btn small danger'); btnDelete.textContent='Löschen';
      btnDelete.onclick = async ()=>{
        if (!confirm('Request löschen?')) return;
        const res = await fetch('requests.php?action=delete', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({id: r.id})
        });
        const j = await res.json();
        if (j.ok) { this.loadRequests(); }
        else alert('Fehler');
      };

      foot.appendChild(btnFulfill);
      foot.appendChild(btnDelete);

      card.append(hdr, body, foot);
      this.requestsList.appendChild(card);
    });
  }
};

// Auto-init after DOM ready
document.addEventListener('DOMContentLoaded', ()=> Requests.init());