<?php
$title = 'Login';
ob_start();
?>
<div class="login-wrapper">
    <div class="login-card">
        <h1>Inventar Login</h1>
        <?php if (isset($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" action="index.php?route=login">
            <input type="hidden" name="csrf_token" value="<?= Auth::getCsrfToken() ?>">
            <div class="field">
                <label>Benutzername</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="field">
                <label>Passwort</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn primary full">Einloggen</button>
            <div style="margin-top: 15px; text-align: center;">
                <a href="index.php?route=register" class="btn secondary full">Neuen Account erstellen</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
