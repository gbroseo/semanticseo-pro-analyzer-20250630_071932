function sspa_render_sidebar_menu(array $menuItems)
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $currentPath = parse_url($requestUri, PHP_URL_PATH) ?: '';
        echo '<nav class="sspa-sidebar"><ul class="sspa-menu">';
        foreach ($menuItems as $item) {
            sspa_render_sidebar_menu_item($item, $currentPath);
        }
        echo '</ul></nav>';
    }
}

if (!function_exists('sspa_render_sidebar_menu_item')) {
    function sspa_render_sidebar_menu_item(array $item, $currentPath)
    {
        $label = isset($item['label']) ? $item['label'] : '';
        $url = isset($item['url']) ? $item['url'] : '#';
        $icon = isset($item['icon']) ? $item['icon'] : '';
        $children = isset($item['children']) && is_array($item['children']) ? $item['children'] : [];

        $menuPath = parse_url($url, PHP_URL_PATH) ?: '';
        $menuSegments = array_values(array_filter(explode('/', trim($menuPath, '/'))));
        $currentSegments = array_values(array_filter(explode('/', trim($currentPath, '/'))));

        $isActive = false;
        if ($menuSegments) {
            $currentSlice = array_slice($currentSegments, 0, count($menuSegments));
            if ($currentSlice === $menuSegments) {
                $isActive = true;
            }
        }

        $liClasses = [];
        if ($isActive) {
            $liClasses[] = 'active';
        }
        if ($children) {
            $liClasses[] = 'has-children';
        }

        echo '<li' . ($liClasses ? ' class="' . esc_attr(implode(' ', $liClasses)) . '"' : '') . '>';
        echo '<a href="' . esc_url($url) . '">';
        if ($icon) {
            echo '<i class="' . esc_attr($icon) . '"></i>';
        }
        echo '<span>' . esc_html__($label, 'semanticseo-pro-analyzer') . '</span>';
        if ($children) {
            echo '<i class="fa fa-chevron-down submenu-arrow"></i>';
        }
        echo '</a>';

        if ($children) {
            echo '<ul class="ssp-submenu">';
            foreach ($children as $child) {
                sspa_render_sidebar_menu_item($child, $currentPath);
            }
            echo '</ul>';
        }

        echo '</li>';
    }
}