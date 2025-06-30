function renderHeader($user)
{
    $appName = defined('APP_NAME') ? APP_NAME : 'SemanticSEO Pro Analyzer';
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    $base = $baseUrl;
    $csrfToken = '';
    if (function_exists('getCsrfToken')) {
        $csrfToken = getCsrfToken();
    } elseif (isset($_SESSION['csrf_token'])) {
        $csrfToken = $_SESSION['csrf_token'];
    }
    ?>
    <header class="global-header">
        <div class="container">
            <div class="branding">
                <a href="<?php echo htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8'); ?>" class="brand-link">
                    <img src="<?php echo htmlspecialchars($base . '/assets/images/logo.svg', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'); ?>" class="brand-logo">
                    <span class="brand-name"><?php echo htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </div>
            <div class="user-controls">
                <?php if ($user && method_exists($user, 'isAuthenticated') && $user->isAuthenticated()): ?>
                    <div class="user-info">
                        <?php if (method_exists($user, 'getAvatarUrl')): ?>
                            <img src="<?php echo htmlspecialchars($user->getAvatarUrl(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo method_exists($user, 'getName') ? htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8') : ''; ?>" class="user-avatar">
                        <?php endif; ?>
                        <?php if (method_exists($user, 'getName')): ?>
                            <span class="user-name"><?php echo htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <form action="<?php echo htmlspecialchars($base . '/logout.php', ENT_QUOTES, 'UTF-8'); ?>" method="POST" class="logout-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-logout">Logout</button>
                    </form>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars($base . '/login.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-login">Login</a>
                    <a href="<?php echo htmlspecialchars($base . '/register.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-register">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <?php
}

function renderNavigation(array $menu)
{
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    $base = $baseUrl;
    ?>
    <nav class="main-nav" aria-label="Main Navigation">
        <div class="container">
            <ul class="nav-list">
                <?php foreach ($menu as $item): ?>
                    <?php $hasChildren = !empty($item['children']) && is_array($item['children']); ?>
                    <li class="nav-item<?php echo $hasChildren ? ' has-children' : ''; ?>">
                        <a href="<?php echo htmlspecialchars(isset($item['url']) ? $item['url'] : $base . '/', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link"<?php echo $hasChildren ? ' aria-haspopup="true" aria-expanded="false"' : ''; ?>><?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php if ($hasChildren): ?>
                            <ul class="subnav-list">
                                <?php foreach ($item['children'] as $child): ?>
                                    <li class="subnav-item">
                                        <a href="<?php echo htmlspecialchars($child['url'], ENT_QUOTES, 'UTF-8'); ?>" class="subnav-link"><?php echo htmlspecialchars($child['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>
    <?php
}

renderHeader($user ?? null);
renderNavigation($menu ?? []);
?>