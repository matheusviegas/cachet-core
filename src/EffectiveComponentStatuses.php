<?php

namespace Cachet;

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Models\Component;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves effective component statuses in SQL-friendly form.
 *
 * The `status` column only holds the baseline. Unresolved incidents and
 * maintenance in progress can override it, but only for the few components
 * they touch. Those are loaded once per request and their effective status is
 * resolved in PHP, so every other query can filter, count and sort by
 * effective status with plain SQL, whatever the number of components.
 *
 *   baseline (column) ──┐
 *                       ├─> effective status
 *   impacts (few rows) ─┘   overrides() keeps only the components where it differs
 */
class EffectiveComponentStatuses
{
    /**
     * Effective statuses keyed by component id, for components whose baseline is overridden.
     *
     * @var Collection<int, ComponentStatusEnum>|null
     */
    private ?Collection $overrides = null;

    /**
     * Get the components whose effective status differs from their baseline.
     *
     * @return Collection<int, ComponentStatusEnum>
     */
    public function overrides(): Collection
    {
        return $this->overrides ??= Component::query()
            ->where(fn (Builder $query) => $query
                ->whereHas('unresolvedIncidents')
                ->orWhereHas('activeMaintenance'))
            ->with(['unresolvedIncidents', 'activeMaintenance'])
            ->get()
            ->filter(fn (Component $component): bool => $component->latest_status !== $component->status)
            ->mapWithKeys(fn (Component $component): array => [$component->getKey() => $component->latest_status])
            ->toBase();
    }

    /**
     * Get the effective status of a component.
     */
    public function of(Component $component): ComponentStatusEnum
    {
        return $this->overrides()->get($component->getKey(), $component->status);
    }

    /**
     * Restrict the query to components with any of the given effective statuses.
     *
     * @param  Builder<Component>  $query
     * @param  array<int, ComponentStatusEnum>  $statuses
     * @return Builder<Component>
     */
    public function whereStatusIn(Builder $query, array $statuses): Builder
    {
        $table = $query->getModel()->getTable();
        $overridden = $this->overrides()->keys()->all();
        $matching = $this->overrides()
            ->filter(fn (ComponentStatusEnum $status): bool => in_array($status, $statuses, true))
            ->keys()
            ->all();

        return $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query
                ->whereIn("{$table}.status", array_map(fn (ComponentStatusEnum $status): int => $status->value, $statuses))
                ->whereNotIn("{$table}.id", $overridden))
            ->orWhereIn("{$table}.id", $matching));
    }

    /**
     * Count the components matched by the query, by effective status.
     *
     * @param  Builder<Component>  $query
     * @return array<string, int>
     */
    public function count(Builder $query): array
    {
        $table = $query->getModel()->getTable();

        $baseline = (clone $query)
            ->toBase()
            ->reorder()
            ->selectRaw("{$table}.status as status, count(*) as aggregate")
            ->groupBy("{$table}.status")
            ->pluck('aggregate', 'status');

        $counts = collect(ComponentStatusEnum::cases())
            ->mapWithKeys(fn (ComponentStatusEnum $status): array => [$status->name => (int) $baseline->get($status->value, 0)])
            ->all();

        if ($this->overrides()->isEmpty()) {
            return $counts;
        }

        (clone $query)
            ->reorder()
            ->whereIn("{$table}.id", $this->overrides()->keys())
            ->pluck("{$table}.status", "{$table}.id")
            ->each(function (mixed $baselineStatus, int $id) use (&$counts): void {
                $from = $baselineStatus instanceof ComponentStatusEnum ? $baselineStatus : ComponentStatusEnum::from((int) $baselineStatus);
                $to = $this->overrides()->get($id);

                $counts[$from->name]--;
                $counts[$to->name]++;
            });

        return $counts;
    }

    /**
     * Order the query by effective severity.
     *
     * Example SQL: case when components.id in (7) then 5 when components.status = 4 then 5 ... else 0 end desc
     *
     * @param  Builder<Component>  $query
     * @return Builder<Component>
     */
    public function orderBySeverity(Builder $query, string $direction = 'desc'): Builder
    {
        $table = $query->getModel()->getTable();
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $cases = [];
        $bindings = [];

        foreach ($this->overrides()->groupBy(fn (ComponentStatusEnum $status): int => $status->severity(), preserveKeys: true) as $severity => $overrides) {
            $placeholders = implode(', ', array_fill(0, $overrides->count(), '?'));
            $cases[] = "when {$table}.id in ({$placeholders}) then {$severity}";
            array_push($bindings, ...$overrides->keys()->all());
        }

        foreach (ComponentStatusEnum::cases() as $status) {
            $cases[] = "when {$table}.status = ? then {$status->severity()}";
            $bindings[] = $status->value;
        }

        return $query->orderByRaw('case '.implode(' ', $cases).' else 0 end '.$direction, $bindings);
    }
}
