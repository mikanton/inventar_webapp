<?php
session_start();

// Prüfen ob Benutzer eingeloggt ist
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Inventardatei
$inventar_file = "inventar.json";

// Inventar laden
$inventar = [];
if (file_exists($inventar_file)) {
    $inventar = json_decode(file_get_contents($inventar_file), true);
}

// Zeitstempel laden
$timestamp = "";
if (isset($inventar['last_upload'])) {
    $timestamp = $inventar['last_upload'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Inventarliste</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        h1 { background: #333; color: white; padding: 10px; }
        table { border-collapse: collapse; width: 100%; background: white; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        tr:nth-child(even) { background: #f9f9f9; }
        .upload-btn { background: #4CAF50; color: white; padding: 10px; border: none; cursor: pointer; margin: 10px 0; }
    </style>
</head>
<body>
<h1>Inventarliste <?php if ($timestamp) { echo "<small>(Letzter Upload: $timestamp)</small>"; } ?></h1>

<!-- Upload-Formular direkt im Haupttab -->
<form action="upload.php" method="post" enctype="multipart/form-data">
    <input type="file" name="file" required>
    <button type="submit" class="upload-btn">Neue Liste hochladen</button>
</form>

<table>
<tr><th>Artikel</th><th>Menge</th></tr>
<?php
if (isset($inventar['items']) && is_array($inventar['items'])) {
    foreach ($inventar['items'] as $item) {
        echo "<tr><td>" . htmlspecialchars($item['name']) . "</td><td>" . intval($item['menge']) . "</td></tr>";
    }
}
?>
</table>
</body>
</html>