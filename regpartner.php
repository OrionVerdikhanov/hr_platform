<?php
require_once 'includes/bootstrap.php';

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
$pageTitle = 'Регистрация партнёра';
include 'templates/header.php';
?>
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
<?php include 'templates/footer.php'; ?>
