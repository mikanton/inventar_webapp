<?php
$title = 'Registrieren';
ob_start();
?>
<div class="login-wrapper">
    <div class="login-card">
        <h1>Neuen Account erstellen</h1>
        <?php if (isset($error)): ?>
            <div class="alert error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="post" action="index.php?route=register">
            <div class="field">
                <label>Benutzername</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="field">
                <label>Passwort</label>
                <input type="password" name="password" required>
            </div>
            <div class="field">
                <label>Passwort bestätigen</label>
                <input type="password" name="password_confirm" required>
            </div>
            <button type="submit" class="btn primary full">Registrieren</button>
            <div style="margin-top: 15px; text-align: center;">
                <a href="index.php?route=login" style="color: var(--primary);">Zurück zum Login</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
