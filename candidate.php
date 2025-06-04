<?php
session_start();
require_once 'functions.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF-токена
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = "<p class='alert alert-danger'>Ошибка: неверный CSRF токен!</p>";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $cover_letter = trim($_POST['cover_letter'] ?? '');
        $vacancy_id = (int)($_POST['vacancy_id'] ?? 0);
        
        if ($full_name && $email && $cover_letter && $vacancy_id) {
            $result = createCandidateApplication($full_name, $email, $cover_letter, $vacancy_id);
            if ($result) {
                $newCandidate = isset($result['candidate']['generated_password']) && $result['candidate']['generated_password'];
                $message = "<p class='alert alert-success'>Ваша заявка отправлена. " .
                           ($newCandidate ? "Ваши учётные данные отправлены на email." : "Вы уже зарегистрированы – используйте свои учётные данные.") .
                           "</p>";
            } else {
                $message = "<p class='alert alert-danger'>Ошибка при отправке заявки.</p>";
            }
        } else {
            $message = "<p class='alert alert-danger'>Пожалуйста, заполните все поля.</p>";
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Подача заявки кандидата</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="topnav">
      <div class="logo">ReferralPlatform</div>
      <a href="index.php?page=vacancies">Вакансии</a>
      <a href="candidate.php">Подача заявки</a>
    </nav>
    <div class="container">
        <h1>Подать заявку кандидата</h1>
        <?php echo $message; ?>
        <form method="POST" class="form-block">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <label>ФИО:
                <input type="text" name="full_name" required>
            </label>
            <label>Email:
                <input type="email" name="email" required>
            </label>
            <label>Номер вакансии:
                <input type="number" name="vacancy_id" required>
            </label>
            <label>Сопроводительное письмо:
                <textarea name="cover_letter" required></textarea>
            </label>
            <button type="submit" class="btn-main">Отправить заявку</button>
        </form>
    </div>
</body>
</html>
