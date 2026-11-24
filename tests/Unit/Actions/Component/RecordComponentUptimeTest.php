<?php

use Cachet\Actions\Component\CalculateComponentUptime;
use Cachet\Actions\Component\RecordComponentUptime;
use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\IncidentStatusEnum;
use Cachet\Enums\ResourceVisibilityEnum;
use Cachet\Models\Component;
use Cachet\Models\ComponentStatusChange;
use Cachet\Models\ComponentUptimeDay;
use Cachet\Models\Incident;
use Cachet\Models\Schedule;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-24 12:00:00');
});

/**
 * Calculate the uptime of a freshly loaded component over the given window.
 *
 * @return array<string, array{status: ComponentStatusEnum, available_seconds: int, operational_seconds: int}>
 */
function uptimeOf(Component $component, string $from, string $to): array
{
    $from = Carbon::parse($from);
    $to = Carbon::parse($to);

    $component = Component::query()->with(CalculateComponentUptime::with($from, $to))->findOrFail($component->id);

    return app(CalculateComponentUptime::class)->handle($component, $from, $to);
}

it('counts a whole operational day as available and operational', function () {
    $component = Component::factory()->create(['status' => ComponentStatusEnum::operational, 'created_at' => '2026-11-01']);

    expect(uptimeOf($component, '2026-11-22', '2026-11-23 23:59:59')['2026-11-22'])->toBe([
        'status' => ComponentStatusEnum::operational,
        'available_seconds' => 86400,
        'operational_seconds' => 86400,
    ]);
});

it('splits a day at each status change', function () {
    $component = Component::factory()->create(['status' => ComponentStatusEnum::operational, 'created_at' => '2026-11-01']);
    ComponentStatusChange::query()->insert([
        ['component_id' => $component->id, 'old_status' => 1, 'new_status' => 4, 'source' => 0, 'created_at' => '2026-11-22 09:30:00', 'updated_at' => now()],
        ['component_id' => $component->id, 'old_status' => 4, 'new_status' => 1, 'source' => 0, 'created_at' => '2026-11-22 10:00:00', 'updated_at' => now()],
    ]);

    $days = uptimeOf($component, '2026-11-21', '2026-11-23 23:59:59');

    expect($days['2026-11-22'])->toBe([
        'status' => ComponentStatusEnum::major_outage,
        'available_seconds' => 86400,
        'operational_seconds' => 86400 - 1800,
    ])->and($days['2026-11-21']['operational_seconds'])->toBe(86400);
});

it('uses the old status of the first change before it happened', function () {
    $component = Component::factory()->create(['status' => ComponentStatusEnum::operational, 'created_at' => '2026-11-01']);
    ComponentStatusChange::query()->insert([
        'component_id' => $component->id, 'old_status' => 3, 'new_status' => 1, 'source' => 0, 'created_at' => '2026-11-22 12:00:00', 'updated_at' => now(),
    ]);

    expect(uptimeOf($component, '2026-11-22', '2026-11-23 00:00:00')['2026-11-22'])
        ->status->toBe(ComponentStatusEnum::partial_outage)
        ->operational_seconds->toBe(43200);
});

it('does not count time before the component existed', function () {
    $component = Component::factory()->create(['status' => ComponentStatusEnum::operational, 'created_at' => '2026-11-22 18:00:00']);

    $days = uptimeOf($component, '2026-11-21', '2026-11-23 00:00:00');

    expect($days['2026-11-21'])->toBe(['status' => ComponentStatusEnum::unknown, 'available_seconds' => 0, 'operational_seconds' => 0])
        ->and($days['2026-11-22']['available_seconds'])->toBe(6 * 3600);
});

it('applies public incident impacts until the incident is fixed', function () {
    $component = Component::factory()->create(['status' => ComponentStatusEnum::operational, 'created_at' => '2026-11-01']);
    $incident = Incident::factory()->create([
        'status' => IncidentStatusEnum::fixed,
        'visible' => ResourceVisibilityEnum::guest,
        'occurred_at' => '2026-11-22 08:00:00',
    ]);
    $incident->components()->attach($component->id, ['component_status' => ComponentStatusEnum::partial_outage]);
    $incident->updates()->create(['status' => IncidentStatusEnum::fixed, 'message' => 'Fixed.'])
        ->forceFill(['created_at' => '2026-11-22 09:00:00'])
        ->save();

    expect(uptimeOf($component, '2026-11-22', '2026-11-23 00:00:00')['2026-11-22'])
        ->status->toBe(ComponentStatusEnum::partial_outage)
        ->operational_seconds->toBe(86400 - 3600);
});

it('ignores incidents hidden from the public', function () {
    $component = Component::factory()->create(['status' => ComponentStatusEnum::operational, 'created_at' => '2026-11-01']);
    $incident = Incident::factory()->create([
        'status' => IncidentStatusEnum::investigating,
        'visible' => ResourceVisibilityEnum::authenticated,
        'occurred_at' => '2026-11-22 08:00:00',
    ]);
    $incident->components()->attach($component->id, ['component_status' => ComponentStatusEnum::major_outage]);

    expect(uptimeOf($component, '2026-11-22', '2026-11-23 00:00:00')['2026-11-22']['status'])->toBe(ComponentStatusEnum::operational);
});

it('replaces the baseline while maintenance is in progress', function () {
    $component = Component::factory()->create(['status' => ComponentStatusEnum::major_outage, 'created_at' => '2026-11-01']);
    $schedule = Schedule::factory()->create([
        'scheduled_at' => '2026-11-22 00:00:00',
        'completed_at' => '2026-11-23 00:00:00',
        'published_at' => '2026-11-01',
    ]);
    $schedule->components()->attach($component->id, ['component_status' => ComponentStatusEnum::under_maintenance]);

    expect(uptimeOf($component, '2026-11-22', '2026-11-23 00:00:00')['2026-11-22'])
        ->status->toBe(ComponentStatusEnum::under_maintenance)
        ->operational_seconds->toBe(0);
});

it('records the last days of every component and refreshes them on the next run', function () {
    $first = Component::factory()->create(['status' => ComponentStatusEnum::operational, 'created_at' => '2026-11-01']);
    $second = Component::factory()->create(['status' => ComponentStatusEnum::major_outage, 'created_at' => '2026-11-01']);

    expect(app(RecordComponentUptime::class)->handle(3))->toBe(6)
        ->and(ComponentUptimeDay::query()->count())->toBe(6);

    $today = fn (Component $component) => ComponentUptimeDay::query()->whereBelongsTo($component)->whereDate('date', '2026-11-24')->sole();

    expect($today($first))
        ->status->toBe(ComponentStatusEnum::operational)
        ->operational_seconds->toBe(12 * 3600)
        ->and($today($second))
        ->status->toBe(ComponentStatusEnum::major_outage)
        ->severity->toBe(ComponentStatusEnum::major_outage->severity());

    Carbon::setTestNow('2026-11-24 18:00:00');
    app(RecordComponentUptime::class)->handle(1);

    expect(ComponentUptimeDay::query()->count())->toBe(6)
        ->and($today($first)->operational_seconds)->toBe(18 * 3600);
});

it('records uptime from the scheduled command', function () {
    Component::factory()->create(['created_at' => '2026-11-01']);

    $this->artisan('cachet:record-uptime', ['--days' => 90])
        ->expectsOutputToContain('Recorded 90 component uptime days.')
        ->assertSuccessful();

    $this->artisan('cachet:record-uptime', ['--days' => 0])->assertFailed();
});

it('prunes uptime days past the retention', function () {
    $component = Component::factory()->create();
    ComponentUptimeDay::query()->insert([
        ['component_id' => $component->id, 'date' => '2025-01-01', 'status' => 1, 'severity' => 0, 'created_at' => now(), 'updated_at' => now()],
        ['component_id' => $component->id, 'date' => '2026-11-01', 'status' => 1, 'severity' => 0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->artisan('model:prune', ['--model' => [ComponentUptimeDay::class]])->assertSuccessful();

    expect(ComponentUptimeDay::query()->pluck('date')->map->toDateString()->all())->toBe(['2026-11-01']);
});
