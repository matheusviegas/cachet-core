<?php

namespace Tests\Feature\Filament\Widgets;

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\ComponentStatusSourceEnum;
use Cachet\Enums\IncidentStatusEnum;
use Cachet\Enums\ResourceVisibilityEnum;
use Cachet\Filament\Widgets\Components;
use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Cachet\Models\Incident;
use Filament\Facades\Filament;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));

    actingAs(User::factory()->create(['is_admin' => true]));
});

it('only lists enabled components', function () {
    $enabled = Component::factory()->create(['enabled' => true]);
    $disabled = Component::factory()->create(['enabled' => false]);

    livewire(Components::class)
        ->assertCanSeeTableRecords([$enabled])
        ->assertCanNotSeeTableRecords([$disabled]);
});

it('lists components of groups of every visibility', function (ResourceVisibilityEnum $visibility) {
    $group = ComponentGroup::factory()->create(['name' => 'Robots', 'visible' => $visibility]);
    $component = Component::factory()->for($group, 'group')->create();

    livewire(Components::class)
        ->assertCanSeeTableRecords([$component])
        ->assertTableColumnStateSet('group.name', 'Robots', $component);
})->with(ResourceVisibilityEnum::cases());

it('lists the most severe components first', function () {
    $operational = Component::factory()->create(['name' => 'A', 'status' => ComponentStatusEnum::operational]);
    $partial = Component::factory()->create(['name' => 'B', 'status' => ComponentStatusEnum::partial_outage]);
    $impacted = Component::factory()->create(['name' => 'C', 'status' => ComponentStatusEnum::operational]);
    $incident = Incident::factory()->create(['status' => IncidentStatusEnum::investigating, 'visible' => ResourceVisibilityEnum::guest]);
    $incident->components()->attach($impacted->id, ['component_status' => ComponentStatusEnum::major_outage]);

    livewire(Components::class)
        ->assertCanSeeTableRecords([$impacted, $partial, $operational], inOrder: true)
        ->assertTableColumnStateSet('effective_status', ComponentStatusEnum::major_outage, $impacted);
});

it('paginates instead of rendering every component', function () {
    Component::factory()->count(15)->create();

    $widget = livewire(Components::class)->assertCountTableRecords(15);

    expect($widget->instance()->getTableRecords())->toHaveCount(10);
});

it('searches components by name', function () {
    $invoices = Component::factory()->create(['name' => 'Invoice robot']);
    $payroll = Component::factory()->create(['name' => 'Payroll robot']);

    livewire(Components::class)
        ->searchTable('invoice')
        ->assertCanSeeTableRecords([$invoices])
        ->assertCanNotSeeTableRecords([$payroll]);
});

it('filters components by effective status and group', function () {
    $group = ComponentGroup::factory()->create();
    $down = Component::factory()->for($group, 'group')->create(['status' => ComponentStatusEnum::major_outage]);
    $up = Component::factory()->create(['status' => ComponentStatusEnum::operational]);

    livewire(Components::class)
        ->filterTable('status', [ComponentStatusEnum::major_outage->value])
        ->assertCanSeeTableRecords([$down])
        ->assertCanNotSeeTableRecords([$up])
        ->resetTableFilters()
        ->filterTable('group', $group->id)
        ->assertCanSeeTableRecords([$down])
        ->assertCanNotSeeTableRecords([$up]);
});

it('changes a component status and records the change', function () {
    $component = Component::factory()->create(['status' => ComponentStatusEnum::operational]);

    livewire(Components::class)
        ->call('updateTableColumnState', 'status', (string) $component->getKey(), ComponentStatusEnum::major_outage->value);

    expect($component->fresh()->status)->toBe(ComponentStatusEnum::major_outage)
        ->and($component->statusChanges()->sole())
        ->new_status->toBe(ComponentStatusEnum::major_outage)
        ->source->toBe(ComponentStatusSourceEnum::Manual);
});
