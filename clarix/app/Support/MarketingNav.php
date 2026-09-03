<?php

namespace App\Support;

/**
 * Reads the marketing site's navigation out of config/marketing.php.
 *
 * The header renders the same menu data twice — desktop dropdowns and the
 * flattened mobile panel — and the footer renders its own tree, so resolving
 * an item to an href lives here rather than being repeated in three Blade
 * templates. It is also what MarketingNavTest walks, which is how an item
 * pointing at a route name that does not exist gets caught.
 */
class MarketingNav
{
    /**
     * An item's destination. Route names win over literal urls; an item with
     * neither is treated as not-yet-built rather than as an error, so a
     * half-finished menu still renders.
     */
    public static function href(array $item): string
    {
        if (isset($item['route'])) {
            return route($item['route']);
        }

        return $item['url'] ?? '#';
    }

    /**
     * Whether following this item leaves the site, and so needs target and
     * rel attributes. Only absolute http(s) urls qualify — a route-backed
     * item is ours by definition.
     */
    public static function isExternal(array $item): bool
    {
        return isset($item['url']) && str_starts_with($item['url'], 'http');
    }

    /**
     * Whether this item still has no destination.
     */
    public static function isPending(array $item): bool
    {
        return ! isset($item['route']) && ($item['url'] ?? '#') === '#';
    }

    public static function menus(): array
    {
        return config('marketing.menus', []);
    }

    public static function links(): array
    {
        return config('marketing.links', []);
    }

    public static function footer(): array
    {
        return config('marketing.footer', []);
    }

    public static function social(): array
    {
        return config('marketing.social', []);
    }

    /**
     * Every navigable item in the config, flattened, each tagged with where it
     * came from so a failing assertion can name the menu it belongs to.
     *
     * @return array<int, array{source: string, item: array}>
     */
    public static function allItems(): array
    {
        $all = [];

        foreach (self::menus() as $label => $menu) {
            foreach ($menu['items'] as $item) {
                $all[] = ['source' => "menu:{$label}", 'item' => $item];
            }
        }

        foreach (self::links() as $item) {
            $all[] = ['source' => 'links', 'item' => $item];
        }

        foreach (self::footer() as $heading => $items) {
            foreach ($items as $item) {
                $all[] = ['source' => "footer:{$heading}", 'item' => $item];
            }
        }

        return $all;
    }
}
