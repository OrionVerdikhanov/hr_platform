<?php
require_once 'includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo "<p class='alert alert-danger'>Неверный CSRF токен.</p>";
        exit;
    }
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    if ($full_name && $email && $password) {
        $ok = registerCandidate($full_name, $email, $password);
        if ($ok) {
            echo "<p class='alert alert-success'>Кандидат зарегистрирован!</p>";
            // Можно добавить автоматический вход или редирект на страницу входа
        } else {
            echo "<p class='alert alert-danger'>Ошибка: email уже используется.</p>";
        }
    } else {
        echo "<p class='alert alert-danger'>Заполните все поля!</p>";
    }
}
$csrfToken = generateCSRFToken();
$pageTitle = 'Регистрация кандидата';
include 'templates/header.php';
?>
<div class='container fade-in'>
  <h2>Регистрация кандидата</h2>
  <form method="POST" class="form-block" style="max-width:400px;">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <label>ФИО:
      <input type="text" name="full_name" required>
    </label>
    <label>Email:
      <input type="email" name="email" required>
    </label>
    <label>Пароль:
      <input type="password" name="password" required>
    </label>
    <button type="submit" class="btn-main" style="margin-top:10px;">Зарегистрироваться</button>
  </form>
</div>
<?php include 'templates/footer.php'; ?>
