<?php

namespace Cachet\View\Components;

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Component as ViewComponent;

/**
 * Daily uptime of all components and of each group, read from the rollup table.
 *
 * One aggregate query returns at most (groups + 1) x days rows, so the cost
 * does not depend on the number of components.
 */
class UptimeHistory extends ViewComponent
{
    public const DAYS = 90;

    public function render(): View|Closure|string
    {
        $authenticated = auth()->check();
        $from = CarbonImmutable::today()->subDays(self::DAYS - 1);
        $dates = collect($from->toPeriod(CarbonImmutable::today()))->map(fn ($day): string => $day->toDateString())->all();

        $aggregates = DB::table('component_uptime_days')
            ->join('components', 'components.id', '=', 'component_uptime_days.component_id')
            ->whereIn('component_uptime_days.component_id', Component::query()->onStatusPage($authenticated)->select('components.id'))
            ->where('component_uptime_days.date', '>=', $from->toDateString())
            ->groupBy('components.component_group_id', 'component_uptime_days.date')
            ->selectRaw('components.component_group_id as group_id, component_uptime_days.date as date')
            ->selectRaw('max(case when component_uptime_days.available_seconds > 0 then component_uptime_days.severity end) as severity')
            ->selectRaw('sum(component_uptime_days.available_seconds) as available, sum(component_uptime_days.operational_seconds) as operational')
            ->get();

        $groupNames = ComponentGroup::query()->visible($authenticated)->orderBy('order')->orderBy('name')->pluck('name', 'id');
        $byGroup = $aggregates->groupBy(fn (object $row): int => (int) $row->group_id);

        $rows = collect([['label' => __('cachet::system_status.all_components'), 'group' => null, ...$this->row($aggregates, $dates)]])
            ->merge($groupNames
                ->filter(fn (string $name, int $id): bool => $byGroup->has($id))
                ->map(fn (string $name, int $id): array => ['label' => $name, 'group' => $id, ...$this->row($byGroup->get($id), $dates)])
                ->values());

        if ($byGroup->has(0) && $groupNames->isNotEmpty()) {
            $rows->push(['label' => __('cachet::component.list.ungrouped'), 'group' => null, ...$this->row($byGroup->get(0), $dates)]);
        }

        return view('cachet::components.uptime-history', [
            'rows' => $aggregates->isEmpty() ? collect() : $rows,
            'days' => self::DAYS,
        ]);
    }

    /**
     * Combine aggregate rows into one status per day and an uptime percentage.
     *
     * @param  Collection<int, covariant object>|null  $aggregates
     * @param  list<string>  $dates
     * @return array{days: array<string, ?ComponentStatusEnum>, uptime: ?float}
     */
    private function row(?Collection $aggregates, array $dates): array
    {
        $aggregates ??= collect();
        $bySeverity = collect(ComponentStatusEnum::cases())->keyBy(fn (ComponentStatusEnum $status): int => $status->severity());
        $byDate = $aggregates->groupBy(fn (object $row): string => substr((string) $row->date, 0, 10));

        $days = [];

        foreach ($dates as $date) {
            $severity = $byDate->get($date)?->max('severity');
            $days[$date] = $severity === null ? null : $bySeverity->get((int) $severity);
        }

        $available = (int) $aggregates->sum('available');

        return [
            'days' => $days,
            'uptime' => $available > 0 ? ((int) $aggregates->sum('operational') / $available) * 100 : null,
        ];
    }
}
