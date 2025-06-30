function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

function loadSidebar(): array
{
    $isWp = defined('ABSPATH');
    $items = [
        ['slug' => 'dashboard',     'title' => __('Dashboard', 'semanticseo-pro'),     'icon' => $isWp ? 'dashicons-dashboard'       : 'fa fa-home',           'capability' => 'manage_options'],
        ['slug' => 'analyses',      'title' => __('Analyses', 'semanticseo-pro'),      'icon' => $isWp ? 'dashicons-chart-bar'      : 'fa fa-chart-bar',      'capability' => 'view_analyses'],
        ['slug' => 'reports',       'title' => __('Reports', 'semanticseo-pro'),       'icon' => $isWp ? 'dashicons-media-spreadsheet': 'fa fa-file-alt',        'capability' => 'view_reports'],
        ['slug' => 'scheduling',    'title' => __('Scheduling', 'semanticseo-pro'),    'icon' => $isWp ? 'dashicons-clock'          : 'fa fa-clock',          'capability' => 'manage_scheduling'],
        ['slug' => 'subscriptions', 'title' => __('Subscriptions', 'semanticseo-pro'),'icon' => $isWp ? 'dashicons-admin-users'    : 'fa fa-users',          'capability' => 'manage_subscriptions'],
        ['slug' => 'settings',      'title' => __('Settings', 'semanticseo-pro'),      'icon' => $isWp ? 'dashicons-admin-generic'  : 'fa fa-cog',            'capability' => 'manage_options'],
        ['slug' => 'logout',        'title' => __('Sign Out', 'semanticseo-pro'),      'icon' => $isWp ? 'dashicons-dismiss'        : 'fa fa-sign-out-alt',   'capability' => ''],
    ];

    $baseUrl = $isWp ? admin_url('admin.php?page=semanticseo_') : '/app.php?page=';

    foreach ($items as $key => $item) {
        if ($item['slug'] === 'logout') {
            if ($isWp) {
                $items[$key]['url'] = wp_logout_url();
            } else {
                $token = getCsrfToken();
                $items[$key]['url'] = '/logout.php?token=' . urlencode($token);
            }
        } else {
            $items[$key]['url'] = $baseUrl . $item['slug'];
        }
    }

    return $items;
}

function renderSidebar($user = null): void
{
    $isWp = defined('ABSPATH');

    if ($user === null) {
        if ($isWp && function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
        } elseif (class_exists('Auth') && method_exists('Auth', 'user')) {
            $user = Auth::user();
        }
    }

    // Require authentication for non-WP contexts
    if (!$isWp && $user === null) {
        return;
    }

    $items = loadSidebar();

    // Detect current page slug
    $pageParam = isset($_GET['page']) ? (string)$_GET['page'] : '';
    if ($isWp) {
        // Strip 'semanticseo_' prefix for WP
        $currentSlug = preg_replace('/^semanticseo_/', '', sanitize_text_field($pageParam));
    } else {
        if ($pageParam !== '') {
            $currentSlug = $pageParam;
        } else {
            $currentSlug = basename((string)($_SERVER['PHP_SELF'] ?? ''), '.php');
        }
    }

    echo '<aside class="semanticseo-sidebar" role="navigation" aria-label="' . esc_attr__('Main Sidebar', 'semanticseo-pro') . '">';
    echo '<ul class="semanticseo-sidebar-list">';

    foreach ($items as $item) {
        $cap = $item['capability'] ?? '';
        $hasPerm = false;

        if ($cap === '') {
            // No capability required (e.g., logout)
            $hasPerm = $isWp || $user !== null;
        } else {
            if ($isWp) {
                $hasPerm = current_user_can($cap);
            } elseif ($user !== null && method_exists($user, 'can')) {
                $hasPerm = $user->can($cap);
            }
        }

        if (!$hasPerm) {
            continue;
        }

        $activeClass = ($item['slug'] === $currentSlug) ? ' is-active' : '';
        $url = $item['url'];

        echo '<li class="semanticseo-sidebar-item' . esc_attr($activeClass) . '">';
        echo '<a href="' . esc_url($url) . '" class="semanticseo-sidebar-link">';
        if (!empty($item['icon'])) {
            echo '<span class="semanticseo-sidebar-icon ' . esc_attr($item['icon']) . '" aria-hidden="true"></span>';
        }
        echo '<span class="semanticseo-sidebar-title">' . esc_html($item['title']) . '</span>';
        echo '</a>';
        echo '</li>';
    }

    echo '</ul></aside>';
}

renderSidebar();