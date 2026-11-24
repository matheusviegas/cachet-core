<?php

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\IncidentStatusEnum;
use Cachet\Enums\ResourceVisibilityEnum;
use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Cachet\Models\ComponentStatusChange;
use Cachet\Models\ComponentUptimeDay;
use Cachet\Models\Incident;
use Cachet\Models\Schedule;
use Illuminate\Support\Carbon;
use Workbench\App\User;

beforeEach(function () {
    Carbon::setTestNow('2026-11-24 12:00:00');
});

it('shows the component with its group, status and description', function () {
    $group = ComponentGroup::factory()->create(['name' => 'Finance', 'visible' => ResourceVisibilityEnum::guest]);
    $component = Component::factory()->for($group, 'group')->create([
        'name' => 'Invoice robot',
        'description' => 'Issues **invoices**.',
        'status' => ComponentStatusEnum::partial_outage,
    ]);

    $this->get(route('cachet.status-page.component', $component))
        ->assertOk()
        ->assertSee('Invoice robot')
        ->assertSee('Finance')
        ->assertSee(ComponentStatusEnum::partial_outage->getLabel())
        ->assertSee('<strong>invoices</strong>', escape: false);
});

it('shows the effective status raised by an incident', function () {
    $component = Component::factory()->create(['status' => ComponentStatusEnum::operational]);
    $incident = Incident::factory()->create(['name' => 'Robots failing', 'status' => IncidentStatusEnum::investigating, 'visible' => ResourceVisibilityEnum::guest]);
    $incident->components()->attach($component->id, ['component_status' => ComponentStatusEnum::major_outage]);

    $this->get(route('cachet.status-page.component', $component))
        ->assertSeeInOrder([ComponentStatusEnum::major_outage->getLabel(), 'Robots failing'])
        ->assertSee(route('cachet.status-page.incident', $incident));
});

it('lists only the incidents the viewer may read', function () {
    $component = Component::factory()->create();
    $public = Incident::factory()->create(['name' => 'Public incident', 'visible' => ResourceVisibilityEnum::guest]);
    $internal = Incident::factory()->create(['name' => 'Internal incident', 'visible' => ResourceVisibilityEnum::authenticated]);
    $component->incidents()->attach([$public->id, $internal->id], ['component_status' => ComponentStatusEnum::major_outage]);

    $this->get(route('cachet.status-page.component', $component))
        ->assertSee('Public incident')
        ->assertDontSee('Internal incident');

    $this->actingAs(User::factory()->create())
        ->get(route('cachet.status-page.component', $component))
        ->assertSee('Internal incident');
});

it('lists upcoming maintenance and recent status changes', function () {
    $component = Component::factory()->create(['status' => ComponentStatusEnum::operational]);
    $schedule = Schedule::factory()->inTheFuture()->create(['name' => 'Database upgrade', 'published_at' => now()->subDay()]);
    $schedule->components()->attach($component->id, ['component_status' => ComponentStatusEnum::under_maintenance]);
    ComponentStatusChange::query()->insert([
        'component_id' => $component->id, 'old_status' => 1, 'new_status' => 4, 'source' => 0, 'created_at' => now()->subHour(), 'updated_at' => now(),
    ]);

    $this->get(route('cachet.status-page.component', $component))
        ->assertSee('Database upgrade')
        ->assertSeeInOrder([ComponentStatusEnum::operational->getLabel(), ComponentStatusEnum::major_outage->getLabel()]);
});

it('shows the recorded uptime history', function () {
    $component = Component::factory()->create();
    ComponentUptimeDay::query()->insert([
        ['component_id' => $component->id, 'date' => '2026-11-23', 'status' => 4, 'severity' => 5, 'available_seconds' => 86400, 'operational_seconds' => 43200, 'created_at' => now(), 'updated_at' => now()],
        ['component_id' => $component->id, 'date' => '2026-11-24', 'status' => 1, 'severity' => 0, 'available_seconds' => 43200, 'operational_seconds' => 43200, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $html = $this->get(route('cachet.status-page.component', $component))->getContent();

    expect(substr_count($html, 'data-slot="day"'))->toBe(90)
        ->and($html)->toContain('data-date="2026-11-23" data-status="major_outage"')
        ->toContain('data-date="2026-11-24" data-status="operational"')
        ->toContain('data-date="2026-11-22" data-status="none"')
        ->toContain(__('cachet::system_status.uptime', ['uptime' => '66.67']));
});

it('hides components the viewer may not see', function (array $attributes, ?ResourceVisibilityEnum $groupVisibility, bool $authenticated) {
    $component = Component::factory()
        ->when($groupVisibility, fn ($factory) => $factory->for(ComponentGroup::factory()->state(['visible' => $groupVisibility]), 'group'))
        ->create($attributes);

    if ($authenticated) {
        $this->actingAs(User::factory()->create());
    }

    $this->get(route('cachet.status-page.component', $component))->assertNotFound();
})->with([
    'disabled' => [['enabled' => false], null, false],
    'in a hidden group' => [[], ResourceVisibilityEnum::hidden, true],
    'in an internal group, for guests' => [[], ResourceVisibilityEnum::authenticated, false],
]);

it('shows components of internal groups to signed in users', function () {
    $component = Component::factory()->for(ComponentGroup::factory()->state(['visible' => ResourceVisibilityEnum::authenticated]), 'group')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('cachet.status-page.component', $component))
        ->assertOk();
});

it('returns not found for unknown components', function () {
    $this->get(route('cachet.status-page.component', 999999))->assertNotFound();
    $this->get(route('cachet.status-page').'/components/abc')->assertNotFound();
});
