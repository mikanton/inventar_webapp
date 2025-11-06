<?php /* admin.php – Admin mit Tabs + Charts */ ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inventar – Admin</title>
<link rel="stylesheet" href="style.css">
<script defer src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<header class="topbar">
  <h1 class="title">Admin</h1>
</header>

<main class="content">
  <!-- Tabs -->
  <nav class="tabs">
    <button class="tab active" data-tab="inv">Inventar</button>
    <button class="tab" data-tab="logs">Logs</button>
    <button class="tab" data-tab="online">Online</button>
    <button class="tab" data-tab="analytics">Analytics</button>
  </nav>

  <!-- Inventar -->
  <section id="tab-inv" class="panel tabcontent active">
    <div class="toolbar">
      <div class="segmented">
        <button id="aSortAlpha" class="seg active">A–Z</button>
        <button id="aSortQty" class="seg">Menge</button>
      </div>
      <div class="row gap">
        <input id="aName" type="text" placeholder="Artikelname">
        <input id="aQty" type="number" min="0" value="0">
        <button id="aAdd" class="btn primary">Hinzufügen/Setzen</button>
      </div>
    </div>
    <div id="adminList" class="list"></div>
  </section>

  <!-- Logs -->
  <section id="tab-logs" class="panel tabcontent">
    <ul id="logs" class="logs"></ul>
  </section>

  <!-- Online -->
  <section id="tab-online" class="panel tabcontent">
    <h3>Online-Geräte (≤ 2 min)</h3>
    <ul id="clients" class="logs"></ul>
    <h3>Alle bekannten User</h3>
    <div class="table-wrap">
      <table id="userTable" class="table">
        <thead><tr><th>ID</th><th>First Seen</th><th>Last Seen</th><th>Hits</th><th>IP</th><th>UA</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </section>

  <!-- Analytics -->
  <section id="tab-analytics" class="panel tabcontent">
    <div class="grid2">
      <div>
        <h3>Änderungen pro Tag</h3>
        <canvas id="chartActivity" height="200"></canvas>
      </div>
      <div>
        <h3>Top User (Aktionen)</h3>
        <canvas id="chartUsers" height="200"></canvas>
      </div>
      <div class="full">
        <h3>Top Artikel (geändert)</h3>
        <canvas id="chartItems" height="220"></canvas>
      </div>
    </div>
  </section>
</main>

<script>
const A = {
  inv:{},
  clientId:(crypto.randomUUID?crypto.randomUUID():String(Math.random()).slice(2)),
  sort:'alpha',
  charts:{}
};

// ---- TABS ----
function switchTab(tab){
  document.querySelectorAll('.tab').forEach(b=>b.classList.toggle('active', b.dataset.tab===tab));
  document.querySelectorAll('.tabcontent').forEach(s=>s.classList.toggle('active', s.id === 'tab-'+tab));
  if (tab==='logs') loadLogs();
  if (tab==='online') loadOnline();
  if (tab==='analytics') loadAnalytics();
}
document.querySelectorAll('.tab').forEach(b=> b.addEventListener('click', ()=>switchTab(b.dataset.tab)));

// ---- INVENTAR RENDER ----
function renderAdmin(){
  const wrap=document.getElementById('adminList'); wrap.innerHTML='';
  let entries = Object.entries(A.inv);
  if (A.sort==='alpha') entries.sort((a,b)=>a[0].localeCompare(b[0],'de'));
  else entries.sort((a,b)=>b[1]-a[1]);

  entries.forEach(([name, qty])=>{
    const card=document.createElement('div'); card.className='card';
    const left=document.createElement('div'); left.className='name'; left.textContent=name;
    const controls=document.createElement('div'); controls.className='controls';
    const input=document.createElement('input'); input.type='number'; input.min='0'; input.value=qty;
    const setB=document.createElement('button'); setB.className='btn primary'; setB.textContent='Setzen';
    const delB=document.createElement('button'); delB.className='btn danger';  delB.textContent='Löschen';
    setB.onclick=()=>api('set',{name, value:parseInt(input.value||'0',10)});
    delB.onclick=()=>api('delete',{name});
    controls.append(input,setB,delB);
    card.append(left,controls);
    wrap.appendChild(card);
  });
}
document.getElementById('aSortAlpha').onclick=()=>{A.sort='alpha';document.getElementById('aSortAlpha').classList.add('active');document.getElementById('aSortQty').classList.remove('active');renderAdmin();};
document.getElementById('aSortQty').onclick=()=>{A.sort='qty';document.getElementById('aSortQty').classList.add('active');document.getElementById('aSortAlpha').classList.remove('active');renderAdmin();};

// Add/Set oben
document.getElementById('aAdd').onclick=()=>{
  const n=document.getElementById('aName').value.trim();
  const v=parseInt(document.getElementById('aQty').value||'0',10);
  if(!n) return;
  if (A.inv[n]===undefined) api('add',{name:n, qty:v}); else api('set',{name:n, value:v});
  document.getElementById('aName').value=''; document.getElementById('aQty').value='0';
};

// ---- API Helper ----
async function api(action,payload={}){
  await fetch(`api.php?action=${action}`,{
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({...payload, clientId:A.clientId})
  });
}

// ---- Logs ----
async function loadLogs(){
  try{
    const j=await (await fetch('api.php?action=logs')).json();
    const L=j.logs||[];
    const ul=document.getElementById('logs'); ul.innerHTML='';
    L.slice(-500).reverse().forEach(row=>{
      const li=document.createElement('li');
      const v=(row.value===null||row.value===undefined)?'—':row.value;
      li.textContent=`[${row.time}] ${row.clientId} → ${row.action} ${row.item} = ${v}`;
      ul.appendChild(li);
    });
  }catch(e){}
}

// ---- Online/Users ----
async function loadOnline(){
  try{
    const pres=await (await fetch('presence.json?ts='+Date.now())).json();
    const ul=document.getElementById('clients'); ul.innerHTML='';
    const now=Math.floor(Date.now()/1000);
    Object.entries(pres).sort((a,b)=>(b[1].last_seen)-(a[1].last_seen)).forEach(([id,info])=>{
      if (now-(info.last_seen||0)<=120){
        const li=document.createElement('li');
        li.textContent=`${id.slice(0,8)}…  | ${info.ip||''} | ${new Date(info.last_seen*1000).toLocaleTimeString()} | ${info.ua||''}`;
        ul.appendChild(li);
      }
    });

    const users=await (await fetch('users.json?ts='+Date.now())).json();
    const tb=document.querySelector('#userTable tbody'); tb.innerHTML='';
    Object.entries(users).sort((a,b)=>(b[1].last_seen)-(a[1].last_seen)).forEach(([id,u])=>{
      const tr=document.createElement('tr');
      tr.innerHTML=`<td>${id.slice(0,8)}…</td>
        <td>${new Date(u.first_seen*1000).toLocaleString()}</td>
        <td>${new Date(u.last_seen*1000).toLocaleString()}</td>
        <td>${u.hits||0}</td>
        <td>${u.ip||''}</td>
        <td class="ua">${(u.ua||'').slice(0,60)}</td>`;
      tb.appendChild(tr);
    });
  }catch(e){}
}

// ---- Analytics ----
async function loadAnalytics(){
  try{
    const a=await (await fetch('api.php?action=analytics')).json();
    const hasChart = !!window.Chart;

    // Day series
    const labels=Object.keys(a.byDay||{}); const values=Object.values(a.byDay||{});
    if (hasChart){
      if (A.charts.activity) A.charts.activity.destroy();
      A.charts.activity = new Chart(document.getElementById('chartActivity'),{
        type:'line',
        data:{labels, datasets:[{label:'Änderungen/Tag', data:values, tension:.3}]},
        options:{responsive:true, maintainAspectRatio:false}
      });
    } else {
      document.getElementById('chartActivity').replaceWith(Object.assign(document.createElement('div'),{textContent:'Chart.js nicht geladen'}));
    }

    // Users
    const uLabels=Object.keys(a.topUsers||{}).map(k=>k.slice(0,8)+'…');
    const uVals=Object.values(a.topUsers||{});
    if (hasChart){
      if (A.charts.users) A.charts.users.destroy();
      A.charts.users = new Chart(document.getElementById('chartUsers'),{
        type:'bar',
        data:{labels:uLabels, datasets:[{label:'Aktionen', data:uVals}]},
        options:{responsive:true, maintainAspectRatio:false}
      });
    }

    // Items
    const iLabels=Object.keys(a.topItems||{});
    const iVals=Object.values(a.topItems||{});
    if (hasChart){
      if (A.charts.items) A.charts.items.destroy();
      A.charts.items = new Chart(document.getElementById('chartItems'),{
        type:'bar',
        data:{labels:iLabels, datasets:[{label:'Änderungen', data:iVals}]},
        options:{responsive:true, maintainAspectRatio:false, indexAxis:'y'}
      });
    }
  }catch(e){}
}

// ---- SSE ----
const ev=new EventSource('sse.php');
ev.addEventListener('inventory', e=>{ A.inv = JSON.parse(e.data||'{}'); renderAdmin(); });
ev.addEventListener('logtick',  e=>{ loadLogs(); loadOnline(); loadAnalytics(); });

// ---- Presence Pings (damit Online-Tab gefüllt ist) ----
async function pingPresence(){
  try{
    await fetch('presence.php',{method:'POST',headers:{'Content-Type':'application/json'}, body:JSON.stringify({clientId:A.clientId})});
  }catch(e){}
}
pingPresence(); setInterval(pingPresence, 20000);

// ---- Init ----
fetch('api.php?action=get').then(r=>r.json()).then(j=>{ A.inv=j.inventory||{}; renderAdmin(); });
</script>
</body>
</html>
