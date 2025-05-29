<?php
session_start();
require_once 'functions.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = "<p class='alert alert-danger'>Ошибка: неверный CSRF токен!</p>";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $user = loginUser($email, $password);
        if ($user && $user['role'] === 'candidate') {
            $_SESSION['user'] = $user;
            header("Location: candidateDashboard.php");
            exit;
        } else {
            $message = "<p class='alert alert-danger'>Неверный логин или пароль.</p>";
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход кандидата</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="topnav">
      <div class="logo">ReferralPlatform</div>
      <a href="index.php?page=vacancies">Вакансии</a>
      <a href="candidateLogin.php">Вход кандидата</a>
    </nav>
    <div class="container">
        <h1>Вход в кабинет кандидата</h1>
        <?php echo $message; ?>
        <form method="POST" class="form-block" style="max-width:400px;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <label>Email:
                <input type="email" name="email" required>
            </label>
            <label>Пароль:
                <input type="password" name="password" required>
            </label>
            <button type="submit" class="btn-main">Войти</button>
        </form>
    </div>
</body>
</html>
