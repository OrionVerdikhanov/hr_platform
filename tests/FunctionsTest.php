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
}
