<?php

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\IncidentStatusEnum;
use Cachet\Filament\Components\ComponentOptions;
use Cachet\Filament\Components\ComponentSelect;
use Cachet\Filament\Resources\Incidents\Pages\CreateIncident;
use Cachet\Filament\Resources\Incidents\Pages\EditIncident;
use Cachet\Filament\Resources\Incidents\RelationManagers\ComponentsRelationManager as IncidentComponentsRelationManager;
use Cachet\Filament\Resources\Schedules\Pages\CreateSchedule;
use Cachet\Filament\Resources\Schedules\Pages\EditSchedule;
use Cachet\Filament\Resources\Schedules\RelationManagers\ComponentsRelationManager as ScheduleComponentsRelationManager;
use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Cachet\Models\Incident;
use Cachet\Models\Schedule;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Livewire\Features\SupportTesting\Testable;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));

    actingAs(User::factory()->administrator()->create());
});

/**
 * Find the component pickers rendered in one of the Livewire component's schemas.
 *
 * @return Collection<int, ComponentSelect>
 */
function componentSelectsIn(Testable $livewire, string $schema = 'form'): Collection
{
    return collect($livewire->instance()->getSchema($schema)->getFlatComponents(withHidden: true))
        ->filter(fn (mixed $component): bool => $component instanceof ComponentSelect)
        ->values();
}

it('searches components by name and by group name', function () {
    $group = ComponentGroup::factory()->create(['name' => 'Finance']);
    $invoices = Component::factory()->for($group, 'group')->create(['name' => 'Invoice robot']);
    $payroll = Component::factory()->create(['name' => 'Payroll robot']);
    Component::factory()->create(['name' => 'Unrelated']);

    expect(ComponentOptions::search('invoice'))->toBe(['Finance' => [$invoices->id => 'Invoice robot']])
        ->and(ComponentOptions::search('FINANCE'))->toBe(['Finance' => [$invoices->id => 'Invoice robot']])
        ->and(ComponentOptions::search('robot'))->toBe([
            'Finance' => [$invoices->id => 'Invoice robot'],
            __('cachet::component.list.ungrouped') => [$payroll->id => 'Payroll robot'],
        ]);
});

it('caps the number of search results', function () {
    Component::factory()->count(ComponentOptions::SEARCH_LIMIT + 10)->sequence(fn ($sequence) => ['name' => "Robot {$sequence->index}"])->create();

    expect(collect(ComponentOptions::search('Robot'))->flatten())->toHaveCount(ComponentOptions::SEARCH_LIMIT);
});

it('leaves excluded components out of search results and labels', function () {
    $attached = Component::factory()->create(['name' => 'Attached robot']);
    $free = Component::factory()->create(['name' => 'Free robot']);

    expect(collect(ComponentOptions::search('robot', [$attached->id]))->flatten()->all())->toBe(['Free robot'])
        ->and(ComponentOptions::labels([$attached->id, $free->id, 999999], [$attached->id]))->toBe([$free->id => 'Free robot']);
});

it('uses a server side component search in the incident and schedule forms', function (string $page, string $repeater) {
    $component = Component::factory()->create(['name' => 'Invoice robot']);

    $selects = componentSelectsIn(livewire($page)->fillForm([$repeater => [['component_id' => null]]]));

    expect($selects)->toHaveCount(1)
        ->and($selects->first()->isNative())->toBeFalse()
        ->and($selects->first()->isSearchable())->toBeTrue()
        ->and($selects->first()->isPreloaded())->toBeFalse()
        ->and($selects->first()->getOptions())->toBe([])
        ->and($selects->first()->getSearchResults('invoice'))->toBe([__('cachet::component.list.ungrouped') => [$component->id => 'Invoice robot']]);
})->with([
    'incident' => [CreateIncident::class, 'incidentComponents'],
    'schedule' => [CreateSchedule::class, 'scheduleComponents'],
]);

it('uses a server side component search when recording an incident update', function () {
    $incident = Incident::factory()->create();
    Component::factory()->create(['name' => 'Invoice robot']);

    $livewire = livewire(EditIncident::class, ['record' => $incident->getKey()])
        ->mountAction('add-update')
        ->fillForm(['components' => [['id' => null]]]);

    $selects = componentSelectsIn($livewire, $livewire->instance()->getMountedActionSchemaName());

    expect($selects)->toHaveCount(1)
        ->and($selects->first()->getOptions())->toBe([])
        ->and(collect($selects->first()->getSearchResults('invoice'))->flatten()->all())->toBe(['Invoice robot']);
});

it('creates an incident with a component picked through the search', function () {
    $component = Component::factory()->create(['name' => 'Invoice robot']);

    livewire(CreateIncident::class)
        ->fillForm([
            'name' => 'Robots are failing',
            'message' => 'Investigating.',
            'status' => IncidentStatusEnum::investigating,
            'incidentComponents' => [['component_id' => $component->id, 'component_status' => ComponentStatusEnum::major_outage]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Incident::query()->latest('id')->first()->components()->pluck('components.id')->all())->toBe([$component->id]);
});

it('excludes already attached components from the attach action search', function (string $model, string $relationManager, string $page) {
    $owner = $model::factory()->create();
    $attached = Component::factory()->create(['name' => 'Attached robot']);
    $free = Component::factory()->create(['name' => 'Free robot']);
    $owner->components()->attach($attached->id, ['component_status' => ComponentStatusEnum::operational->value]);

    $livewire = livewire($relationManager, ['ownerRecord' => $owner, 'pageClass' => $page])
        ->mountAction(TestAction::make('attach')->table());

    $selects = componentSelectsIn($livewire, $livewire->instance()->getMountedActionSchemaName());

    expect(collect($selects->first()->getSearchResults('robot'))->flatten()->all())->toBe(['Free robot']);

    $livewire->callMountedAction(['recordId' => [$attached->id], 'component_status' => ComponentStatusEnum::major_outage->value])
        ->assertHasActionErrors(['recordId']);

    livewire($relationManager, ['ownerRecord' => $owner, 'pageClass' => $page])
        ->callAction(TestAction::make('attach')->table(), data: ['recordId' => [$free->id], 'component_status' => ComponentStatusEnum::major_outage->value])
        ->assertHasNoActionErrors();

    expect($owner->components()->pluck('components.id')->sort()->values()->all())->toBe([$attached->id, $free->id]);
})->with([
    'incident' => [Incident::class, IncidentComponentsRelationManager::class, EditIncident::class],
    'schedule' => [Schedule::class, ScheduleComponentsRelationManager::class, EditSchedule::class],
]);
