<?php
/****************************************************
 * Файл: adminlogin.php
 * Отдельная страница для входа администратора
 ****************************************************/
session_start();
require_once 'functions.php';

// Если админ уже залогинен, можно сразу перекинуть
if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    header("Location: index.php?page=dashboard");
    exit;
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'admin_login') {
        $email = trim($_POST['email'] ?? '');
        $pass  = trim($_POST['password'] ?? '');

        if ($email && $pass) {
            // Пытаемся залогиниться
            $admin = loginAdmin($email, $pass);
            if ($admin) {
                // Сохраняем в сессию
                $_SESSION['user'] = $admin;
                // Перенаправляем на админ-панель
                header("Location: index.php?page=dashboard");
                exit;
            } else {
                $errorMsg = "Ошибка: неправильный email/пароль или вы не админ!";
            }
        } else {
            $errorMsg = "Пожалуйста, заполните все поля!";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админ-вход</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<nav class="topnav">
  <div class="logo">ReferralPlatform</div>
  <a href="index.php">Главная</a>
  <?php if (!isset($_SESSION['user'])): ?>
    <a href="index.php?page=login" class="btn-small">Вход / Регистрация</a>
  <?php else: ?>
    <a href="index.php?page=dashboard" class="btn-small">Личный кабинет</a>
    <a href="index.php?logout=1" class="btn-small btn-danger">Выйти</a>
  <?php endif; ?>
</nav>

<div class="container fade-in">
  <h2>Вход для Админа</h2>
  <?php if (!empty($errorMsg)): ?>
    <p class="alert alert-danger"><?php echo $errorMsg; ?></p>
  <?php endif; ?>

  <form method="POST" class="form-block" style="max-width:400px;">
    <input type="hidden" name="action" value="admin_login">
    <label>Email:
      <input type="email" name="email" required>
    </label>
    <label>Пароль:
      <input type="password" name="password" required>
    </label>
    <button type="submit" class="btn-main" style="margin-top:10px;">Войти как админ</button>
  </form>
</div>

</body>
</html>
