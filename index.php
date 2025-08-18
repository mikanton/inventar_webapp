<?php
session_start();
if(!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}

// Inventar laden
$inventar = json_decode(file_get_contents('inventar.json'), true);
?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventarliste</title>
<style>
/* Grundlayout */
body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    background: #f0f2f5;
    margin: 0;
    padding: 0;
}
header {
    background: #007bff;
    color: white;
    padding: 15px 20px;
    text-align: center;
    font-size: 1.8em;
    font-weight: bold;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
.container {
    max-width: 900px;
    margin: 30px auto;
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 12px 15px;
    text-align: left;
}
th {
    background: #007bff;
    color: white;
    text-transform: uppercase;
    font-size: 0.9em;
}
tr:nth-child(even) {
    background: #f9f9f9;
}
button {
    padding: 5px 12px;
    margin: 0 3px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: 0.2s;
}
button.plus {
    background: #28a745;
    color: white;
}
button.minus {
    background: #dc3545;
    color: white;
}
button.plus:hover {
    background: #218838;
}
button.minus:hover {
    background: #c82333;
}

/* Mobile */
@media (max-width: 600px) {
    th, td {
        padding: 10px;
    }
    button {
        padding: 4px 10px;
        font-size: 0.85em;
    }
}
</style>
</head>
<body>
<header>Inventarliste</header>
<div class="container">
<table>
<tr><th>Artikel</th><th>Menge</th><th>Aktionen</th></tr>
<?php foreach($inventar as $artikel => $menge): ?>
<tr>
<td><?= htmlspecialchars($artikel) ?></td>
<td id="menge-<?= md5($artikel) ?>"><?= $menge ?></td>
<td>
<button class="plus" onclick="updateInventar('<?= addslashes($artikel) ?>', 1)">+</button>
<button class="minus" onclick="updateInventar('<?= addslashes($artikel) ?>', -1)">-</button>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>

<script>
function updateInventar(artikel, delta) {
    fetch('update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'artikel=' + encodeURIComponent(artikel) + '&delta=' + delta
    })
    .then(response => response.json())
    .then(data => {
        if(data.success){
            document.getElementById('menge-' + data.id).textContent = data.neu;
        } else {
            alert('Fehler: ' + data.error);
        }
    });
}
</script>

</body>
</html>
