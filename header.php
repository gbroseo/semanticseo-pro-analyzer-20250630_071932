<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$defaultTitle = 'SemanticSEO Pro Analyzer';
$pageTitle   = defined('PAGE_TITLE') ? PAGE_TITLE : $defaultTitle;
$assetsUrl   = rtrim((string)getenv('ASSETS_URL') ?: '/assets', '/');
$baseUrl     = rtrim((string)getenv('BASE_URL')   ?: '', '/');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken   = $_SESSION['csrf_token'];
$currentUser = null;
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $currentUser = (object)$_SESSION['user'];
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetsUrl, ENT_QUOTES, 'UTF-8'); ?>/css/main.css">
    <script src="<?php echo htmlspecialchars($assetsUrl, ENT_QUOTES, 'UTF-8'); ?>/js/main.js" defer></script>
</head>
<body>
<header class="site-header">
    <div class="container">
        <a class="logo" href="<?php echo htmlspecialchars($baseUrl . '/', ENT_QUOTES, 'UTF-8'); ?>">
            <img src="<?php echo htmlspecialchars($assetsUrl . '/images/logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="SemanticSEO Logo">
        </a>
        <nav class="main-nav">
            <ul>
                <?php
                $navItems = [
                    ['Dashboard', '/dashboard'],
                    ['Analyze',    '/analyze'],
                    ['Reports',    '/reports'],
                    ['Settings',   '/settings'],
                ];
                foreach ($navItems as [$label, $path]) {
                    $href = $baseUrl . $path;
                    echo '<li><a href="', htmlspecialchars($href, ENT_QUOTES, 'UTF-8'), '">', htmlspecialchars($label, ENT_QUOTES, 'UTF-8'), '</a></li>';
                }
                ?>
            </ul>
        </nav>
        <div class="user-menu">
            <?php if ($currentUser): ?>
                <?php
                    $name       = htmlspecialchars($currentUser->name ?? '', ENT_QUOTES, 'UTF-8');
                    $rawAvatar  = $currentUser->avatar ?? $assetsUrl . '/images/default-avatar.png';
                    if (preg_match('#^https?://#i', $rawAvatar) || strpos($rawAvatar, '/') === 0) {
                        $avatarUrl = $rawAvatar;
                    } else {
                        $avatarUrl = $assetsUrl . '/images/default-avatar.png';
                    }
                    $avatar     = htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8');
                ?>
                <button class="user-toggle">
                    <img src="<?php echo $avatar; ?>" alt="<?php echo $name; ?>">
                </button>
                <div class="user-dropdown">
                    <p><?php echo $name; ?></p>
                    <a href="<?php echo htmlspecialchars($baseUrl . '/profile', ENT_QUOTES, 'UTF-8'); ?>">Profile</a>
                    <form method="POST" action="<?php echo htmlspecialchars($baseUrl . '/logout', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit">Logout</button>
                    </form>
                </div>
            <?php else: ?>
                <a class="btn login" href="<?php echo htmlspecialchars($baseUrl . '/login', ENT_QUOTES, 'UTF-8'); ?>">Login</a>
                <a class="btn register" href="<?php echo htmlspecialchars($baseUrl . '/register', ENT_QUOTES, 'UTF-8'); ?>">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>