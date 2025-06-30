public function __construct(
        string $templateFile = null,
        array $defaultStyles = ['/assets/css/reset.css', '/assets/css/main.css'],
        array $defaultScripts = ['/assets/js/vendor/jquery.min.js', '/assets/js/app.js']
    ) {
        $this->templateFile = $templateFile ?? __DIR__ . '/templates/master.php';
        if (!is_file($this->templateFile) || !is_readable($this->templateFile)) {
            throw new \RuntimeException("Master template not found: {$this->templateFile}");
        }
        $this->defaultStyles = $defaultStyles;
        $this->defaultScripts = $defaultScripts;
    }

    private function sanitizeContent(string $content): string
    {
        // Remove script and style tags to prevent XSS
        $content = preg_replace('#<(script|style).*?>.*?</\1>#is', '', $content);
        return $content;
    }

    public function render(array $data = []): void
    {
        $title = htmlspecialchars($data['title'] ?? 'SemanticSEO Pro Analyzer', ENT_QUOTES, 'UTF-8');
        $metaDescription = htmlspecialchars($data['metaDescription'] ?? '', ENT_QUOTES, 'UTF-8');
        $bodyClass = htmlspecialchars($data['bodyClass'] ?? '', ENT_QUOTES, 'UTF-8');
        $styles = $data['styles'] ?? $this->defaultStyles;
        $scripts = $data['scripts'] ?? $this->defaultScripts;
        $content = $this->sanitizeContent($data['content'] ?? '');

        include $this->templateFile;
    }
}

// Bootstrap rendering
$pageData = [
    'title' => $pageTitle ?? 'SemanticSEO Pro Analyzer',
    'metaDescription' => $metaDescription ?? '',
    'bodyClass' => $bodyClass ?? '',
    'styles' => $styles ?? [],
    'scripts' => $scripts ?? [],
    'content' => $content ?? '',
];

$renderer = new MasterPageRenderer();
$renderer->render($pageData);