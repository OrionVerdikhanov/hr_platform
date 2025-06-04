<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Referral Platform';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<nav class="topnav">
  <div class="logo">ReferralPlatform</div>
  <a href="/index.php?page=vacancies">Вакансии</a>
  <?php if (!isset($_SESSION['user'])): ?>
    <a href="/regpartner.php" class="btn-small">Регистрация партнёра</a>
    <a href="/partnerlogin.php" class="btn-small">Вход для партнёра</a>
    <a href="/adminlogin.php" class="btn-small">Вход для Админа</a>
    <a href="/candidateReg.php" class="btn-small">Регистрация кандидата</a>
    <a href="/candidateLogin.php" class="btn-small">Вход кандидата</a>
  <?php else: ?>
      <?php if ($_SESSION['user']['role'] === 'partner'): ?>
        <a href="/partnerdashboard.php" class="btn-small">Личный кабинет</a>
        <a href="/partnerlogout.php" class="btn-small btn-danger">Выйти</a>
      <?php else: ?>
        <a href="/index.php?page=dashboard" class="btn-small">Личный кабинет</a>
        <a href="/analytics.php" class="btn-small">Аналитика</a>
        <a href="/index.php?logout=1" class="btn-small btn-danger">Выйти</a>
      <?php endif; ?>
  <?php endif; ?>
</nav>

