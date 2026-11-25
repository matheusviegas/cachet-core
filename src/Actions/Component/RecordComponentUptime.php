<?php

namespace Cachet\Actions\Component;

use Cachet\Models\Component;
use Cachet\Models\ComponentUptimeDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recalculates and stores the daily uptime of components.
 *
 * Components are processed in chunks so memory stays flat with thousands of
 * components. Rows are upserted, so running it again for the same days only
 * refreshes them.
 */
class RecordComponentUptime
{
    /**
     * How many components are loaded with their history at once.
     */
    private const CHUNK_SIZE = 200;

    public function __construct(private readonly CalculateComponentUptime $calculate) {}

    /**
     * Record the uptime of the queried components for the last given days, today included.
     *
     * @param  Builder<Component>|null  $components
     * @return int The number of day rows written.
     */
    public function handle(int $days = 2, ?Builder $components = null, ?CarbonInterface $now = null): int
    {
        $to = CarbonImmutable::instance($now ?? now());
        $from = $to->subDays(max(1, $days) - 1)->startOfDay();
        $written = 0;

        ($components ?? Component::query())
            ->with(CalculateComponentUptime::with($from, $to))
            ->chunkById(self::CHUNK_SIZE, function (Collection $chunk) use ($from, $to, &$written): void {
                $rows = $chunk->flatMap(fn (Component $component): array => $this->rows($component, $from, $to))->all();

                ComponentUptimeDay::query()->upsert(
                    $rows,
                    ['component_id', 'date'],
                    ['status', 'severity', 'available_seconds', 'operational_seconds', 'updated_at'],
                );

                $written += count($rows);
            });

        return $written;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(Component $component, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $now = now();
        $rows = [];

        foreach ($this->calculate->handle($component, $from, $to) as $date => $day) {
            $rows[] = [
                'component_id' => $component->getKey(),
                'date' => $date,
                'status' => $day['status']->value,
                'severity' => $day['status']->severity(),
                'available_seconds' => $day['available_seconds'],
                'operational_seconds' => $day['operational_seconds'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}
