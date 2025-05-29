<?php
/****************************************************
 * Файл: regadmin.php
 * Страница для регистрации админов + вход партнёра
 ****************************************************/
session_start();
require_once 'functions.php';

// Если уже авторизован, можно при желании перекинуть на dashboard
// if (isset($_SESSION['user'])) {
//    header("Location: index.php?page=dashboard");
//    exit;
// }

?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Регистрация Админа / Вход Партнёра</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<nav class="topnav">
  <div class="logo">ReferralPlatform</div>
  <a href="index.php">На главную</a>
  <?php if(!isset($_SESSION['user'])): ?>
    <!-- Если не авторизован, кнопка "Логин" для админа/партнёра -->
    <a href="adminlogin.php" class="btn-small">Вход для Админа</a>
  <?php else: ?>
    <a href="index.php?page=dashboard">Личный кабинет</a>
    <a href="regadmin.php?logout=1" class="btn-small btn-danger">Выйти</a>
  <?php endif; ?>
</nav>

<div class="container fade-in">
<?php
// Обработка форм
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1) Регистрация админа
    if (isset($_POST['action']) && $_POST['action'] === 'register_admin') {
        $email = trim($_POST['email'] ?? '');
        $pass  = trim($_POST['password'] ?? '');
        if ($email && $pass) {
            $res = registerAdmin($email, $pass);
            if ($res) {
                echo "<p class='alert alert-success'>Админ <b>{$email}</b> успешно зарегистрирован!</p>";
            } else {
                echo "<p class='alert alert-danger'>Ошибка: админ с таким email уже существует!</p>";
            }
        } else {
            echo "<p class='alert alert-danger'>Заполните все поля!</p>";
        }
    }

    // 2) Вход партнёра
    if (isset($_POST['action']) && $_POST['action'] === 'partner_login') {
        $em  = trim($_POST['email'] ?? '');
        $pw  = trim($_POST['password'] ?? '');
        if ($em && $pw) {
            $user = loginUser($em, $pw); // функция loginUser(...) в functions.php
            // Проверяем, что user с role='partner'
            if ($user && $user['role'] === 'partner') {
                // Сохраняем в сессию
                $_SESSION['user'] = $user;
                echo "<p class='alert alert-success'>Партнёр <b>{$em}</b> вошёл!</p>";
                // Можно перекинуть на личный кабинет:
                // header("Location: index.php?page=dashboard");
                // exit;
            } else {
                echo "<p class='alert alert-danger'>Ошибка: неверный логин/пароль или вы не партнёр!</p>";
            }
        } else {
            echo "<p class='alert alert-danger'>Заполните все поля формы входа!</p>";
        }
    }
}

// Если нажата "logout" выше
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: regadmin.php");
    exit;
}
?>

<!-- Блок: Регистрация нового Админа -->
<h2 style="margin-bottom:20px;">Регистрация нового Админа</h2>
<form method="POST" class="form-block" style="max-width:400px;">
  <input type="hidden" name="action" value="register_admin">
  <label>Email:
    <input type="email" name="email" required>
  </label>
  <label>Пароль:
    <input type="password" name="password" required>
  </label>
  <button type="submit" class="btn-main" style="margin-top:10px;">Зарегистрировать</button>
</form>

<hr>

<!-- Блок: Вход Партнёра -->
<h2>Вход Партнёра</h2>
<form method="POST" class="form-block" style="max-width:400px;">
  <input type="hidden" name="action" value="partner_login">
  <label>Email:
    <input type="email" name="email" required>
  </label>
  <label>Пароль:
    <input type="password" name="password" required>
  </label>
  <button type="submit" class="btn-main" style="margin-top:10px;">Войти как партнёр</button>
</form>

</div>
</body>
</html>
