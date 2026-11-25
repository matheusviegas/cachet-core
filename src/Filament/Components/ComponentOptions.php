<?php

namespace Cachet\Filament\Components;

use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ComponentOptions
{
    /**
     * The most components a single search returns.
     */
    public const SEARCH_LIMIT = 50;

    /**
     * Search components by their name or group name, grouped by component group.
     *
     * Results are capped so a select never ships every component to the browser.
     *
     * Example: search('128') => ['Area 3' => [128 => 'Robot 128']]
     *
     * @param  array<int, int|string>  $excludeIds
     * @return array<string, array<int, string>>
     */
    public static function search(string $search, array $excludeIds = [], int $limit = self::SEARCH_LIMIT): array
    {
        $search = trim($search);

        $components = self::orderedQuery()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('components.name', "%{$search}%")
                ->orWhereLike('component_groups.name', "%{$search}%")))
            ->when($excludeIds !== [], fn (Builder $query) => $query->whereNotIn('components.id', $excludeIds))
            ->limit($limit)
            ->get();

        return self::grouped($components);
    }

    /**
     * Get the labels of the given components, skipping excluded and missing ones.
     *
     * Filament validates the submitted value against these labels, so an
     * excluded or unknown id fails validation.
     *
     * @param  array<int, int|string>  $ids
     * @param  array<int, int|string>  $excludeIds
     * @return array<int, string>
     */
    public static function labels(array $ids, array $excludeIds = []): array
    {
        $ids = array_values(array_diff(array_filter($ids, 'is_numeric'), $excludeIds));

        if ($ids === []) {
            return [];
        }

        return Component::query()
            ->whereKey($ids)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Components ordered by group, then by their position within it.
     *
     * @return Builder<Component>
     */
    private static function orderedQuery(): Builder
    {
        return Component::query()
            ->with('group')
            ->leftJoin('component_groups', 'components.component_group_id', '=', 'component_groups.id')
            ->orderByRaw('component_groups.id is null')
            ->orderBy('component_groups.order')
            ->orderBy('component_groups.name')
            ->orderBy('components.order')
            ->orderBy('components.name')
            ->select('components.*');
    }

    /**
     * @param  Collection<int, Component>  $components
     * @return array<string, array<int, string>>
     */
    private static function grouped(Collection $components): array
    {
        return $components
            ->groupBy(function (Component $component): string {
                $group = $component->group;

                return $group instanceof ComponentGroup
                    ? $group->name
                    : __('cachet::component.list.ungrouped');
            })
            ->map(fn ($components): array => $components->pluck('name', 'id')->all())
            ->all();
    }
}
