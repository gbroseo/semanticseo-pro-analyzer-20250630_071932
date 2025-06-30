function renderFooter(): void
    {
        $year = date('Y');
        $appName = defined('APP_NAME') && APP_NAME
            ? APP_NAME
            : (function_exists('get_bloginfo') ? get_bloginfo('name') : 'SemanticSEO Pro Analyzer');
        $version = defined('APP_VERSION') && APP_VERSION
            ? APP_VERSION
            : '1.0.0';
        $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? $_SERVER['REQUEST_TIME'] ?? null;
        $loadTime = isset($startTime) ? microtime(true) - $startTime : null;
        $textDomain = defined('TEXT_DOMAIN') ? TEXT_DOMAIN : 'semanticseo-pro-analyzer';

        $esc = static function (string $string): string {
            return function_exists('esc_html')
                ? esc_html($string)
                : htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        };

        $trans = function (string $string, ...$args) use ($textDomain): string {
            $translated = function_exists('__')
                ? __($string, $textDomain)
                : $string;
            return $args
                ? vsprintf($translated, $args)
                : $translated;
        };
        ?>
        <footer id="site-footer" class="site-footer" role="contentinfo">
            <div class="container">
                <p>&copy; <?php echo $esc("{$year} {$appName}"); ?> ? <?php echo $esc($trans('Version %s', $version)); ?></p>
                <?php if ($loadTime !== null): ?>
                    <p><?php echo $esc($trans('Page generated in %s seconds.', number_format($loadTime, 3, '.', ''))); ?></p>
                <?php endif; ?>
            </div>
        </footer>
        <?php
        if (function_exists('wp_footer')) {
            wp_footer();
        }
        ?>
        </body>
        </html>
        <?php
    }
}

renderFooter();