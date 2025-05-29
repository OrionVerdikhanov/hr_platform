<?php
session_start();
require_once 'functions.php';

// Если пользователь не авторизован как партнёр – перенаправляем на страницу входа
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'partner') {
    header("Location: partnerlogin.php");
    exit;
}

$csrfToken = generateCSRFToken();

// Получаем данные партнёра
$partner = getPartnerByEmail($_SESSION['user']['email']);
if (!$partner) {
    // Если данные партнёра не найдены, разлогиниваем и перенаправляем
    session_destroy();
    header("Location: partnerlogin.php");
    exit;
}

// Получаем данные для дашборда партнёра
$dashboardData = getPartnerDashboardData($partner['id']);

// Обработка POST-запросов в кабинете партнёра
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo "<p class='alert alert-danger'>Ошибка: неверный CSRF токен!</p>";
        exit;
    }
    
    // Обновление профиля партнёра
    if (isset($_POST['action']) && $_POST['action'] === 'update_partner_profile') {
        $new_name = trim($_POST['new_name'] ?? '');
        $new_pass = trim($_POST['new_pass'] ?? '');
        $payment_info = trim($_POST['payment_info'] ?? '');
        updatePartnerProfile($partner['id'], $new_name, $new_pass, $payment_info);
        echo "<p class='alert alert-success'>Профиль обновлён!</p>";
        // Обновляем данные партнёра и дашборда после изменения
        $partner = getPartnerByEmail($_SESSION['user']['email']);
        $dashboardData = getPartnerDashboardData($partner['id']);
    }
    
    // Создание заявки на вывод средств
    if (isset($_POST['action']) && $_POST['action'] === 'create_withdraw') {
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount > 0) {
            $ok = createWithdrawRequest($partner['id'], $amount);
            if ($ok) {
                echo "<p class='alert alert-success'>Заявка на вывод создана</p>";
            } else {
                echo "<p class='alert alert-danger'>Недостаточно средств</p>";
            }
            // Обновляем данные дашборда
            $dashboardData = getPartnerDashboardData($partner['id']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Личный кабинет партнёра</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Стили для вкладок */
    .tabs-menu a {
      margin-right: 10px;
      padding: 8px 12px;
      border: 1px solid #ccc;
      border-radius: 4px;
      text-decoration: none;
      color: inherit;
    }
    .tabs-menu a.active {
      background-color: #FFA500;
      color: #000;
    }
    .tab-content {
      margin-top: 20px;
    }
  </style>
</head>
<body>
<nav class="topnav">
  <div class="logo">ReferralPlatform</div>
  <a href="index.php?page=vacancies">Вакансии</a>
  <a href="partnerlogout.php" class="btn-small btn-danger">Выйти</a>
</nav>
<div class="container fade-in">
  <h2>Личный кабинет партнёра (<?= htmlspecialchars($partner['name'] ?? '') ?>)</h2>
  <div class="tabs-menu">
    <a href="?page=dashboard&tab=withdraws" class="<?= (!isset($_GET['tab']) || $_GET['tab'] === 'withdraws') ? 'active' : '' ?>">Мои заявки на вывод</a>
    <a href="?page=dashboard&tab=invited" class="<?= (isset($_GET['tab']) && $_GET['tab'] === 'invited') ? 'active' : '' ?>">Мои приглашённые</a>
    <a href="?page=dashboard&tab=stats" class="<?= (isset($_GET['tab']) && $_GET['tab'] === 'stats') ? 'active' : '' ?>">Статистика</a>
    <a href="?page=dashboard&tab=profile" class="<?= (isset($_GET['tab']) && $_GET['tab'] === 'profile') ? 'active' : '' ?>">Профиль</a>
  </div>
  <div class="tab-content">
    <?php
    $tab = $_GET['tab'] ?? 'withdraws';
    if ($tab === 'withdraws'):
    ?>
      <h3>Мои заявки на вывод</h3>
      <form method="POST" class="form-inline">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="create_withdraw">
        <label>Сумма вывода:
          <input type="number" step="0.01" name="amount" required>
        </label>
        <button type="submit" class="btn-main">Создать заявку</button>
      </form>
      <table class="table-simple" style="margin-top:15px;">
        <tr>
          <th>ID</th>
          <th>Сумма</th>
          <th>Статус</th>
          <th>Дата</th>
        </tr>
        <?php foreach ($dashboardData['withdraw_requests'] as $req): ?>
          <tr>
            <td><?= htmlspecialchars($req['id'] ?? '') ?></td>
            <td><?= htmlspecialchars($req['amount'] ?? '') ?></td>
            <td><?= htmlspecialchars($req['status'] ?? '') ?></td>
            <td><?= htmlspecialchars($req['created_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php
    elseif ($tab === 'invited'):
    ?>
      <h3>Мои приглашённые</h3>
      <table class="table-simple">
        <tr>
          <th>ID</th>
          <th>ФИО</th>
          <th>Статус</th>
          <th>Дни</th>
        </tr>
        <?php
        foreach ($dashboardData['leads'] as $lead):
        ?>
          <tr>
            <td><?= htmlspecialchars($lead['id'] ?? '') ?></td>
            <td><?= htmlspecialchars($lead['fio'] ?? '') ?></td>
            <td><?= htmlspecialchars($lead['status'] ?? '') ?></td>
            <td><?= htmlspecialchars($lead['days_worked'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <p>Ваша реферальная ссылка: <b><?= htmlspecialchars($dashboardData['referral_link'] ?? '') ?></b></p>
    <?php
    elseif ($tab === 'stats'):
        // Подсчитаем дополнительные показатели
        $totalLeads = count($dashboardData['leads']);
        $accepted = 0;
        $employed = 0;
        $earned = 0;
        foreach ($dashboardData['leads'] as $lead) {
            if (($lead['status'] ?? '') === 'accepted') $accepted++;
            if (($lead['status'] ?? '') === 'employed') $employed++;
            if (!empty($lead['is_paid'])) $earned += 5000;
        }
    ?>
      <h3>Статистика</h3>
      <ul>
        <li>Кликов по ссылке: <b><?= htmlspecialchars($dashboardData['clicks'] ?? 0) ?></b></li>
        <li>Всего заявок: <b><?= htmlspecialchars($totalLeads) ?></b></li>
        <li>Принято (accepted): <b><?= htmlspecialchars($accepted) ?></b></li>
        <li>Трудоустроено (employed): <b><?= htmlspecialchars($employed) ?></b></li>
        <li>Выплачено: <b><?= htmlspecialchars($earned) ?> руб.</b></li>
      </ul>
    <?php
    elseif ($tab === 'profile'):
    ?>
      <h3>Профиль</h3>
      <form method="POST" class="form-block" style="max-width:400px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="update_partner_profile">
        <label>Имя (Название):
          <input type="text" name="new_name" value="<?= htmlspecialchars($partner['name'] ?? '') ?>">
        </label>
        <label>Новый пароль (если менять):
          <input type="password" name="new_pass">
        </label>
        <label>Реквизиты (карта/тел.):
          <input type="text" name="payment_info" value="<?= htmlspecialchars($partner['payment_info'] ?? '') ?>">
        </label>
        <button type="submit" class="btn-main">Сохранить</button>
      </form>
      <div style="margin-top:10px;">
        <p>Ваша реферальная ссылка: <b><?= htmlspecialchars($dashboardData['referral_link'] ?? '') ?></b></p>
        <p>Баланс: <b><?= htmlspecialchars($partner['wallet_balance'] ?? '') ?></b> (заморожено: <?= htmlspecialchars($partner['frozen_balance'] ?? '') ?>)</p>
      </div>
    <?php
    endif;
    ?>
  </div>
</div>
</body>
</html>
