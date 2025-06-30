public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function showLoginForm(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            header('Location: /dashboard.php');
            exit;
        }
        $csrf = $this->generateCsrfToken();
        include __DIR__ . '/../views/auth/login.php';
    }

    public function handleLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $csrfToken = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);
        if (!$this->validateCsrf($csrfToken)) {
            $this->setFlash('error', 'Invalid CSRF token.');
            header('Location: /login.php');
            exit;
        }

        // Rate limiting
        $maxAttempts = 5;
        $attemptWindow = 300;
        $currentTime = time();
        $attempts = $_SESSION['login_attempts'] ?? 0;
        $lastAttempt = $_SESSION['login_last_attempt'] ?? 0;
        if ($currentTime - $lastAttempt > $attemptWindow) {
            $attempts = 0;
        }
        if ($attempts >= $maxAttempts) {
            $this->setFlash('error', 'Too many login attempts. Please try again later.');
            header('Location: /login.php');
            exit;
        }

        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $this->setFlash('error', 'Email and password are required.');
            header('Location: /login.php');
            exit;
        }

        $email = strtolower($email);

        $stmt = $this->db->prepare('SELECT id, password FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $attempts++;
            $_SESSION['login_attempts'] = $attempts;
            $_SESSION['login_last_attempt'] = $currentTime;
            $this->setFlash('error', 'Invalid credentials.');
            header('Location: /login.php');
            exit;
        }

        // Successful login
        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['login_last_attempt']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];

        header('Location: /dashboard.php');
        exit;
    }

    public function showRegisterForm(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            header('Location: /dashboard.php');
            exit;
        }
        $csrf = $this->generateCsrfToken();
        include __DIR__ . '/../views/auth/register.php';
    }

    public function handleRegister(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $csrfToken = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);
        if (!$this->validateCsrf($csrfToken)) {
            $this->setFlash('error', 'Invalid CSRF token.');
            header('Location: /register.php');
            exit;
        }

        $name = trim(filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW) ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirmation'] ?? '';

        if (!$name || !$email || !$password || !$confirm) {
            $this->setFlash('error', 'All fields are required.');
            header('Location: /register.php');
            exit;
        }

        if ($password !== $confirm) {
            $this->setFlash('error', 'Passwords do not match.');
            header('Location: /register.php');
            exit;
        }

        if (strlen($password) < 8) {
            $this->setFlash('error', 'Password must be at least 8 characters.');
            header('Location: /register.php');
            exit;
        }

        $email = strtolower($email);

        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $this->setFlash('error', 'Email already in use.');
            header('Location: /register.php');
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password, created_at) VALUES (:name, :email, :password, NOW())');
        $stmt->execute([
            ':name'     => $name,
            ':email'    => $email,
            ':password' => $hash,
        ]);

        $_SESSION['user_id'] = (int)$this->db->lastInsertId();
        session_regenerate_id(true);

        header('Location: /dashboard.php');
        exit;
    }

    protected function setFlash(string $key, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash'][$key] = $message;
    }

    protected function validateCsrf(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        return true;
    }

    protected function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}