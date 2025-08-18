<?php
session_start();

// Feste Benutzer
$users = [
    "admin" => password_hash("BitteÄndern123!", PASSWORD_DEFAULT),
    "user" => password_hash("userpass", PASSWORD_DEFAULT)
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (isset($users[$username]) && password_verify($password, $users[$username])) {
        $_SESSION['username'] = $username;
        header("Location: index.php");
        exit();
    } else {
        $error = "Login fehlgeschlagen.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Login</title>
</head>
<body>
<h1>Login</h1>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<form method="post">
    Benutzername: <input type="text" name="username" required><br>
    Passwort: <input type="password" name="password" required><br>
    <button type="submit">Login</button>
</form>
</body>
</html>