<?php
// views/select_location.php
$title = 'Standort wählen';
ob_start();
?>
<div class="location-select-container">
    <h1>Wähle einen Standort</h1>
    <div class="location-grid">
        <?php foreach ($locations as $id => $name): ?>
            <button class="location-card" onclick="selectLocation(<?= $id ?>)">
                <div class="icon">📍</div>
                <div class="name"><?= htmlspecialchars($name) ?></div>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<script>
    async function selectLocation(id) {
        try {
            await fetch('api.php?action=set_location', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            window.location.reload();
        } catch (e) {
            console.error(e);
            alert('Fehler beim Setzen des Standorts');
        }
    }
</script>

<style>
    .location-select-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 80vh;
        padding: 20px;
        text-align: center;
    }

    .location-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
        max-width: 800px;
        width: 100%;
        margin-top: 40px;
    }

    .location-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 30px 20px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .location-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }

    .location-card .icon {
        font-size: 3em;
    }

    .location-card .name {
        font-size: 1.2em;
        font-weight: 600;
        color: var(--text-primary);
    }
</style>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
