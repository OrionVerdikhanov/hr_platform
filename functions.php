<?php
/****************************************************
 * Файл: functions.php – улучшенная версия
 *
 * Этот файл содержит функции для работы с базой данных,
 * регистрации и логина пользователей (админов, партнёров,
 * кандидатов), работы с вакансиями, лидами, кликами,
 * выводом средств, а также для работы с заявками кандидатов,
 * чатом и дополнительным функционалом для личного кабинета партнёра.
 *
 * Основные улучшения:
 * - Использование отдельного файла конфигурации (config.php или local.php)
 * - Улучшенная обработка ошибок и логирование
 * - Валидация входных данных и улучшенная обработка загрузки файлов
 ****************************************************/

/** 
 * Получить настройки приложения из файла конфигурации.
 * Если существует файл local.php, он используется, иначе – config.php.
 */
function getConfig() {
    $custom = getenv('CONFIG_PATH');
    if ($custom && file_exists($custom)) {
        return require $custom;
    }
    if (file_exists(__DIR__ . '/local.php')) {
        return require __DIR__ . '/local.php';
    }
    return require __DIR__ . '/config.php';
}

/** 
 * Получить подключение к базе данных.
 * Используется параметр из файла конфигурации. Режим работы – PDO::ERRMODE_EXCEPTION.
 */
function getDBConnection() {
    static $pdo;
    if (!$pdo) {
        $config = getConfig();
        $db = $config['db'];
        if (isset($db['dsn'])) {
            $dsn = $db['dsn'];
            $username = $db['username'] ?? null;
            $password = $db['password'] ?? null;
        } else {
            $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}";
            $username = $db['username'];
            $password = $db['password'];
        }
        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            // Логируем техническую ошибку
            error_log("DB Connection Error: " . $e->getMessage());
            // Показываем пользователю понятное сообщение
            die("Нет подключения к базе данных.");
        }
    }
    return $pdo;
}


/****************************************************
 * Функции для работы с пользователями
 ****************************************************/

/**
 * Регистрация админа.
 * Проверяет корректность email и отсутствие дубликата.
 */
function registerAdmin($email, $password) {
    $pdo = getDBConnection();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $check = $pdo->prepare("SELECT id FROM users WHERE email = :em");
    $check->execute([':em' => $email]);
    if ($check->fetch()) return false;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (email, password, role) VALUES (:em, :pw, 'admin')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':em' => $email, ':pw' => $hash]);
    return true;
}

/**
 * Генерация CSRF-токена.
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Проверка CSRF-токена.
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Логин админа.
 */
function loginAdmin($email, $password) {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM users WHERE email = :em AND role = 'admin'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':em' => $email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
}

/**
 * Регистрация партнёра.
 * Создаёт запись как в таблице partners, так и в общей таблице users.
 */
function registerPartner($name, $email, $password) {
    $pdo = getDBConnection();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $ch = $pdo->prepare("SELECT id FROM users WHERE email = :em");
    $ch->execute([':em' => $email]);
    if ($ch->fetch()) return false;

    // Создаем уникальный реферальный код (первые 3 символа имени + случайное число)
    $refCode = substr($name, 0, 3) . rand(1000, 9999);
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Вставка в таблицу partners
    $sql1 = "INSERT INTO partners (name, email, password, referral_code)
             VALUES (:nm, :em, :pw, :rc)";
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute([
        ':nm' => $name,
        ':em' => $email,
        ':pw' => $hash,
        ':rc' => $refCode
    ]);

    // Вставка в общую таблицу users
    $sql2 = "INSERT INTO users (email, password, role)
             VALUES (:em, :pw, 'partner')";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([
        ':em' => $email,
        ':pw' => $hash
    ]);

    return true;
}

/**
 * Регистрация кандидата.
 * Создает записи в таблице users и, при необходимости, в таблице candidates.
 * Кандидат регистрируется самостоятельно, вводя свои данные.
 */
function registerCandidate($full_name, $email, $password) {
    $pdo = getDBConnection();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $ch = $pdo->prepare("SELECT id FROM users WHERE email = :em");
    $ch->execute([':em' => $email]);
    if ($ch->fetch()) return false;
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Вставка в таблицу users
    $sql1 = "INSERT INTO users (email, password, role) VALUES (:em, :pw, 'candidate')";
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute([':em' => $email, ':pw' => $hash]);
    $user_id = $pdo->lastInsertId();

    // Вставка в таблицу candidates
    $sql2 = "INSERT INTO candidates (user_id, full_name)
             VALUES (:uid, :fn)";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([
        ':uid' => $user_id,
        ':fn'  => $full_name
    ]);

    return true;
}

/**
 * Универсальный логин для партнёра, кандидата и других пользователей.
 */
function loginUser($email, $password) {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM users WHERE email = :em";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':em' => $email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
}

/** Получить партнёра по email */
function getPartnerByEmail($email) {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM partners WHERE email = :em";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':em' => $email]);
    return $stmt->fetch();
}

/****************************************************
 * Функции для работы с кликами
 ****************************************************/

/** 
 * Добавить клик.
 * Сохраняется partner_id, IP-адрес и user agent.
 */
function addClick($partner_id) {
    $pdo = getDBConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $sql = "INSERT INTO clicks (partner_id, ip, user_agent)
            VALUES (:pid, :ip, :ua)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':pid' => $partner_id,
        ':ip'  => $ip,
        ':ua'  => $ua
    ]);
}

/** Получить количество кликов для заданного партнёра */
function getClicksCount($partner_id) {
    $pdo = getDBConnection();
    $sql = "SELECT COUNT(*) as cnt FROM clicks WHERE partner_id = :pid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pid' => $partner_id]);
    $row = $stmt->fetch();
    return $row ? $row['cnt'] : 0;
}

/****************************************************
 * Функции для работы с вакансиями
 ****************************************************/

/**
 * Обработка загрузки фото вакансии (jpg, jpeg, png, gif).
 * Ограничение размера – 2 МБ.
 */
function handleVacancyPhotoUpload($file) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) {
        return null;
    }
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $origName = $file['name'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return null;
    }
    $newName = uniqid('vac_') . '.' . $ext;
    $uploadDir = __DIR__ . '/uploads/vacancy_photos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $target = $uploadDir . $newName;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return 'uploads/vacancy_photos/' . $newName;
    }
    return null;
}

/**
 * Создать вакансию.
 * Дополнительные требования передаются в виде массива, который сериализуется в JSON.
 */
function createVacancy($name, $salary, $conditions, $requirements, $additionalReqsArr = [], $photoFile = null) {
    $pdo = getDBConnection();

    // Обработка фото, если загружено
    $photoUrl = null;
    if ($photoFile && !empty($photoFile['name'])) {
        $photoUrl = handleVacancyPhotoUpload($photoFile);
    }

    $arrJson = json_encode($additionalReqsArr, JSON_UNESCAPED_UNICODE);

    $sql = "INSERT INTO vacancies (name, salary, conditions, requirements, additional_requirements, photo_url)
            VALUES (:nm, :sal, :cond, :reqs, :adds, :photo)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nm'    => $name,
        ':sal'   => $salary,
        ':cond'  => $conditions,
        ':reqs'  => $requirements,
        ':adds'  => $arrJson,
        ':photo' => $photoUrl
    ]);
}

/** 
 * Получить все вакансии.
 * Можно задать лимит и смещение (для пагинации).
 */
function getAllVacanciesDB($limit = null, $offset = null) {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM vacancies ORDER BY created_at DESC";
    if ($limit !== null) {
        $sql .= " LIMIT " . (int)$limit;
        if ($offset !== null) {
            $sql .= " OFFSET " . (int)$offset;
        }
    }
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/** Получить вакансию по её ID */
function getVacancyById($id) {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM vacancies WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

/** Удалить вакансию по ID */
function deleteVacancy($id) {
    $pdo = getDBConnection();
    $sql = "DELETE FROM vacancies WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
}

/**
 * Обновить вакансию.
 * Если загружено новое фото, оно заменяется.
 */
function updateVacancy($id, $name, $salary, $conditions, $requirements, $additionalReqsArr = [], $photoFile = null) {
    $pdo = getDBConnection();
    $arrJson = json_encode($additionalReqsArr, JSON_UNESCAPED_UNICODE);

    $photoUrl = null;
    if ($photoFile && !empty($photoFile['name'])) {
        $photoUrl = handleVacancyPhotoUpload($photoFile);
    }

    if ($photoUrl) {
        $sql = "UPDATE vacancies SET name = :nm, salary = :sal, conditions = :cond,
                requirements = :reqs, additional_requirements = :adds, photo_url = :ph
                WHERE id = :id";
        $params = [
            ':nm' => $name,
            ':sal' => $salary,
            ':cond'=> $conditions,
            ':reqs'=> $requirements,
            ':adds'=> $arrJson,
            ':ph'  => $photoUrl,
            ':id'  => $id
        ];
    } else {
        $sql = "UPDATE vacancies SET name = :nm, salary = :sal, conditions = :cond,
                requirements = :reqs, additional_requirements = :adds
                WHERE id = :id";
        $params = [
            ':nm' => $name,
            ':sal'=> $salary,
            ':cond'=> $conditions,
            ':reqs'=> $requirements,
            ':adds'=> $arrJson,
            ':id'  => $id
        ];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

/****************************************************
 * Функции для работы с лидами (откликами)
 ****************************************************/

/**
 * Создать лид (старый вариант).
 */
function createLead($partner_id, $name, $age, $experience, $self_comment, $email, $phone, $vacancy_id = 1) {
    $pdo = getDBConnection();
    $sql = "INSERT INTO leads
            (partner_id, name, age, experience, self_comment, email, phone, vacancy_id)
            VALUES (:pid, :nm, :ag, :ex, :sc, :em, :ph, :vid)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':pid' => $partner_id,
        ':nm'  => $name,
        ':ag'  => $age,
        ':ex'  => $experience,
        ':sc'  => $self_comment,
        ':em'  => $email,
        ':ph'  => $phone,
        ':vid' => $vacancy_id
    ]);
}

/**
 * Обработка загрузки резюме (форматы: doc, docx, pdf).
 * Ограничение размера – 2 МБ.
 */
function handleResumeUpload($file) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) {
        return null;
    }
    $allowed = ['doc', 'docx', 'pdf'];
    $fn = $file['name'];
    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return null;
    }
    $newName = uniqid('resume_') . '.' . $ext;
    $uploadDir = __DIR__ . '/uploads/resumes/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $target = $uploadDir . $newName;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return 'uploads/resumes/' . $newName;
    }
    return null;
}

/**
 * Создать лид (отклик) на вакансию.
 * Если в сессии нет реферального партнёра или его значение равно 0,
 * partner_id устанавливается в NULL.
 */
function createVacancyLead($vacancy_id, $fio, $experience, $krs_doc, $resumePath = null) {
    $pdo = getDBConnection();
    $partner_id = (isset($_SESSION['ref_partner_id']) && $_SESSION['ref_partner_id'] > 0) ? $_SESSION['ref_partner_id'] : null;
    $sql = "INSERT INTO leads (partner_id, vacancy_id, fio, experience, krs_doc, resume_path)
            VALUES (:pid, :vid, :fio, :exp, :krs, :res)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':pid' => $partner_id,
        ':vid' => $vacancy_id,
        ':fio' => $fio,
        ':exp' => $experience,
        ':krs' => $krs_doc,
        ':res' => $resumePath
    ]);
}

/** Получить все лиды */
function getAllLeads() {
    $pdo = getDBConnection();
    $sql = "SELECT l.*, p.name as partner_name, l.vacancy_id
            FROM leads l
            LEFT JOIN partners p ON l.partner_id = p.id
            ORDER BY l.created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/** Обновить статус лида */
function updateLeadStatus($lead_id, $new_status) {
    $pdo = getDBConnection();
    $sql = "UPDATE leads SET status = :st WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':st' => $new_status, ':id' => $lead_id]);
}

/** Принять лид (установить статус accepted, дату найма и сбросить дни) */
function setLeadAccepted($lead_id) {
    $pdo = getDBConnection();
    $today = date('Y-m-d');
    $sql = "UPDATE leads SET status = 'accepted', hired_date = :hd, days_worked = 0 WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':hd' => $today, ':id' => $lead_id]);
}

/** Удалить лид */
function deleteLead($lead_id) {
    $pdo = getDBConnection();
    $sql = "DELETE FROM leads WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $lead_id]);
}

/**
 * Увеличить количество отработанных дней для лида.
 * Если статус лида ('accepted' или 'Трудоустроен') и days_worked достигает 150,
 * производится выплата 5000 руб. и устанавливается флаг is_paid.
 */
function incDaysWorked($lead_id) {
    $pdo = getDBConnection();
    $q1 = "SELECT * FROM leads WHERE id = :id";
    $s1 = $pdo->prepare($q1);
    $s1->execute([':id' => $lead_id]);
    $lead = $s1->fetch();
    if (!$lead) return;
    $newDays = $lead['days_worked'] + 1;
    $q2 = "UPDATE leads SET days_worked = :dw WHERE id = :id";
    $s2 = $pdo->prepare($q2);
    $s2->execute([':dw' => $newDays, ':id' => $lead_id]);
    if (
        $lead['is_paid'] == 0 &&
        ($lead['status'] === 'accepted' || $lead['status'] === 'Трудоустроен') &&
        $newDays >= 150
    ) {
        $pid = $lead['partner_id'];
        addWalletHistory($pid, 5000, "Выплата за лида #$lead_id (150 дней)", $lead_id);
        $q3 = "UPDATE leads SET is_paid = 1 WHERE id = :id";
        $s3 = $pdo->prepare($q3);
        $s3->execute([':id' => $lead_id]);
    }
}

/** Удалить лид полностью */
function deleteLeadComplete($lead_id) {
    $pdo = getDBConnection();
    $sql = "DELETE FROM leads WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $lead_id]);
}

/**
 * Архивировать лид (не удаляя запись).
 * Требуется, чтобы в таблице leads было поле archived.
 */
function archiveLead($lead_id) {
    $pdo = getDBConnection();
    $sql = "UPDATE leads SET archived = 1 WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $lead_id]);
}

/**
 * Получить все «новые» лиды (не архивированные).
 */
function getNewLeads() {
    $pdo = getDBConnection();
    $sql = "SELECT l.*, p.name as partner_name
            FROM leads l
            LEFT JOIN partners p ON l.partner_id = p.id
            WHERE l.archived = 0
              AND (l.status = 'Новый отклик'
                   OR l.status = 'Приглашен на собеседование'
                   OR l.status = 'Не прошел собеседование'
                   OR l.status = 'Не соответствует требованиям'
                   OR l.status = 'Отказ соискателя')
            ORDER BY l.created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Получить все архивированные лиды.
 */
function getArchivedLeads() {
    $pdo = getDBConnection();
    $sql = "SELECT l.*, p.name as partner_name
            FROM leads l
            LEFT JOIN partners p ON l.partner_id = p.id
            WHERE l.archived = 1
            ORDER BY l.created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Получить «отработанные» лиды (например, с status = 'Трудоустроен' и days_worked >= 150).
 */
function getWorkedLeads($daysThreshold = 150) {
    $pdo = getDBConnection();
    $sql = "SELECT l.*, p.name as partner_name
            FROM leads l
            LEFT JOIN partners p ON l.partner_id = p.id
            WHERE l.status = 'Трудоустроен'
              AND l.days_worked >= :th
              AND l.archived = 0
            ORDER BY l.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':th' => $daysThreshold]);
    return $stmt->fetchAll();
}

/****************************************************
 * Функции для работы с балансом и заявками на вывод средств
 ****************************************************/

/**
 * Добавить запись в историю кошелька и обновить баланс партнёра.
 */
function addWalletHistory($partner_id, $amount, $reason, $lead_id = null, $request_id = null) {
    $pdo = getDBConnection();
    $sql1 = "INSERT INTO wallet_history (partner_id, amount, reason, lead_id, request_id)
             VALUES (:pid, :amt, :rsn, :lid, :rid)";
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute([
        ':pid' => $partner_id,
        ':amt' => $amount,
        ':rsn' => $reason,
        ':lid' => $lead_id,
        ':rid' => $request_id
    ]);
    $sql2 = "UPDATE partners SET wallet_balance = wallet_balance + :amt WHERE id = :pid";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([':amt' => $amount, ':pid' => $partner_id]);
}

/** Получить лиды конкретного партнёра */
function getLeadsByPartner($partner_id) {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM leads WHERE partner_id = :pid ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pid' => $partner_id]);
    return $stmt->fetchAll();
}

/** Обновить профиль партнёра */
function updatePartnerProfile($partner_id, $new_name, $new_pass = null, $new_payment_info = null) {
    $pdo = getDBConnection();
    $sqlP = "SELECT * FROM partners WHERE id = :id";
    $stP = $pdo->prepare($sqlP);
    $stP->execute([':id' => $partner_id]);
    $p = $stP->fetch();
    if (!$p) return;
    
    $passClause = "";
    $infoClause = "";
    $params = [':nm' => $new_name, ':id' => $partner_id];
    if ($new_pass) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $passClause = ", password = :pw";
        $params[':pw'] = $hash;
    }
    if (!is_null($new_payment_info)) {
        $infoClause = ", payment_info = :inf";
        $params[':inf'] = $new_payment_info;
    }
    $sqlU = "UPDATE partners SET name = :nm $passClause $infoClause WHERE id = :id";
    $stU = $pdo->prepare($sqlU);
    $stU->execute($params);
    
    if ($new_pass) {
        $sql3 = "UPDATE users SET password = :pw WHERE email = :em";
        $st3 = $pdo->prepare($sql3);
        $st3->execute([':pw' => $hash, ':em' => $p['email']]);
    }
}

/**
 * Создать заявку на вывод средств.
 */
function createWithdrawRequest($partner_id, $amount) {
    $pdo = getDBConnection();
    $q = "SELECT wallet_balance, frozen_balance, payment_info FROM partners WHERE id = :id";
    $s = $pdo->prepare($q);
    $s->execute([':id' => $partner_id]);
    $p = $s->fetch();
    if (!$p) return false;
    if ($p['wallet_balance'] < $amount) {
        return false;
    }
    $newWb = $p['wallet_balance'] - $amount;
    $newFr = $p['frozen_balance'] + $amount;
    $q2 = "UPDATE partners SET wallet_balance = :wb, frozen_balance = :fr WHERE id = :id";
    $s2 = $pdo->prepare($q2);
    $s2->execute([
        ':wb' => $newWb,
        ':fr' => $newFr,
        ':id' => $partner_id
    ]);
    $sql2 = "INSERT INTO withdraw_requests (partner_id, amount) VALUES (:pid, :amt)";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([
        ':pid' => $partner_id,
        ':amt' => $amount
    ]);
    return true;
}

/** Получить все заявки на вывод (для админа) */
function getAllWithdrawRequests($onlyActive = true) {
    $pdo = getDBConnection();
    $where = $onlyActive ? "WHERE w.status = 'pending'" : "";
    $sql = "SELECT w.*, p.name as partner_name, p.payment_info
            FROM withdraw_requests w
            LEFT JOIN partners p ON w.partner_id = p.id
            $where
            ORDER BY w.created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/** Получить заявки на вывод для конкретного партнёра */
function getPartnerWithdrawRequests($partner_id) {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM withdraw_requests WHERE partner_id = :pid ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pid' => $partner_id]);
    return $stmt->fetchAll();
}

/** Обновить статус заявки на вывод */
function updateWithdrawRequestStatus($request_id, $new_status) {
    $pdo = getDBConnection();
    $q1 = "SELECT * FROM withdraw_requests WHERE id = :id";
    $s1 = $pdo->prepare($q1);
    $s1->execute([':id' => $request_id]);
    $wr = $s1->fetch();
    if (!$wr) return;
    $q2 = "UPDATE withdraw_requests SET status = :st WHERE id = :id";
    $s2 = $pdo->prepare($q2);
    $s2->execute([':st' => $new_status, ':id' => $request_id]);
    if ($new_status === 'declined') {
        $q3 = "SELECT wallet_balance, frozen_balance FROM partners WHERE id = :pid";
        $s3 = $pdo->prepare($q3);
        $s3->execute([':pid' => $wr['partner_id']]);
        $p = $s3->fetch();
        if ($p) {
            $restoredFr = $p['frozen_balance'] - $wr['amount'];
            $backWb = $p['wallet_balance'] + $wr['amount'];
            $q4 = "UPDATE partners SET wallet_balance = :wb, frozen_balance = :fr WHERE id = :pid";
            $s4 = $pdo->prepare($q4);
            $s4->execute([
                ':wb' => $backWb,
                ':fr' => $restoredFr,
                ':pid' => $wr['partner_id']
            ]);
        }
    }
    if ($new_status === 'approved') {
        $q5 = "SELECT wallet_balance, frozen_balance FROM partners WHERE id = :pid";
        $s5 = $pdo->prepare($q5);
        $s5->execute([':pid' => $wr['partner_id']]);
        $p2 = $s5->fetch();
        if ($p2) {
            $fr2 = $p2['frozen_balance'] - $wr['amount'];
            $q6 = "UPDATE partners SET frozen_balance = :fr WHERE id = :pid";
            $s6 = $pdo->prepare($q6);
            $s6->execute([
                ':fr' => $fr2,
                ':pid' => $wr['partner_id']
            ]);
            addWalletHistory(
                $wr['partner_id'],
                ($wr['amount'] * -1),
                "Вывод средств (req #$request_id)",
                null,
                $wr['id']
            );
        }
    }
}

/** Получить статистику по всем партнёрам */
function getAllPartnersStats() {
    $pdo = getDBConnection();
    $sql = "SELECT p.*,
       (SELECT COUNT(*) FROM leads WHERE partner_id = p.id) as leads_count
       FROM partners p
       ORDER BY p.created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/** Получить всех кандидатов */
function getAllCandidates() {
    $pdo = getDBConnection();
    $sql = "SELECT c.*, u.email
            FROM candidates c
            LEFT JOIN users u ON c.user_id = u.id
            ORDER BY c.created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/** Получить общее количество партнёров */
function getTotalPartnersCount() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM partners");
    $row = $stmt->fetch();
    return $row ? (int)$row['cnt'] : 0;
}

/** Получить общее количество кандидатов */
function getTotalCandidatesCount() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM candidates");
    $row = $stmt->fetch();
    return $row ? (int)$row['cnt'] : 0;
}

/** Получить общее количество вакансий */
function getTotalVacanciesCount() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM vacancies");
    $row = $stmt->fetch();
    return $row ? (int)$row['cnt'] : 0;
}

/** Получить общее количество лидов */
function getTotalLeadsCount() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM leads");
    $row = $stmt->fetch();
    return $row ? (int)$row['cnt'] : 0;
}

/** Получить количество лидов по статусу */
function getLeadsCountByStatus($status) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM leads WHERE status = :st");
    $stmt->execute([':st' => $status]);
    $row = $stmt->fetch();
    return $row ? (int)$row['cnt'] : 0;
}

/****************************************************
 * Дополнительные функции для лидов (при необходимости)
 ****************************************************/

/** Получить приглашённых лидов */
function getInvitedLeads() {
    $pdo = getDBConnection();
    $sql = "SELECT l.*, p.name as partner_name
            FROM leads l
            LEFT JOIN partners p ON l.partner_id = p.id
            WHERE l.status IN ('new','accepted','interview_scheduled','interview_fail','interview_pass')
            ORDER BY l.created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/** Получить трудоустроенных лидов */
function getEmployedLeads() {
    $pdo = getDBConnection();
    $sql = "SELECT l.*, p.name as partner_name
            FROM leads l
            LEFT JOIN partners p ON l.partner_id = p.id
            WHERE l.status = 'employed'
            ORDER BY l.created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/****************************************************
 * Функции для работы с кандидатами и заявками
 ****************************************************/

/**
 * Создать заявку кандидата на вакансию.
 * Если кандидат с указанным email не зарегистрирован, функция возвращает false,
 * чтобы пользователь мог пройти на страницу регистрации.
 * Если кандидат уже зарегистрирован, заявка создается и, если кандидат пришёл по реферальной ссылке,
 * в поле ref_partner_id записывается ID партнёра.
 *
 * Требуется, чтобы в таблице candidate_applications было поле ref_partner_id.
 */
function createCandidateApplication($full_name, $email, $cover_letter, $vacancy_id) {
    $pdo = getDBConnection();

    // Проверяем, существует ли кандидат (role = 'candidate')
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :em AND role = 'candidate'");
    $stmt->execute([':em' => $email]);
    $candidate = $stmt->fetch();

    if (!$candidate) {
        // Автоматическая регистрация кандидата с генерацией пароля
        $generated_password = bin2hex(random_bytes(4));
        $hash = password_hash($generated_password, PASSWORD_DEFAULT);

        // Вставка в таблицу users
        $sql1 = "INSERT INTO users (email, password, role) VALUES (:em, :pw, 'candidate')";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->execute([':em' => $email, ':pw' => $hash]);
        $user_id = $pdo->lastInsertId();

        // Вставка в таблицу candidates
        $sql2 = "INSERT INTO candidates (user_id, full_name) VALUES (:uid, :fn)";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([':uid' => $user_id, ':fn' => $full_name]);

        $candidate = [
            'id' => $user_id,
            'email' => $email,
            'role' => 'candidate',
            'generated_password' => $generated_password
        ];
    } else {
        // Пользователь уже существует
        $candidate['generated_password'] = null;
        $user_id = $candidate['id'];
    }

    // Проверяем наличие реферального партнёра
    $ref_partner_id = (isset($_SESSION['ref_partner_id']) && $_SESSION['ref_partner_id'] > 0) ? $_SESSION['ref_partner_id'] : null;

    $sql = "INSERT INTO candidate_applications (candidate_id, vacancy_id, full_name, email, cover_letter, status, ref_partner_id)
            VALUES (:cid, :vid, :fn, :em, :cl, 'Новая', :ref)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':cid' => $user_id,
        ':vid' => $vacancy_id,
        ':fn'  => $full_name,
        ':em'  => $email,
        ':cl'  => $cover_letter,
        ':ref' => $ref_partner_id
    ]);

    $application_id = $pdo->lastInsertId();
    return [
        'candidate' => $candidate,
        'application_id' => $application_id
    ];
}

/**
 * Получить заявку кандидата по его ID.
 */
function getCandidateApplication($candidate_id) {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM candidate_applications WHERE candidate_id = :cid ORDER BY created_at DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cid' => $candidate_id]);
    return $stmt->fetch();
}

/**
 * Обновить статус заявки кандидата.
 */
function updateCandidateApplicationStatus($application_id, $status) {
    $pdo = getDBConnection();
    $sql = "UPDATE candidate_applications SET status = :status WHERE id = :appid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':status' => $status, ':appid' => $application_id]);
}

/****************************************************
 * Функции для чата между кандидатом и администратором
 ****************************************************/

/**
 * Отправить сообщение в чат для конкретной заявки кандидата.
 * $application_id – ID заявки из candidate_applications.
 * $sender – 'candidate' или 'admin'.
 * $message – текст сообщения.
 *
 * Предполагается, что таблица chat_messages имеет поля:
 * id, application_id, sender, message, created_at.
 */
function sendChatMessage($application_id, $sender, $message) {
    $pdo = getDBConnection();
    $sql = "INSERT INTO chat_messages (application_id, sender, message)
            VALUES (:appid, :sender, :msg)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':appid' => $application_id,
        ':sender' => $sender,
        ':msg' => $message
    ]);
}

/**
 * Получить все сообщения чата для заданной заявки.
 */
function getChatMessages($application_id) {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM chat_messages WHERE application_id = :appid ORDER BY created_at ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':appid' => $application_id]);
    return $stmt->fetchAll();
}

/****************************************************
 * Дополнительная функция: Получить заявки кандидатов,
 * привлечённых определённым партнёром (по реферальной ссылке).
 ****************************************************/
function getCandidatesByPartner($partner_id) {
    $pdo = getDBConnection();
    $sql = "SELECT ca.*, u.email, u.created_at as user_created
            FROM candidate_applications ca
            LEFT JOIN users u ON ca.candidate_id = u.id
            WHERE ca.ref_partner_id = :ref
            ORDER BY ca.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':ref' => $partner_id]);
    return $stmt->fetchAll();
}

/****************************************************
 * Функции для работы с партнёром (дополнительный функционал)
 ****************************************************/

/**
 * Получить данные партнёра по его ID.
 */
function getPartnerById($partner_id) {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM partners WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $partner_id]);
    return $stmt->fetch();
}

/**
 * Получить данные для личного кабинета партнёра:
 * - Список заявок на вывод
 * - Список лидов (приглашённых)
 * - Статистика по кликам и лидам
 * - Реферальная ссылка
 */
function getPartnerDashboardData($partner_id) {
    $partner = getPartnerById($partner_id);
    return [
        'withdraw_requests' => getPartnerWithdrawRequests($partner_id),
        'leads' => getLeadsByPartner($partner_id),
        'clicks' => getClicksCount($partner_id),
        'referral_link' => getConfig()['app']['base_url'] . "/index.php?ref=" . ($partner['referral_code'] ?? '')
    ];
}

?>
