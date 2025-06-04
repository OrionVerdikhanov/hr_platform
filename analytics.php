<?php
require_once 'includes/bootstrap.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: adminlogin.php');
    exit;
}

$stats = [
    'partners'   => getTotalPartnersCount(),
    'candidates' => getTotalCandidatesCount(),
    'vacancies'  => getTotalVacanciesCount(),
    'leads'      => getTotalLeadsCount(),
    'leads_new'  => getLeadsCountByStatus('new'),
    'leads_employed' => getLeadsCountByStatus('employed'),
];

$pageTitle = 'Аналитика';
include 'templates/header.php';
?>
<div class="container fade-in">
    <h2>Общая аналитика</h2>
    <table class="table-simple">
        <tr><th>Показатель</th><th>Количество</th></tr>
        <tr><td>Партнёров</td><td><?= htmlspecialchars($stats['partners']) ?></td></tr>
        <tr><td>Кандидатов</td><td><?= htmlspecialchars($stats['candidates']) ?></td></tr>
        <tr><td>Вакансий</td><td><?= htmlspecialchars($stats['vacancies']) ?></td></tr>
        <tr><td>Всего лидов</td><td><?= htmlspecialchars($stats['leads']) ?></td></tr>
        <tr><td>Новые лиды</td><td><?= htmlspecialchars($stats['leads_new']) ?></td></tr>
        <tr><td>Трудоустроено</td><td><?= htmlspecialchars($stats['leads_employed']) ?></td></tr>
    </table>
</div>
<?php include 'templates/footer.php'; ?>
