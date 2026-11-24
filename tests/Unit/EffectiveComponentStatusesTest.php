<?php

use Cachet\EffectiveComponentStatuses;
use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\IncidentStatusEnum;
use Cachet\Enums\ResourceVisibilityEnum;
use Cachet\Models\Component;
use Cachet\Models\Incident;
use Cachet\Models\Schedule;

/**
 * Attach the component to a public, unresolved incident with the given impact.
 */
function impactWithIncident(Component $component, ComponentStatusEnum $impact): Incident
{
    $incident = Incident::factory()->create([
        'status' => IncidentStatusEnum::investigating,
        'visible' => ResourceVisibilityEnum::guest,
    ]);
    $incident->components()->attach($component->id, ['component_status' => $impact]);

    return $incident;
}

function effectiveStatuses(): EffectiveComponentStatuses
{
    app()->forgetScopedInstances();

    return app(EffectiveComponentStatuses::class);
}

it('only tracks components whose effective status differs from the baseline', function () {
    $raised = Component::factory()->create(['status' => ComponentStatusEnum::operational]);
    $same = Component::factory()->create(['status' => ComponentStatusEnum::major_outage]);
    Component::factory()->create(['status' => ComponentStatusEnum::operational]);
    impactWithIncident($raised, ComponentStatusEnum::partial_outage);
    impactWithIncident($same, ComponentStatusEnum::partial_outage);

    expect(effectiveStatuses()->overrides()->all())->toBe([$raised->id => ComponentStatusEnum::partial_outage]);
});

it('replaces the baseline while maintenance is in progress', function () {
    $component = Component::factory()->create(['status' => ComponentStatusEnum::major_outage]);
    $schedule = Schedule::factory()->inProgress()->create();
    $schedule->components()->attach($component->id, ['component_status' => ComponentStatusEnum::under_maintenance]);

    expect(effectiveStatuses()->of($component))->toBe(ComponentStatusEnum::under_maintenance);
});

it('filters by effective status', function () {
    $raised = Component::factory()->create(['status' => ComponentStatusEnum::operational]);
    $down = Component::factory()->create(['status' => ComponentStatusEnum::major_outage]);
    $up = Component::factory()->create(['status' => ComponentStatusEnum::operational]);
    impactWithIncident($raised, ComponentStatusEnum::major_outage);

    $ids = fn (array $statuses): array => effectiveStatuses()
        ->whereStatusIn(Component::query(), $statuses)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($ids([ComponentStatusEnum::major_outage]))->toBe([$raised->id, $down->id])
        ->and($ids([ComponentStatusEnum::operational]))->toBe([$up->id]);
});

it('counts components by effective status', function () {
    $raised = Component::factory()->create(['status' => ComponentStatusEnum::operational]);
    Component::factory()->create(['status' => ComponentStatusEnum::operational]);
    Component::factory()->create(['status' => ComponentStatusEnum::performance_issues]);
    impactWithIncident($raised, ComponentStatusEnum::major_outage);

    expect(effectiveStatuses()->count(Component::query()))->toBe([
        'operational' => 1,
        'performance_issues' => 1,
        'partial_outage' => 0,
        'major_outage' => 1,
        'unknown' => 0,
        'under_maintenance' => 0,
    ]);
});

it('only counts the components matched by the query', function () {
    $raised = Component::factory()->create(['status' => ComponentStatusEnum::operational, 'enabled' => false]);
    Component::factory()->create(['status' => ComponentStatusEnum::operational]);
    impactWithIncident($raised, ComponentStatusEnum::major_outage);

    expect(effectiveStatuses()->count(Component::query()->enabled()))
        ->toMatchArray(['operational' => 1, 'major_outage' => 0]);
});

it('orders components by effective severity', function () {
    $performance = Component::factory()->create(['name' => 'performance', 'status' => ComponentStatusEnum::performance_issues]);
    $raised = Component::factory()->create(['name' => 'raised', 'status' => ComponentStatusEnum::operational]);
    $operational = Component::factory()->create(['name' => 'operational', 'status' => ComponentStatusEnum::operational]);
    $partial = Component::factory()->create(['name' => 'partial', 'status' => ComponentStatusEnum::partial_outage]);
    impactWithIncident($raised, ComponentStatusEnum::major_outage);

    expect(effectiveStatuses()->orderBySeverity(Component::query())->pluck('name')->all())
        ->toBe(['raised', 'partial', 'performance', 'operational'])
        ->and(effectiveStatuses()->orderBySeverity(Component::query(), 'asc')->pluck('name')->first())
        ->toBe('operational');
});
