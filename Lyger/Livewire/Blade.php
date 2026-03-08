<?php

declare(strict_types=1);

namespace Lyger\Livewire;

/**
 * Blade - Simple template engine for Livewire views
 */

class Blade
{
    private static string $viewsPath = '';
    private static string $cachePath = '';

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = $path;
    }

    public static function setCachePath(string $path): void
    {
        self::$cachePath = $path;
    }

    /**
     * Render a view
     */
    public static function render(string $view, array $data = []): string
    {
        $viewPath = self::$viewsPath . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            return "View not found: {$view}";
        }

        // Extract data to local scope
        extract($data);

        // Start output buffering
        ob_start();

        try {
            include $viewPath;
        } catch (\Throwable $e) {
            ob_end_clean();
            return "Error rendering view: " . $e->getMessage();
        }

        return ob_get_clean();
    }

    /**
     * Render a component (Livewire style)
     */
    public static function renderComponent(Component $component): string
    {
        return $component->render();
    }

    /**
     * Include a partial
     */
    public static function include(string $view, array $data = []): string
    {
        return self::render($view, $data);
    }

    /**
     * Yield a section
     */
    public static function yield(string $section): string
    {
        return $_SESSION['blade_sections'][$section] ?? '';
    }

    /**
     * Start a section
     */
    public static function start(string $section): void
    {
        ob_start();
        $_SESSION['blade_section'] = $section;
    }

    /**
     * End a section
     */
    public static function end(): void
    {
        $content = ob_get_clean();
        $section = $_SESSION['blade_section'] ?? 'content';

        if (!isset($_SESSION['blade_sections'])) {
            $_SESSION['blade_sections'] = [];
        }

        $_SESSION['blade_sections'][$section] = $content;
    }

    /**
     * Extends a layout
     */
    public static function extends(string $layout): void
    {
        $_SESSION['blade_layout'] = $layout;
    }

    /**
     * Component helper
     */
    public static function component(string $class, array $props = []): string
    {
        $component = new $class();
        $component->mount(...array_values($props));

        foreach ($props as $key => $value) {
            $component->$key = $value;
        }

        return $component->render();
    }

    /**
     * Loop helper
     */
    public static function each(iterable $items, string $view, string $key = 'item'): string
    {
        $html = '';

        foreach ($items as $index => $item) {
            $html .= self::render($view, [$key => $item, 'loop' => [
                'index' => $index,
                'iteration' => $index + 1,
                'first' => $index === 0,
                'last' => $index === count($items) - 1,
                'count' => count($items),
            ]]);
        }

        return $html;
    }
}

/**
 * View - Facade for Blade
 */
class View
{
    public static function make(string $view, array $data = []): string
    {
        return Blade::render($view, $data);
    }

    public static function share(string $key, $value): void
    {
        // Share data across all views
        if (!isset($_SESSION['view_data'])) {
            $_SESSION['view_data'] = [];
        }
        $_SESSION['view_data'][$key] = $value;
    }

    public static function getShared(string $key, $default = null)
    {
        return $_SESSION['view_data'][$key] ?? $default;
    }
}

/**
 * Blade Directives - Common template directives
 */
class Directives
{
    /**
     * @if($condition)
     */
    public static function if(string $condition, string $content): string
    {
        return "<?php if({$condition}): ?> {$content} <?php endif; ?>";
    }

    /**
     * @foreach($items as $item)
     */
    public static function foreach(string $items, string $item, string $content): string
    {
        return "<?php foreach({$items} as {$item}): ?> {$content} <?php endforeach; ?>";
    }

    /**
     * @forelse($items as $item)
     */
    public static function forelse(string $items, string $item, string $content, string $empty = ''): string
    {
        return "<?php if(!empty({$items})): foreach({$items} as {$item}): ?> {$content} <?php else: ?> {$empty} <?php endif; ?>";
    }

    /**
     * @for($i = 0; $i < 10; $i++)
     */
    public static function for(string $init, string $condition, string $increment, string $content): string
    {
        return "<?php for({$init}; {$condition}; {$increment}): ?> {$content} <?php endfor; ?>";
    }

    /**
     * @while($condition)
     */
    public static function while(string $condition, string $content): string
    {
        return "<?php while({$condition}): ?> {$content} <?php endwhile; ?>";
    }

    /**
     * @switch($value)
     */
    public static function switch(string $value, array $cases, string $default = ''): string
    {
        $html = "<?php switch({$value}): ?>";

        foreach ($cases as $case => $content) {
            $html .= "<?php case '{$case}': ?> {$content} <?php break; ?>";
        }

        if ($default) {
            $html .= "<?php default: ?> {$default}";
        }

        $html .= "<?php endswitch; ?>";

        return $html;
    }

    /**
     * @isset($var)
     */
    public static function isset(string $var, string $content): string
    {
        return "<?php if(isset({$var})): ?> {$content} <?php endif; ?>";
    }

    /**
     * @empty($var)
     */
    public static function empty(string $var, string $content): string
    {
        return "<?php if(empty({$var})): ?> {$content} <?php endif; ?>";
    }

    /**
     * @auth
     */
    public static function auth(string $content, string $guest = ''): string
    {
        if ($guest) {
            return "<?php if(auth()->check()): ?> {$content} <?php else: ?> {$guest} <?php endif; ?>";
        }
        return "<?php if(auth()->check()): ?> {$content} <?php endif; ?>";
    }

    /**
     * @can('permission')
     */
    public static function can(string $permission, string $content): string
    {
        return "<?php if(auth()->can('{$permission}')): ?> {$content} <?php endif; ?>";
    }
}
