<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Application URL configuration
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
if (empty($appUrl)) {
    $appUrl = 'http://localhost';
}
$urlParts = parse_url($appUrl);
$scheme = $urlParts['scheme'] ?? 'http';
$appHost = $urlParts['host'] ?? 'localhost';
$appPath = $urlParts['path'] ?? '';

// Secure session cookie parameters
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $appHost,
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Database connection
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? '';
$dbUser = $_ENV['DB_USER'] ?? '';
$dbPass = $_ENV['DB_PASS'] ?? '';
$charset = 'utf8mb4';
$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed.');
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$old = ['name' => '', 'email' => ''];

// Rate limiting setup
$maxAttempts = 5;
$decaySeconds = 3600;
if (!isset($_SESSION['register_attempts']) || !is_array($_SESSION['register_attempts'])) {
    $_SESSION['register_attempts'] = [];
}
// Remove outdated attempts
$_SESSION['register_attempts'] = array_filter(
    $_SESSION['register_attempts'],
    static fn($ts) => ($ts >= time() - $decaySeconds)
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check rate limit
    if (count($_SESSION['register_attempts']) >= $maxAttempts) {
        $errors['general'] = 'Too many registration attempts. Please try again later.';
    }

    if (empty($errors)) {
        // CSRF validation
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $errors['general'] = 'Invalid CSRF token.';
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $old['name'] = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $old['email'] = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Validation
        if (empty($name)) {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Name must not exceed 100 characters.';
        }

        if (empty($email)) {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
    }

    if (empty($errors)) {
        // Check duplicate email
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['general'] = 'Registration failed. Please check your details and try again.';
        } else {
            // Create user
            $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
            $token = bin2hex(random_bytes(16));
            $expiresAt = (new DateTime('+1 day'))->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, verification_token, verification_token_expires, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, 0, NOW())'
            );
            $stmt->execute([$name, $email, $passwordHash, $token, $expiresAt]);

            // Build verification URL
            $verifyUrl = sprintf(
                '%s://%s%s/verify.php?token=%s',
                $scheme,
                $appHost,
                $appPath,
                urlencode($token)
            );

            // Send verification email
            $subject = 'SemanticSEO Pro Analyzer - Verify Your Email';
            $message = "Thank you for registering.\n\nPlease verify your email address by clicking the link below:\n$verifyUrl\n\nIf you did not register, please ignore this email.";
            $headers = 'From: no-reply@' . $appHost . "\r\n" .
                       'Reply-To: no-reply@' . $appHost . "\r\n" .
                       'X-Mailer: PHP/' . phpversion();
            mail($email, $subject, $message, $headers);

            $_SESSION['flash_message'] = 'Registration successful! Please check your email to verify your account.';
            header('Location: login.php');
            exit;
        }
    }

    // Record failed attempt
    if (!empty($errors)) {
        $_SESSION['register_attempts'][] = time();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - SemanticSEO Pro Analyzer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="register-container">
    <h1>Create an Account</h1>
    <?php if (!empty($errors['general'])): ?>
        <div class="alert error"><?= htmlspecialchars($errors['general'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>
    <form action="" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?= $old['name'] ?>" required maxlength="100">
            <?php if (!empty($errors['name'])): ?><small class="error"><?= htmlspecialchars($errors['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= $old['email'] ?>" required>
            <?php if (!empty($errors['email'])): ?><small class="error"><?= htmlspecialchars($errors['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <?php if (!empty($errors['password'])): ?><small class="error"><?= htmlspecialchars($errors['password'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            <?php if (!empty($errors['confirm_password'])): ?><small class="error"><?= htmlspecialchars($errors['confirm_password'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small><?php endif; ?>
        </div>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a>.</p>
</div>
<script src="assets/js/validation.js"></script>
</body>
</html>