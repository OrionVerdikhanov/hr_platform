<?php
/****************************************************
 * Файл: adminlogin.php
 * Отдельная страница для входа администратора
 ****************************************************/
require_once 'includes/bootstrap.php';

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
$pageTitle = 'Админ-вход';
include 'templates/header.php';
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
<?php include 'templates/footer.php'; ?>
