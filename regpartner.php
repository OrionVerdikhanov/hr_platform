<?php
session_start();
require_once 'functions.php';

// Генерируем CSRF-токен для формы
$csrfToken = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF-токена
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo "<p class='alert alert-danger'>Ошибка: неверный CSRF токен!</p>";
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'partner_register') {
        $name     = trim($_POST['partner_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if ($name && $email && $password) {
            $ok = registerPartner($name, $email, $password);
            if ($ok) {
                echo "<p class='alert alert-success'>Партнёр <b>" . htmlspecialchars($name) . "</b> зарегистрирован!</p>";
                // Для автоматического перехода в личный кабинет можно раскомментировать строки ниже:
                // header("Location: index.php?page=dashboard");
                // exit;
            } else {
                echo "<p class='alert alert-danger'>Ошибка: Возможно, email уже существует.</p>";
            }
        } else {
            echo "<p class='alert alert-danger'>Заполните все поля!</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Регистрация партнёра</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<nav class="topnav">
  <div class="logo">ReferralPlatform</div>
  <a href="index.php">Главная</a>
  <!-- Если пользователь не авторизован, показываем ссылку для входа партнёра -->
  <?php if (!isset($_SESSION['user'])): ?>
    <a href="partnerlogin.php" class="btn-small">Вход для партнёра</a>
  <?php else: ?>
    <a href="index.php?page=dashboard">Личный кабинет</a>
    <a href="index.php?logout=1" class="btn-small btn-danger">Выйти</a>
  <?php endif; ?>
</nav>

<div class="container fade-in">
  <h2>Регистрация партнёра</h2>
  <form method="POST" class="form-block" style="max-width: 400px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="action" value="partner_register">
    
    <label>Название / Имя партнёра:
      <input type="text" name="partner_name" required>
    </label>
    <label>Email:
      <input type="email" name="email" required>
    </label>
    <label>Пароль:
      <input type="password" name="password" required>
    </label>
    <button type="submit" class="btn-main" style="margin-top: 10px;">Зарегистрироваться</button>
  </form>
</div>

</body>
</html>
