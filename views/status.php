<?php
$title = 'System Status';
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="assets/js/status.js"></script>';
ob_start();
?>
<div class="row" style="margin-bottom: 20px; align-items: center;">
    <h1>System Status</h1>
    <div style="margin-left: auto; font-family: monospace; color: var(--text-muted);" id="uptime">Uptime: ...</div>
</div>

<div class="grid2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">

    <!-- CPU & Temp -->
    <div class="card" style="display: block;">
        <h3>CPU Load & Temp</h3>
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <div id="cpuTemp" style="font-size: 2rem; font-weight: bold; color: var(--danger);">--°C</div>
            <div id="cpuLoad" style="text-align: right; color: var(--text-muted);">Load: --</div>
        </div>
        <div style="height: 150px;"><canvas id="chartCpu"></canvas></div>
    </div>

    <!-- Memory -->
    <div class="card" style="display: block;">
        <h3>Memory Usage</h3>
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <div id="ramPercent" style="font-size: 2rem; font-weight: bold; color: var(--primary);">--%</div>
            <div id="ramText" style="text-align: right; color: var(--text-muted);">-- / -- MB</div>
        </div>
        <div class="progress-bar"
            style="background: rgba(255,255,255,0.1); border-radius: 4px; height: 10px; overflow: hidden;">
            <div id="ramBar" style="width: 0%; background: var(--primary); height: 100%; transition: width 0.5s;"></div>
        </div>
        <div style="height: 140px; margin-top: 10px;"><canvas id="chartRam"></canvas></div>
    </div>

    <!-- Network -->
    <div class="card" style="display: block;">
        <h3>Network Traffic</h3>
        <div style="display: flex; gap: 20px; margin-bottom: 10px;">
            <div>
                <small>Download</small>
                <div id="netRx" style="font-size: 1.5rem; fontWeight: bold; color: var(--accent);">-- KB/s</div>
            </div>
            <div>
                <small>Upload</small>
                <div id="netTx" style="font-size: 1.5rem; fontWeight: bold; color: var(--primary);">-- KB/s</div>
            </div>
        </div>
        <div style="height: 150px;"><canvas id="chartNet"></canvas></div>
    </div>

    <!-- Clients -->
    <div class="card" style="display: block;">
        <h3>Active Clients</h3>
        <ul id="clientList" class="list" style="max-height: 200px; overflow-y: auto;">
            <li>Scanning...</li>
        </ul>
    </div>
</div>

<style>
    .client-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--border);
        font-size: 0.9rem;
    }

    .client-ua {
        color: var(--text-muted);
        font-size: 0.8rem;
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
