<?php
require_once 'includes/bootstrap.php';

if (isset($_GET['ref'])) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM partners WHERE referral_code = :rc");
    $stmt->execute([':rc' => $_GET['ref']]);
    if ($partner = $stmt->fetch()) {
        $_SESSION['ref_partner_id'] = $partner['id'];
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$page = $_GET['page'] ?? 'vacancies';

if ($page === 'partner_login') {
    header("Location: partnerlogin.php");
    exit;
}

if ($page === 'partner_logout') {
    header("Location: partnerlogout.php");
    exit;
}

if ($page === 'dashboard' && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'partner') {
    header("Location: partnerdashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo "<p class='alert alert-danger'>Ошибка: неверный CSRF токен!</p>";
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create_vacancy') {
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
            $name         = trim($_POST['name'] ?? '');
            $salary       = (int)($_POST['salary'] ?? 0);
            $conditions   = trim($_POST['conditions'] ?? '');
            $requirements = trim($_POST['requirements'] ?? '');
            $additional   = trim($_POST['additional'] ?? '');
            $photo        = $_FILES['photo'] ?? null;
            $additionalArr = $additional ? array_map('trim', explode(',', $additional)) : [];
            if ($name) {
                createVacancy($name, $salary, $conditions, $requirements, $additionalArr, $photo);
                echo "<p class='alert alert-success'>Вакансия «" . htmlspecialchars($name) . "» создана!</p>";
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_vacancy') {
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
            $vac_id = (int)($_POST['vac_id'] ?? 0);
            if ($vac_id) {
                deleteVacancy($vac_id);
                echo "<p class='alert alert-success'>Вакансия #$vac_id удалена.</p>";
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_vacancy') {
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
            $vac_id       = (int)($_POST['vac_id'] ?? 0);
            $name         = trim($_POST['name'] ?? '');
            $salary       = (int)($_POST['salary'] ?? 0);
            $conditions   = trim($_POST['conditions'] ?? '');
            $requirements = trim($_POST['requirements'] ?? '');
            $additional   = trim($_POST['additional'] ?? '');
            $photo        = $_FILES['photo'] ?? null;
            $additionalArr = $additional ? array_map('trim', explode(',', $additional)) : [];
            if ($vac_id && $name) {
                updateVacancy($vac_id, $name, $salary, $conditions, $requirements, $additionalArr, $photo);
                echo "<p class='alert alert-success'>Вакансия #$vac_id обновлена!</p>";
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'apply_vacancy') {
        $vac_id     = (int)($_POST['vac_id'] ?? 0);
        $fio        = trim($_POST['fio'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $experience = trim($_POST['experience'] ?? '');
        $krs_doc    = isset($_POST['krs_doc']) ? 1 : 0;
        $resumePath = null;
        if (!empty($_FILES['resume']['name'])) {
            $resumePath = handleResumeUpload($_FILES['resume']);
        }
        $additionalAnswers = $_POST['adds'] ?? [];
        $additionalJson    = json_encode($additionalAnswers, JSON_UNESCAPED_UNICODE);

        $partner_id = (isset($_SESSION['ref_partner_id']) && $_SESSION['ref_partner_id'] > 0) ? $_SESSION['ref_partner_id'] : null;

        if ($fio && $vac_id) {
            $pdo = getDBConnection();
            $sql = "INSERT INTO leads (partner_id, vacancy_id, fio, phone, email, experience, krs_doc, resume_path, additional_answers)
                    VALUES (:pid, :vid, :fio, :phone, :email, :exp, :krs, :res, :adds)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':pid'  => $partner_id,
                ':vid'  => $vac_id,
                ':fio'  => $fio,
                ':phone' => $phone,
                ':email' => $email,
                ':exp'  => $experience,
                ':krs'  => $krs_doc,
                ':res'  => $resumePath,
                ':adds' => $additionalJson
            ]);
            echo "<p class='alert alert-success'>Отклик отправлен!</p>";
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create_withdraw') {
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'partner') {
            $partner = getPartnerByEmail($_SESSION['user']['email']);
            $amount  = (float)($_POST['amount'] ?? 0);
            if ($amount > 0) {
                $ok = createWithdrawRequest($partner['id'], $amount);
                if ($ok) {
                    echo "<p class='alert alert-success'>Заявка на вывод создана</p>";
                } else {
                    echo "<p class='alert alert-danger'>Недостаточно средств</p>";
                }
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_lead_status') {
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
            $lead_id    = (int)($_POST['lead_id'] ?? 0);
            $new_status = trim($_POST['new_status'] ?? '');
            if ($lead_id && $new_status) {
                updateLeadStatus($lead_id, $new_status);
                echo "<p class='alert alert-success'>Статус лида #$lead_id обновлён на " . htmlspecialchars($new_status) . "!</p>";
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'add_ten_days') {
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
            $lead_id = (int)($_POST['lead_id'] ?? 0);
            if ($lead_id) {
                for ($i = 0; $i < 10; $i++) {
                    incDaysWorked($lead_id);
                }
                echo "<p class='alert alert-success'>Добавлено +10 дней для лида #$lead_id</p>";
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'archive_lead') {
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
            $lead_id = (int)($_POST['lead_id'] ?? 0);
            if ($lead_id) {
                archiveLead($lead_id);
                echo "<p class='alert alert-success'>Лид #$lead_id заархивирован!</p>";
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_lead_complete') {
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
            $lead_id = (int)($_POST['lead_id'] ?? 0);
            if ($lead_id) {
                deleteLeadComplete($lead_id);
                echo "<p class='alert alert-danger'>Лид #$lead_id удалён полностью!</p>";
            }
        }
    }

    if (isset($_POST['chat_action']) && $_POST['chat_action'] === 'send_message') {
        $application_id = (int)($_POST['application_id'] ?? 0);
        $chat_message   = trim($_POST['message'] ?? '');
        if ($application_id && $chat_message) {
            $sender = (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'candidate') ? 'candidate' : 'admin';
            sendChatMessage($application_id, $sender, $chat_message);
            echo "<p class='alert alert-success'>Сообщение отправлено!</p>";
        } else {
            echo "<p class='alert alert-danger'>Пожалуйста, введите сообщение.</p>";
        }
    }
}

if (($page === 'vacancies' || $page === 'vacancy_details') && isset($_SESSION['ref_partner_id'])) {
    addClick($_SESSION['ref_partner_id']);
}

$csrfToken = generateCSRFToken();
$pageTitle = 'Referral Platform';
include 'templates/header.php';
?>
<div class='container fade-in'>
<?php
switch ($page) {

    case 'vacancies':
        $vacancies = getAllVacanciesDB();
        echo "<h1>Список вакансий</h1>";
        if ($vacancies) {
            echo "<div class='vacancies-list' style='display: flex; flex-wrap: wrap; gap: 20px;'>";
            foreach ($vacancies as $vac) {
                echo "<div class='vacancy-card' style='width:280px; background:#eaffea; padding:16px; border-radius:8px;'>";
                if (!empty($vac['photo_url'])) {
                    echo "<img src='" . htmlspecialchars($vac['photo_url'] ?? '') . "' style='width:100%; border-radius:8px;' alt='Фото вакансии'>";
                }
                echo "<h3 style='margin-top:10px;'>" . htmlspecialchars($vac['name'] ?? '') . "</h3>";
                echo "<p style='margin:5px 0;'>З/п: <b>" . htmlspecialchars($vac['salary'] ?? '') . " руб.</b></p>";
                echo "<a href='index.php?page=vacancy_details&id=" . (int)($vac['id'] ?? 0) . "' class='btn-small'>Подробнее</a>";
                echo "</div>";
            }
            echo "</div>";
        } else {
            echo "<p>Пока нет вакансий.</p>";
        }
        break;
        
    case 'vacancy_details':
        $id = (int)($_GET['id'] ?? 0);
        $vac = getVacancyById($id);
        if (!$vac) {
            echo "<p class='alert alert-danger'>Вакансия не найдена!</p>";
            break;
        }
        echo "<h2>" . htmlspecialchars($vac['name'] ?? '') . "</h2>";
        if (!empty($vac['photo_url'])) {
            echo "<img src='" . htmlspecialchars($vac['photo_url'] ?? '') . "' style='max-width:300px; float:right; margin:0 0 10px 10px; border-radius:8px;' alt='Фото вакансии'>";
        }
        echo "<p><strong>З/п:</strong> " . htmlspecialchars($vac['salary'] ?? '') . " руб.</p>";
        echo "<p><strong>Условия:</strong><br>" . nl2br(htmlspecialchars($vac['conditions'] ?? '')) . "</p>";
        echo "<p><strong>Требования:</strong><br>" . nl2br(htmlspecialchars($vac['requirements'] ?? '')) . "</p>";
        $addReq = json_decode($vac['additional_requirements'] ?? '', true);
        if ($addReq && is_array($addReq)) {
            echo "<p><strong>Дополнительные требования:</strong></p>";
            echo "<div>";
            foreach ($addReq as $req) {
                echo "<label style='display:block;'><input type='checkbox' disabled> " . htmlspecialchars($req ?? '') . "</label>";
            }
            echo "</div>";
        }
        ?>
        <hr>
        <h3>Оставить отклик</h3>
        <form method="POST" enctype="multipart/form-data" class="form-block" style="max-width:500px;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="action" value="apply_vacancy">
          <input type="hidden" name="vac_id" value="<?= (int)($vac['id'] ?? 0) ?>">
          <label>ФИО:
            <input type="text" name="fio" required>
          </label>
          <label>Телефон:
            <input type="text" name="phone" required pattern="[\d\+\-\(\) ]{7,}">
          </label>
          <label>Email:
            <input type="email" name="email" required>
          </label>
          <label>Опыт работы:
            <select name="experience">
              <option value="Нет">Нет</option>
              <option value="1-3 года">1-3 года</option>
              <option value="Более 3 лет">Более 3 лет</option>
            </select>
          </label>
          <label>
            <input type="checkbox" name="krs_doc" value="1">
            Имеется удостоверение КРС
          </label>
          <?php
          if ($addReq && is_array($addReq)) {
              echo "<p><strong>Дополнительные требования (отметьте, что у вас есть):</strong></p>";
              foreach ($addReq as $i => $req) {
                  echo "<label style='display:block;'><input type='checkbox' name='adds[{$i}]' value='1'> " . htmlspecialchars($req ?? '') . "</label>";
              }
          }
          ?>
          <label>Резюме (doc, docx, pdf):
            <input type="file" name="resume" accept=".doc,.docx,.pdf">
          </label>
          <button type="submit" class="btn-main" style="margin-top:10px;">Отправить отклик</button>
        </form>
        <?php
        break;
        
    case 'adminlogin':
        header("Location: adminlogin.php");
        exit;
        
    case 'dashboard':
        if (!isset($_SESSION['user'])) {
            echo "<p class='alert alert-danger'>Вы не авторизованы!</p>";
            break;
        }
        $user = $_SESSION['user'];
        if ($user['role'] === 'admin') {
            $subpage = $_GET['subpage'] ?? 'invited';
            echo "<h2>Админ-панель</h2>";
            echo "<div class='tabs-menu'>";
            echo "<a href='index.php?page=dashboard&subpage=invited' class='" . ($subpage === 'invited' ? 'active' : '') . "'>Приглашённые</a>";
            echo "<a href='index.php?page=dashboard&subpage=manage_vacancies' class='" . ($subpage === 'manage_vacancies' ? 'active' : '') . "'>Управление вакансиями</a>";
            echo "</div>";
            echo "<div class='tab-content fade-in'>";
            if ($subpage === 'invited') {
                $leads = getAllLeads();
                echo "<h3>Приглашённые (лиды)</h3>";
                echo "<table class='table-simple'>";
                echo "<tr>
                        <th>ID</th>
                        <th>Партнёр</th>
                        <th>ФИО</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Статус</th>
                        <th>Дни</th>
                        <th>Действия</th>
                      </tr>";
                foreach ($leads as $lead) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($lead['id'] ?? '') . "</td>";
                    echo "<td>" . htmlspecialchars($lead['partner_name'] ?? '') . "</td>";
                    echo "<td>" . htmlspecialchars($lead['fio'] ?? ($lead['name'] ?? '')) . "</td>";
                    echo "<td>" . htmlspecialchars($lead['phone'] ?? '') . "</td>";
                    echo "<td>" . htmlspecialchars($lead['email'] ?? '') . "</td>";
                    echo "<td>";
                    ?>
                    <form method="POST" style="display:inline-block;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                      <input type="hidden" name="action" value="update_lead_status">
                      <input type="hidden" name="lead_id" value="<?= htmlspecialchars($lead['id'] ?? '') ?>">
                      <select name="new_status">
                        <?php
                        $statuses = [
                            'Новый отклик',
                            'Приглашён на собеседование',
                            'Отказ соискателя',
                            'Не прошёл собеседование',
                            'Не соответствует требованиям',
                            'Трудоустроен'
                        ];
                        $currentStatus = $lead['status'] ?? 'Новый отклик';
                        foreach ($statuses as $st) {
                            $selected = ($st === $currentStatus) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($st) . "' $selected>" . htmlspecialchars($st) . "</option>";
                        }
                        ?>
                      </select>
                      <button type="submit" class="btn-small">Сменить</button>
                    </form>
                    <?php
                    echo "<br><small>Текущий: " . htmlspecialchars($lead['status'] ?? '') . "</small>";
                    echo "</td>";
                    echo "<td>" . htmlspecialchars($lead['days_worked'] ?? '') . "</td>";
                    echo "<td>";
                    ?>
                    <form method="POST" style="display:inline-block;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                      <input type="hidden" name="action" value="add_ten_days">
                      <input type="hidden" name="lead_id" value="<?= htmlspecialchars($lead['id'] ?? '') ?>">
                      <button type="submit" class="btn-small">+10 дней</button>
                    </form>
                    <form method="POST" style="display:inline-block;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                      <input type="hidden" name="action" value="archive_lead">
                      <input type="hidden" name="lead_id" value="<?= htmlspecialchars($lead['id'] ?? '') ?>">
                      <button type="submit" class="btn-small">Архив</button>
                    </form>
                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Удалить лид #<?= htmlspecialchars($lead['id'] ?? '') ?> полностью?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                      <input type="hidden" name="action" value="delete_lead_complete">
                      <input type="hidden" name="lead_id" value="<?= htmlspecialchars($lead['id'] ?? '') ?>">
                      <button type="submit" class="btn-small btn-danger">Удалить</button>
                    </form>
                    <?php
                    echo "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } elseif ($subpage === 'manage_vacancies') {
                echo "<h3>Список вакансий</h3>";
                $vacancies = getAllVacanciesDB();
                if ($vacancies) {
                    echo "<table class='table-simple'>";
                    echo "<tr><th>ID</th><th>Название</th><th>З/п</th><th>Действия</th></tr>";
                    foreach ($vacancies as $vac) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($vac['id'] ?? '') . "</td>";
                        echo "<td>" . htmlspecialchars($vac['name'] ?? '') . "</td>";
                        echo "<td>" . htmlspecialchars($vac['salary'] ?? '') . "</td>";
                        echo "<td>";
                        ?>
                        <form method="POST" style="display:inline-block">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                          <input type="hidden" name="action" value="delete_vacancy">
                          <input type="hidden" name="vac_id" value="<?= htmlspecialchars($vac['id'] ?? '') ?>">
                          <button type="submit" class="btn-small btn-danger" onclick="return confirm('Удалить вакансию #<?= htmlspecialchars($vac['id'] ?? '') ?>?');">Удалить</button>
                        </form>
                        <button onclick="document.getElementById('editForm<?= htmlspecialchars($vac['id'] ?? '') ?>').style.display='block'" class="btn-small">Редактировать</button>
                        <div id="editForm<?= htmlspecialchars($vac['id'] ?? '') ?>" style="display:none; margin-top:10px;">
                          <form method="POST" enctype="multipart/form-data" class="form-block">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="update_vacancy">
                            <input type="hidden" name="vac_id" value="<?= htmlspecialchars($vac['id'] ?? '') ?>">
                            <label>Название:
                              <input type="text" name="name" value="<?= htmlspecialchars($vac['name'] ?? '') ?>" required>
                            </label>
                            <label>З/п:
                              <input type="number" name="salary" value="<?= htmlspecialchars($vac['salary'] ?? '') ?>" required>
                            </label>
                            <label>Условия:
                              <textarea name="conditions"><?= htmlspecialchars($vac['conditions'] ?? '') ?></textarea>
                            </label>
                            <label>Требования:
                              <textarea name="requirements"><?= htmlspecialchars($vac['requirements'] ?? '') ?></textarea>
                            </label>
                            <label>Доп. требования (через запятую):
                              <?php 
                              $adArr = json_decode($vac['additional_requirements'] ?? '', true) ?: [];
                              $adStr = implode(', ', $adArr);
                              ?>
                              <textarea name="additional"><?= htmlspecialchars($adStr ?? '') ?></textarea>
                            </label>
                            <label>Фото (jpg, png, gif):
                              <input type="file" name="photo" accept="image/*">
                            </label>
                            <button type="submit" class="btn-main" style="margin-top:8px;">Сохранить</button>
                          </form>
                        </div>
                        <?php
                        echo "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>Вакансий нет.</p>";
                }
                ?>
                <hr>
                <h4>Создать вакансию</h4>
                <form method="POST" enctype="multipart/form-data" class="form-block">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                  <input type="hidden" name="action" value="create_vacancy">
                  <label>Название вакансии:
                    <input type="text" name="name" required>
                  </label>
                  <label>З/п:
                    <input type="number" name="salary" required>
                  </label>
                  <label>Условия:
                    <textarea name="conditions"></textarea>
                  </label>
                  <label>Требования:
                    <textarea name="requirements"></textarea>
                  </label>
                  <label>Доп. требования (через запятую):
                    <textarea name="additional"></textarea>
                  </label>
                  <label>Фото (jpg, png, gif):
                    <input type="file" name="photo" accept="image/*">
                  </label>
                  <button type="submit" class="btn-main">Создать</button>
                </form>
                <?php
            } elseif ($subpage === 'stats') {
                $clicks = getClicksCount($partner['id'] ?? 0);
                $myLeads = getLeadsByPartner($partner['id'] ?? 0);
                $totalLeads = count($myLeads);
                $acceptedCount = 0;
                $employedCount = 0;
                $earned = 0;
                foreach ($myLeads as $ld) {
                    if (($ld['status'] ?? '') === 'accepted') {
                        $acceptedCount++;
                    }
                    if (($ld['status'] ?? '') === 'employed') {
                        $employedCount++;
                    }
                    if (!empty($ld['is_paid'])) {
                        $earned += 5000;
                    }
                }
                echo "<h3>Статистика</h3>";
                echo "<ul>";
                echo "<li>Кликов по ссылке: <b>" . htmlspecialchars($clicks ?? 0) . "</b></li>";
                echo "<li>Всего заявок: <b>" . htmlspecialchars($totalLeads) . "</b></li>";
                echo "<li>Принято (accepted): <b>" . htmlspecialchars($acceptedCount) . "</b></li>";
                echo "<li>Трудоустроено (employed): <b>" . htmlspecialchars($employedCount) . "</b></li>";
                echo "<li>Выплачено: <b>" . htmlspecialchars($earned) . " руб.</b></li>";
                echo "</ul>";
            } elseif ($subpage === 'profile') {
                echo "<h3>Профиль</h3>";
                ?>
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
                  <p>Ваша реферальная ссылка: <b><?= htmlspecialchars(getConfig()['app']['base_url'] . "/index.php?ref=" . ($partner['referral_code'] ?? '')) ?></b></p>
                  <p>Баланс: <b><?= htmlspecialchars($partner['wallet_balance'] ?? '') ?></b> (заморожено: <?= htmlspecialchars($partner['frozen_balance'] ?? '') ?>)</p>
                </div>
                <?php
            }
            echo "</div>";
        } elseif ($user['role'] === 'candidate') {
            echo "<h2>Личный кабинет кандидата</h2>";
            $candidateApp = getCandidateApplication($user['id']);
            if ($candidateApp) {
                echo "<h3>Информация о заявке</h3>";
                echo "<p><strong>Вакансия ID:</strong> " . htmlspecialchars($candidateApp['vacancy_id'] ?? '') . "</p>";
                echo "<p><strong>Статус заявки:</strong> " . htmlspecialchars($candidateApp['status'] ?? '') . "</p>";
                echo "<p><strong>Сопроводительное письмо:</strong><br>" . nl2br(htmlspecialchars($candidateApp['cover_letter'] ?? '')) . "</p>";
                echo "<hr><h3>Чат с администратором</h3>";
                $chatMessages = getChatMessages($candidateApp['id'] ?? 0);
                if ($chatMessages) {
                    echo "<div class='chat-window' style='border: 1px solid #ccc; padding: 1rem; max-height: 300px; overflow-y: scroll;'>";
                    foreach ($chatMessages as $msg) {
                        echo "<p><strong>" . ucfirst(htmlspecialchars($msg['sender'] ?? '')) . ":</strong> " . nl2br(htmlspecialchars($msg['message'] ?? '')) . "<br><small>" . htmlspecialchars($msg['created_at'] ?? '') . "</small></p><hr>";
                    }
                    echo "</div>";
                } else {
                    echo "<p>Чат пока пуст.</p>";
                }
                ?>
                <form method="POST" class="form-block" style="margin-top: 1rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="chat_action" value="send_message">
                    <input type="hidden" name="application_id" value="<?= htmlspecialchars($candidateApp['id'] ?? '') ?>">
                    <label>Новое сообщение:
                        <textarea name="message" required></textarea>
                    </label>
                    <button type="submit" class="btn-main">Отправить</button>
                </form>
                <?php
            } else {
                echo "<p>Заявка не найдена. Пожалуйста, оформите заявку на вакансию.</p>";
            }
        } else {
            echo "<h2>Личный кабинет</h2>";
            echo "<p>Функционал для данной роли не реализован.</p>";
        }
        break;
        
    default:
        header("Location: index.php?page=vacancies");
        exit;
}
?>
<?php include 'templates/footer.php'; ?>
