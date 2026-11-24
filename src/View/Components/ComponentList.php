<?php

namespace Cachet\View\Components;

use Cachet\EffectiveComponentStatuses;
use Cachet\Enums\ComponentListSortEnum;
use Cachet\Enums\ComponentListViewEnum;
use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Cachet\Models\Tag;
use Cachet\Settings\AppSettings;
use Cachet\View\ComponentListFilters;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Component as ViewComponent;

/**
 * The searchable component list of the status page.
 *
 * Every request does a fixed amount of work, whatever the number of
 * components: one aggregate for the status summary, one page of rows (or a
 * capped set of grid tiles) and the filter options.
 *
 *   summary (counts by effective status, ignores the status filter)
 *   toolbar (search, group, tag, sort, view)
 *   list: one page of rows | grid: tiles grouped by component group
 */
class ComponentList extends ViewComponent
{
    public function __construct(
        private readonly EffectiveComponentStatuses $statuses,
        private readonly AppSettings $appSettings,
    ) {}

    public function render(): View|Closure|string
    {
        $filters = ComponentListFilters::fromRequest(request());
        $authenticated = auth()->check();
        $components = $this->baseQuery($filters, $authenticated);
        $counts = $this->statuses->count($components);

        if ($filters->statuses !== []) {
            $this->statuses->whereStatusIn($components, $filters->statuses);
        }

        return view('cachet::components.component-list', [
            'filters' => $filters,
            'counts' => $counts,
            'total' => array_sum($counts),
            'groups' => ComponentGroup::query()->visible($authenticated)->orderBy('order')->orderBy('name')->get(['id', 'name']),
            'tags' => $this->tags(),
            'showTags' => $this->appSettings->show_component_tags,
            'statuses' => $this->statuses,
            ...$filters->view === ComponentListViewEnum::grid
                ? $this->grid($components)
                : ['paginator' => $this->page($components, $filters)],
        ]);
    }

    /**
     * The visible components matching every filter except the statuses.
     *
     * @return Builder<Component>
     */
    private function baseQuery(ComponentListFilters $filters, bool $authenticated): Builder
    {
        return Component::query()
            ->onStatusPage($authenticated)
            ->when($filters->search !== '', fn (Builder $query) => $query->whereLike('components.name', "%{$filters->search}%"))
            ->when($filters->group !== null, fn (Builder $query) => $query->where('components.component_group_id', $filters->group))
            ->when($filters->tag !== null, fn (Builder $query) => $query->whereHas('tags', fn (Builder $query) => $query->where('slug', $filters->tag)));
    }

    /**
     * One page of rows, with the relations a row needs.
     *
     * @param  Builder<Component>  $components
     */
    private function page(Builder $components, ComponentListFilters $filters): LengthAwarePaginator
    {
        $query = match ($filters->sort) {
            ComponentListSortEnum::name => $components->orderBy('components.name'),
            ComponentListSortEnum::group => $this->orderByGroup($components)->orderBy('components.order')->orderBy('components.name'),
            ComponentListSortEnum::severity => $this->statuses->orderBySeverity($components)->orderBy('components.name'),
        };

        return $query
            ->with(['group', 'unresolvedIncidents', 'activeMaintenance', ...$this->appSettings->show_component_tags ? ['tags'] : []])
            ->paginate((int) config('cachet.component_list.per_page', 50))
            ->withQueryString();
    }

    /**
     * Every matching component as a tile, grouped, up to the grid limit.
     *
     * @param  Builder<Component>  $components
     * @return array{tiles: Collection<int, Component>, truncated: bool, gridLimit: int}
     */
    private function grid(Builder $components): array
    {
        $limit = (int) config('cachet.component_list.grid_limit', 2000);

        $tiles = $this->statuses->orderBySeverity($this->orderByGroup($components))
            ->orderBy('components.name')
            ->with('group:id,name')
            ->limit($limit + 1)
            ->get();

        return [
            'tiles' => $tiles->take($limit),
            'truncated' => $tiles->count() > $limit,
            'gridLimit' => $limit,
        ];
    }

    /**
     * Order by component group, ungrouped components last.
     *
     * @param  Builder<Component>  $components
     * @return Builder<Component>
     */
    private function orderByGroup(Builder $components): Builder
    {
        return $components
            ->leftJoin('component_groups', 'components.component_group_id', '=', 'component_groups.id')
            ->select('components.*')
            ->orderByRaw('component_groups.id is null')
            ->orderBy('component_groups.order')
            ->orderBy('component_groups.name');
    }

    /**
     * The tags attached to at least one component.
     *
     * @return Collection<int, Tag>
     */
    private function tags(): Collection
    {
        return Tag::query()
            ->whereIn('id', DB::table('taggables')->where('taggable_type', (new Component)->getMorphClass())->select('tag_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }
}
