<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Router.php';

$router = new Router();

// Home
$router->add('GET', 'home', function () use ($router) {
  if (!Auth::isLocationSelected()) {
    require_once __DIR__ . '/includes/Database.php';
    $pdo = Database::connect();
    $locations = $pdo->query("SELECT id, name FROM locations")->fetchAll(PDO::FETCH_KEY_PAIR);
    require __DIR__ . '/views/select_location.php';
    return;
  }
  require __DIR__ . '/views/home.php';
});

// Change Location
$router->add('GET', 'change_location', function () {
  Auth::clearLocationSelection();
  header('Location: index.php');
});

// Login
$router->add('GET', 'login', function () use ($router) {
  if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
  }
  require __DIR__ . '/views/login.php';
});

$router->add('POST', 'login', function () {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';

  if (Auth::login($username, $password)) {
    header('Location: index.php');
  } else {
    $error = 'Ungültige Zugangsdaten';
    require __DIR__ . '/views/login.php';
  }
});

// Logout
$router->add('GET', 'logout', function () {
  Auth::logout();
  header('Location: index.php');
});

// Admin
$router->add('GET', 'admin', function () use ($router) {
  require __DIR__ . '/views/admin.php';
});

$router->dispatch();
