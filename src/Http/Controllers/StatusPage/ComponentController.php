<?php

namespace Cachet\Http\Controllers\StatusPage;

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Models\Component;
use Cachet\Models\ComponentUptimeDay;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class ComponentController
{
    /**
     * How many days of uptime history the page shows.
     */
    private const DAYS = 90;

    /**
     * How many incidents and status changes the page lists.
     */
    private const RECENT_LIMIT = 20;

    /**
     * Show a component with its uptime history, incidents, maintenance and status changes.
     */
    public function show(int $component): View
    {
        $authenticated = auth()->check();

        $component = Component::query()
            ->onStatusPage($authenticated)
            ->with(['group', 'tags', 'unresolvedIncidents', 'activeMaintenance'])
            ->findOrFail($component);

        $from = CarbonImmutable::today()->subDays(self::DAYS - 1);
        $uptimeDays = $component->uptimeDays()->where('date', '>=', $from->toDateString())->get()
            ->keyBy(fn (ComponentUptimeDay $day): string => $day->date->toDateString());

        $available = $uptimeDays->sum('available_seconds');

        return view('cachet::status-page.component', [
            'record' => $component,
            'status' => $component->latest_status,
            'days' => collect($from->toPeriod(CarbonImmutable::today()))
                ->mapWithKeys(fn ($day): array => [$day->toDateString() => $this->dayStatus($uptimeDays->get($day->toDateString()))])
                ->all(),
            'uptime' => $available > 0 ? ($uptimeDays->sum('operational_seconds') / $available) * 100 : null,
            'incidents' => $component->incidents()
                ->viewableBy($authenticated)
                ->latest('incidents.created_at')
                ->limit(self::RECENT_LIMIT)
                ->get(),
            'schedules' => $component->schedules()
                ->published()
                ->incomplete()
                ->orderBy('scheduled_at')
                ->limit(self::RECENT_LIMIT)
                ->get(),
            'statusChanges' => $component->statusChanges()
                ->latest('created_at')
                ->latest('id')
                ->limit(self::RECENT_LIMIT)
                ->get(),
        ]);
    }

    private function dayStatus(?ComponentUptimeDay $day): ?ComponentStatusEnum
    {
        return $day === null || $day->available_seconds === 0 ? null : $day->status;
    }
}
