private const APPLICATION_NAME = 'SemanticSEO Pro Analyzer';
    private static string $title = self::APPLICATION_NAME;

    public static function setTitle(string $title): void
    {
        $clean = trim($title);
        $fullTitle = $clean !== '' ? "{$clean} - " . self::APPLICATION_NAME : self::APPLICATION_NAME;
        self::$title = htmlspecialchars($fullTitle, ENT_QUOTES, 'UTF-8');
    }

    public static function renderLayout(string $content): void
    {
        $title = self::$title;

        $rawBaseUrl = $_ENV['APP_URL'] ?? '';
        $trimmedUrl = rtrim($rawBaseUrl, '/');
        if ($trimmedUrl !== '' && !filter_var($trimmedUrl, FILTER_VALIDATE_URL)) {
            $trimmedUrl = '';
        }
        $baseUrl = htmlspecialchars($trimmedUrl, ENT_QUOTES, 'UTF-8');

        $year = date('Y');

        // CSS asset with cache-busting
        $cssFile = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/assets/css/app.css';
        $cssVersion = file_exists($cssFile) ? filemtime($cssFile) : time();
        $cssHref = htmlspecialchars("{$baseUrl}/assets/css/app.css?v={$cssVersion}", ENT_QUOTES, 'UTF-8');

        // JS asset with cache-busting
        $jsFile = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/assets/js/app.js';
        $jsVersion = file_exists($jsFile) ? filemtime($jsFile) : time();
        $jsSrc = htmlspecialchars("{$baseUrl}/assets/js/app.js?v={$jsVersion}", ENT_QUOTES, 'UTF-8');

        // Escape content to prevent XSS
        $safeContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <link rel="stylesheet" href="{$cssHref}">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="{$baseUrl}" class="navbar-brand">SemanticSEO Pro Analyzer</a>
            <ul class="navbar-nav">
                <li><a href="{$baseUrl}/dashboard">Dashboard</a></li>
                <li><a href="{$baseUrl}/analysis">Analysis</a></li>
                <li><a href="{$baseUrl}/reports">Reports</a></li>
                <li><a href="{$baseUrl}/settings">Settings</a></li>
            </ul>
        </div>
    </nav>
    <main class="container">
{$safeContent}
    </main>
    <footer class="footer">
        <div class="container">
            <p>&copy; {$year} SemanticSEO Pro Analyzer</p>
        </div>
    </footer>
    <script src="{$jsSrc}"></script>
</body>
</html>
HTML;
    }
}