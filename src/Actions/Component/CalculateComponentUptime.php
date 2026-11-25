<?php

namespace Cachet\Actions\Component;

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\IncidentStatusEnum;
use Cachet\Models\Component;
use Cachet\Models\Incident;
use Cachet\Models\Schedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Splits a component's effective status history into daily uptime figures.
 *
 * The window is cut at every status change, incident and maintenance
 * boundary and at each midnight. Each slice takes the effective status at
 * its start, so a day's figures only count the time it was actually in each
 * status. Time before the component existed, or in an unknown status, is not
 * available time.
 *
 *   00:00 ── operational ── 09:30 ── major outage ── 10:00 ── operational ── 24:00
 *   => available 86400 s, operational 84600 s, worst status major outage
 *
 * Maintenance in progress replaces the baseline and incidents raise it, as on
 * the status page. The baseline before the first change in the window is that
 * change's old status. The component must be loaded with the history the
 * window needs, see with().
 */
class CalculateComponentUptime
{
    /**
     * The relations to eager load for the given window.
     *
     * @return array<string, callable>
     */
    public static function with(CarbonInterface $from, CarbonInterface $to): array
    {
        return [
            'statusChanges' => fn ($query) => $query->whereBetween('created_at', [$from, $to])->orderBy('created_at'),
            'incidents' => fn ($query) => $query
                ->viewableBy(false)
                ->where(fn ($query) => $query
                    ->whereIn('incidents.status', IncidentStatusEnum::unresolved())
                    ->orWhere('incidents.occurred_at', '>=', $from)
                    ->orWhere('incidents.created_at', '>=', $from)
                    ->orWhere('incidents.updated_at', '>=', $from))
                ->with('updates'),
            'schedules' => fn ($query) => $query
                ->published()
                ->where('scheduled_at', '<=', $to)
                ->where(fn ($query) => $query->whereNull('completed_at')->orWhere('completed_at', '>=', $from)),
        ];
    }

    /**
     * Calculate the uptime of each day in the window.
     *
     * @return array<string, array{status: ComponentStatusEnum, available_seconds: int, operational_seconds: int}>
     */
    public function handle(Component $component, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::instance($from)->startOfDay();
        $to = CarbonImmutable::instance($to);

        $days = [];

        foreach ($from->toPeriod($to->startOfDay()) as $day) {
            $days[$day->toDateString()] = [
                'status' => ComponentStatusEnum::unknown,
                'available_seconds' => 0,
                'operational_seconds' => 0,
            ];
        }

        $boundaries = $this->boundaries($component, $from, $to);

        for ($index = 0; $index < count($boundaries) - 1; $index++) {
            $start = $boundaries[$index];
            $seconds = (int) $start->diffInSeconds($boundaries[$index + 1]);
            $status = $this->statusAt($component, $start);

            if ($status === null || $status === ComponentStatusEnum::unknown || $seconds === 0) {
                continue;
            }

            $date = $start->toDateString();
            $day = $days[$date];

            $days[$date] = [
                'status' => $day['available_seconds'] === 0 || $status->severity() > $day['status']->severity() ? $status : $day['status'],
                'available_seconds' => $day['available_seconds'] + $seconds,
                'operational_seconds' => $day['operational_seconds'] + ($status === ComponentStatusEnum::operational ? $seconds : 0),
            ];
        }

        return $days;
    }

    /**
     * Every instant in the window where the effective status may change.
     *
     * @return list<CarbonImmutable>
     */
    private function boundaries(Component $component, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $instants = collect([$from, $to])
            ->merge(collect($from->toPeriod($to->startOfDay()))->map(fn (CarbonInterface $day) => $day->startOfDay()))
            ->push($component->created_at)
            ->merge($component->statusChanges->pluck('created_at'))
            ->merge($component->incidents->flatMap(fn (Incident $incident): array => [$incident->timestamp, $this->incidentEnd($incident, $to)]))
            ->merge($component->schedules->flatMap(fn (Schedule $schedule): array => [$schedule->scheduled_at, $schedule->completed_at]))
            ->filter(fn (?CarbonInterface $instant): bool => $instant !== null && $instant->betweenIncluded($from, $to))
            ->map(fn (CarbonInterface $instant): CarbonImmutable => CarbonImmutable::instance($instant))
            ->unique(fn (CarbonImmutable $instant): int => $instant->getTimestamp())
            ->sort()
            ->values();

        return $instants->all();
    }

    /**
     * The effective status of the component at the given instant.
     */
    private function statusAt(Component $component, CarbonInterface $time): ?ComponentStatusEnum
    {
        if ($component->created_at === null || $time->lt($component->created_at)) {
            return null;
        }

        $lastChange = $component->statusChanges->filter(fn ($change): bool => $change->created_at->lte($time))->last();
        $baseline = $lastChange->new_status
            ?? $component->statusChanges->first()->old_status
            ?? $component->status;

        $maintenance = $component->schedules
            ->filter(fn (Schedule $schedule): bool => $schedule->scheduled_at?->lte($time) === true
                && ($schedule->completed_at === null || $schedule->completed_at->gt($time)))
            ->map(fn (Schedule $schedule): ComponentStatusEnum => $this->impact($schedule->pivot->component_status) ?? ComponentStatusEnum::under_maintenance);

        $incidents = $component->incidents
            ->filter(fn (Incident $incident): bool => $incident->timestamp->lte($time) && $this->incidentEnd($incident, null)->gt($time))
            ->map(fn (Incident $incident): ?ComponentStatusEnum => $this->impact($incident->pivot->component_status))
            ->filter();

        return $this->worst(collect([$this->worst($maintenance) ?? $baseline])->merge($incidents));
    }

    /**
     * When the incident stopped affecting the component.
     */
    private function incidentEnd(Incident $incident, ?CarbonInterface $fallback): CarbonInterface
    {
        $fixedAt = $incident->updates
            ->where('status', IncidentStatusEnum::fixed)
            ->sortBy('created_at')
            ->first()
            ?->created_at;

        if ($fixedAt !== null) {
            return $fixedAt;
        }

        if ($incident->status === IncidentStatusEnum::fixed) {
            return $incident->updated_at ?? $incident->created_at;
        }

        return $fallback ?? CarbonImmutable::now()->addCentury();
    }

    private function impact(mixed $status): ?ComponentStatusEnum
    {
        if ($status instanceof ComponentStatusEnum || $status === null) {
            return $status;
        }

        return ComponentStatusEnum::tryFrom((int) $status);
    }

    /**
     * @param  Collection<int, covariant ComponentStatusEnum|null>  $statuses
     */
    private function worst(Collection $statuses): ?ComponentStatusEnum
    {
        return $statuses
            ->filter()
            ->sortByDesc(fn (ComponentStatusEnum $status): int => $status->severity())
            ->first();
    }
}
