function getPDO(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $name = $_ENV['DB_NAME'] ?? '';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? '';
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function showLoginForm(array $errors = []): void
{
    $csrfToken = generateCsrfToken();
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | SemanticSEO Pro Analyzer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <h1>Login</h1>
        <?php if ($errors): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <button type="submit" name="action" value="login">Login</button>
            </div>
        </form>
    </div>
</body>
</html><?php
    exit;
}

function submitLogin(): void
{
    $errors = [];
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        $errors[] = 'Invalid CSRF token.';
        showLoginForm($errors);
    }

    // Login throttling: max 5 attempts per 15 minutes
    $maxAttempts = 5;
    $windowSeconds = 900;
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = ['count' => 0, 'first_time' => time()];
    }
    $attempts =& $_SESSION['login_attempts'];
    if ($attempts['first_time'] + $windowSeconds < time()) {
        $attempts['count'] = 0;
        $attempts['first_time'] = time();
    }
    if ($attempts['count'] >= $maxAttempts) {
        $errors[] = 'Too many login attempts. Please try again later.';
        showLoginForm($errors);
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Both username and password are required.';
        showLoginForm($errors);
    }

    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT id, password_hash, role FROM users WHERE username = :u OR email = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch();
    } catch (Exception $e) {
        $errors[] = 'An error occurred. Please try again later.';
        showLoginForm($errors);
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $attempts['count']++;
        $errors[] = 'Invalid username or password.';
        showLoginForm($errors);
    }

    // Reset attempts on successful login
    unset($_SESSION['login_attempts']);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    unset($_SESSION['csrf_token']);

    $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
    unset($_SESSION['redirect_after_login']);
    if (preg_match('/[\r\n]/', $redirect) || !preg_match('#^[a-zA-Z0-9/_\-]+\.php$#', $redirect)) {
        $redirect = 'dashboard.php';
    }

    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    submitLogin();
} else {
    showLoginForm();
}