<?php

namespace App\Domain\Platform\Services;

use App\Models\User;

/**
 * Builds the CRM sidebar from config/navigation.php.
 * Later tasks (1.3) filter entries by the user's permissions here.
 */
class NavigationMenu
{
    /**
     * Tree for the sidebar, shared to Inertia as `navigation`.
     *
     * @return list<array{title: string, url: string|null, icon: string|null, phase: string|null, placeholder: bool, children: list<array{title: string, url: string, phase: string|null, placeholder: bool}>}>
     */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $menu = [];

        foreach ($this->items() as $item) {
            $children = array_values(array_filter(
                $item['children'] ?? [],
                fn (array $child): bool => $this->allowed($user, $child),
            ));

            // A group whose children are all hidden disappears; a single page needs its own permission.
            if (isset($item['children']) ? $children === [] : ! $this->allowed($user, $item)) {
                continue;
            }

            $menu[] = [
                'title' => $item['title'],
                'url' => $item['url'] ?? null,
                'icon' => $item['icon'] ?? null,
                'phase' => $item['phase'] ?? null,
                'placeholder' => (bool) ($item['placeholder'] ?? false),
                'children' => array_map(fn (array $child): array => [
                    'title' => $child['title'],
                    'url' => $child['url'],
                    'phase' => $child['phase'] ?? null,
                    'placeholder' => (bool) ($child['placeholder'] ?? false),
                ], $children),
            ];
        }

        return $menu;
    }

    /**
     * Entries with a `permission` key (screens already built) are shown only to users who have it.
     * Entries without one (placeholders) stay visible until their task adds the permission.
     *
     * @param  array<string, mixed>  $entry
     */
    private function allowed(User $user, array $entry): bool
    {
        return ! isset($entry['permission']) || $user->can($entry['permission']);
    }

    /**
     * Leaf entries not built yet, with their group title (for the breadcrumb).
     *
     * @return list<array{title: string, url: string, route: string, phase: string|null, section: string|null}>
     */
    public function placeholders(): array
    {
        $placeholders = [];

        foreach ($this->items() as $item) {
            $leaves = isset($item['children'])
                ? array_map(fn (array $child): array => [...$child, 'section' => $item['title']], $item['children'])
                : [[...$item, 'section' => null]];

            foreach ($leaves as $leaf) {
                if (! ($leaf['placeholder'] ?? false)) {
                    continue;
                }

                $placeholders[] = [
                    'title' => $leaf['title'],
                    'url' => $leaf['url'],
                    'route' => $leaf['route'],
                    'phase' => $leaf['phase'] ?? null,
                    'section' => $leaf['section'],
                ];
            }
        }

        return $placeholders;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(): array
    {
        return config('navigation.items', []);
    }
}
