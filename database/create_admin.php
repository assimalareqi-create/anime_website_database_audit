<?php
/**
 * CLI-only helper to create the first administrator safely.
 * Run: php database/create_admin.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

require_once __DIR__ . '/../config/database.php';

function ask(string $label): string {
    fwrite(STDOUT, $label);
    $value = trim((string) fgets(STDIN));
    return $value;
}

$username = ask('Admin username: ');
$email = ask('Admin email: ');
fwrite(STDOUT, 'Admin password (minimum 12 characters): ');
if (PHP_OS_FAMILY === 'Windows') {
    $password = trim((string) fgets(STDIN));
} else {
    shell_exec('stty -echo');
    $password = trim((string) fgets(STDIN));
    shell_exec('stty echo');
    fwrite(STDOUT, "\n");
}

if (!preg_match('/^[\pL\pN_.-]{3,50}$/u', $username)) {
    fwrite(STDERR, "Invalid username. Use 3-50 letters, numbers, dot, underscore or hyphen.\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email address.\n");
    exit(1);
}
if (strlen($password) < 12) {
    fwrite(STDERR, "Password must contain at least 12 characters.\n");
    exit(1);
}

try {
    $db = getDbConnection();
    if (!$db) {
        throw new RuntimeException('Could not connect to the configured database.');
    }
    $count = (int) $db->query('SELECT COUNT(*) FROM users WHERE role = 3')->fetchColumn();
    if ($count > 0) {
        throw new RuntimeException('An administrator already exists. Refusing to create another initial admin.');
    }
    $stmt = $db->prepare('INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, 3)');
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    fwrite(STDOUT, "Administrator created successfully. Delete/retain this CLI helper according to your deployment policy.\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
