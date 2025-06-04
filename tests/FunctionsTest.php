<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../functions.php';

class FunctionsTest extends TestCase
{
    private static $pdo;

    public static function setUpBeforeClass(): void
    {
        putenv('CONFIG_PATH=' . __DIR__ . '/config.php');
        self::$pdo = getDBConnection();
        self::$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, password TEXT, role TEXT);");
        self::$pdo->exec("CREATE TABLE partners (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT);");
        self::$pdo->exec("CREATE TABLE candidates (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, full_name TEXT);");
        self::$pdo->exec("CREATE TABLE vacancies (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT);");
        self::$pdo->exec("CREATE TABLE leads (id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT, partner_id INTEGER, candidate_id INTEGER, vacancy_id INTEGER);");
    }

    public static function tearDownAfterClass(): void
    {
        putenv('CONFIG_PATH');
    }

    public function testLoginUserSuccess(): void
    {
        $email = 'test@example.com';
        $password = 'secret';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = self::$pdo->prepare("INSERT INTO users (email, password, role) VALUES (:e, :p, 'candidate')");
        $stmt->execute([':e' => $email, ':p' => $hash]);

        $user = loginUser($email, $password);
        $this->assertIsArray($user);
        $this->assertSame($email, $user['email']);
        $this->assertSame('candidate', $user['role']);
    }

    public function testLoginUserFailure(): void
    {
        $result = loginUser('nonexistent@example.com', 'pwd');
        $this->assertFalse($result);
    }

    public function testAnalyticsCounts(): void
    {
        self::$pdo->exec("INSERT INTO partners (name) VALUES ('P1')");
        self::$pdo->exec("INSERT INTO candidates (user_id, full_name) VALUES (1, 'C1')");
        self::$pdo->exec("INSERT INTO vacancies (name) VALUES ('V1')");
        self::$pdo->exec("INSERT INTO leads (status) VALUES ('new')");

        $this->assertSame(1, getTotalPartnersCount());
        $this->assertSame(1, getTotalCandidatesCount());
        $this->assertSame(1, getTotalVacanciesCount());
        $this->assertSame(1, getTotalLeadsCount());
        $this->assertSame(1, getLeadsCountByStatus('new'));
    }
}
