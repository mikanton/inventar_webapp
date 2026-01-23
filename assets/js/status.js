// assets/js/status.js

let charts = {};
let lastNet = null;
const MAX_POINTS = 30;

document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    poll();
    setInterval(poll, 2000);
});

async function poll() {
    try {
        const res = await fetch('api.php?action=system_stats');
        const data = await res.json();
        updateUI(data);
    } catch (e) { console.error(e); }
}

function updateUI(data) {
    // uptime
    document.getElementById('uptime').textContent = 'Uptime: ' + data.uptime;

    // CPU
    document.getElementById('cpuTemp').textContent = data.cpu.temp + '°C';
    document.getElementById('cpuLoad').textContent = `Load: ${data.cpu.load.join(' ')}`;
    addData(charts.cpu, data.cpu.load[0]); // 1 min load

    // RAM
    document.getElementById('ramPercent').textContent = data.ram.percent + '%';
    document.getElementById('ramText').textContent = `${data.ram.used} / ${data.ram.total} MB`;
    document.getElementById('ramBar').style.width = data.ram.percent + '%';
    addData(charts.ram, data.ram.percent);

    // Network (Calc Scan Speed)
    if (lastNet) {
        // Delta bytes / 2 seconds = B/s
        const rxSpeed = (data.net.rx - lastNet.rx) / 2;
        const txSpeed = (data.net.tx - lastNet.tx) / 2;

        document.getElementById('netRx').textContent = formatSpeed(rxSpeed);
        document.getElementById('netTx').textContent = formatSpeed(txSpeed);

        addData(charts.net, rxSpeed, 0);
        addData(charts.net, txSpeed, 1);
    }
    lastNet = data.net;

    // Clients
    const list = document.getElementById('clientList');
    list.innerHTML = '';
    Object.keys(data.clients).forEach(id => {
        const c = data.clients[id];
        const age = Math.round((Date.now() / 1000) - c.last_seen);
        const li = document.createElement('li');
        li.className = 'client-item';
        li.innerHTML = `
            <div>
                <strong>${id.substring(0, 8)}...</strong>
                <div class="client-ua" title="${c.ua}">${c.ua}</div>
            </div>
            <span>${age}s ago</span>
        `;
        list.appendChild(li);
    });
}

function formatSpeed(bytes) {
    if (bytes > 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB/s';
    if (bytes > 1024) return (bytes / 1024).toFixed(1) + ' KB/s';
    return bytes + ' B/s';
}

function initCharts() {
    const common = {
        type: 'line',
        options: {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            scales: { x: { display: false }, y: { beginAtZero: true } },
            plugins: { legend: { display: false } },
            elements: { point: { radius: 0 } }
        }
    };

    // CPU Chart
    charts.cpu = new Chart(document.getElementById('chartCpu'), {
        ...common,
        data: { labels: [], datasets: [{ label: 'Load', data: [], borderColor: '#ef4444', borderWidth: 2, fill: true, backgroundColor: 'rgba(239,68,68,0.1)' }] }
    });

    // RAM Chart
    charts.ram = new Chart(document.getElementById('chartRam'), {
        ...common,
        data: { labels: [], datasets: [{ label: 'RAM %', data: [], borderColor: '#3b82f6', borderWidth: 2, fill: true, backgroundColor: 'rgba(59,130,246,0.1)' }] },
        options: { ...common.options, scales: { ...common.options.scales, y: { min: 0, max: 100 } } }
    });

    // Net Chart
    charts.net = new Chart(document.getElementById('chartNet'), {
        ...common,
        data: {
            labels: [],
            datasets: [
                { label: 'DL', data: [], borderColor: '#10b981', borderWidth: 2 },
                { label: 'UL', data: [], borderColor: '#3b82f6', borderWidth: 2 }
            ]
        }
    });
}

function addData(chart, val, datasetIndex = 0) {
    if (chart.data.labels.length > MAX_POINTS) {
        chart.data.labels.shift();
        chart.data.datasets.forEach(ds => ds.data.shift());
    }
    chart.data.labels.push('');
    chart.data.datasets[datasetIndex].data.push(val);
    chart.update();
}
