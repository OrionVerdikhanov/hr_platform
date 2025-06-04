<?php
require_once 'includes/bootstrap.php';

// Если партнёр уже авторизован, перенаправляем в Личный кабинет
if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'partner') {
    header("Location: partnerdashboard.php");
    exit;
}

$csrfToken = generateCSRFToken();
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF-токена
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errorMsg = "Ошибка: неверный CSRF токен!";
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'partner_login') {
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $user = loginUser($email, $password);
            if ($user && $user['role'] === 'partner') {
                $_SESSION['user'] = $user;
                header("Location: partnerdashboard.php");
                exit;
            } else {
                $errorMsg = "Ошибка: неверный email/пароль для партнёра.";
            }
        }
    }
}
?>
$pageTitle = 'Вход партнёра';
include 'templates/header.php';
<div class="container fade-in">
    <h2>Вход партнёра</h2>
    <?php if (!empty($errorMsg)): ?>
      <p class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></p>
    <?php endif; ?>
    <form method="POST" class="form-block" style="max-width:400px;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="action" value="partner_login">
      <label>Email:
        <input type="email" name="email" required>
      </label>
      <label>Пароль:
        <input type="password" name="password" required>
      </label>
      <button type="submit" class="btn-main" style="margin-top:10px;">Войти</button>
    </form>
  </div>
<?php include 'templates/footer.php'; ?>
