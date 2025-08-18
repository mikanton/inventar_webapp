<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['file']['tmp_name'];

    // Backup anlegen
    if (file_exists("inventar.json")) {
        $backup_name = "backups/inventar_" . date("Y-m-d_H-i-s") . ".json";
        copy("inventar.json", $backup_name);
    }

    // Neue Liste einlesen und Mengen=0 setzen
    $lines = file($file_tmp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $items = [];
    foreach ($lines as $line) {
        $parts = explode(";", $line);
        $name = trim($parts[0]);
        if ($name !== "") {
            $items[] = ["name" => $name, "menge" => 0];
        }
    }

    // Neue Inventardatei mit Zeitstempel speichern
    $inventar = [
        "last_upload" => date("d.m.Y, H:i") . " Uhr",
        "items" => $items
    ];
    file_put_contents("inventar.json", json_encode($inventar, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    header("Location: index.php");
    exit();
} else {
    echo "Fehler beim Hochladen.";
}
?>