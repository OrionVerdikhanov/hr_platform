<?php
require_once 'includes/bootstrap.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'candidate') {
    header("Location: candidateLogin.php");
    exit;
}

$candidate = $_SESSION['user'];
$candidateApplication = getCandidateApplication($candidate['id']);

$chatMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_action']) && $_POST['chat_action'] === 'send_message') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $chatMessage = "<p class='alert alert-danger'>Ошибка: неверный CSRF токен!</p>";
    } else {
        $messageText = trim($_POST['message'] ?? '');
        $application_id = (int)($_POST['application_id'] ?? 0);
        if ($messageText && $application_id) {
            sendChatMessage($application_id, 'candidate', $messageText);
            $chatMessage = "<p class='alert alert-success'>Сообщение отправлено!</p>";
        } else {
            $chatMessage = "<p class='alert alert-danger'>Пожалуйста, введите сообщение.</p>";
        }
    }
}

$chatMessages = [];
if ($candidateApplication) {
    $chatMessages = getChatMessages($candidateApplication['id']);
}

$csrfToken = generateCSRFToken();
?>

$pageTitle = 'Кабинет кандидата';
include 'templates/header.php';
<div class="container">
        <h1>Личный кабинет кандидата</h1>
        <h2>Информация о заявке</h2>
        <?php if ($candidateApplication): ?>
            <p><strong>Вакансия ID:</strong> <?= htmlspecialchars($candidateApplication['vacancy_id']) ?></p>
            <p><strong>Статус заявки:</strong> <?= htmlspecialchars($candidateApplication['status']) ?></p>
            <p><strong>ФИО:</strong> <?= htmlspecialchars($candidateApplication['full_name'] ?? '') ?></p>
            <p><strong>Телефон:</strong> <?= htmlspecialchars($candidateApplication['phone'] ?? '') ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($candidateApplication['email'] ?? '') ?></p>
            <p><strong>Сопроводительное письмо:</strong></p>
            <p><?= nl2br(htmlspecialchars($candidateApplication['cover_letter'])) ?></p>
        <?php else: ?>
            <p>Заявка не найдена.</p>
        <?php endif; ?>
        
        <hr>
        <h2>Чат с администратором</h2>
        <?php echo $chatMessage; ?>
        <div class="chat-window" style="border: 1px solid #ccc; padding: 1rem; max-height: 300px; overflow-y: scroll;">
            <?php if (!empty($chatMessages)): ?>
                <?php foreach ($chatMessages as $msg): ?>
                    <p>
                        <strong><?= ucfirst($msg['sender']) ?>:</strong>
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                        <br>
                        <small><?= htmlspecialchars($msg['created_at']) ?></small>
                    </p>
                    <hr>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Чат пока пуст.</p>
            <?php endif; ?>
        </div>
        <form method="POST" class="form-block" style="margin-top: 1rem;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="chat_action" value="send_message">
            <input type="hidden" name="application_id" value="<?= $candidateApplication ? $candidateApplication['id'] : 0 ?>">
            <label>Новое сообщение:
                <textarea name="message" required></textarea>
            </label>
            <button type="submit" class="btn-main">Отправить</button>
        </form>
    </div>
<?php include 'templates/footer.php'; ?>
