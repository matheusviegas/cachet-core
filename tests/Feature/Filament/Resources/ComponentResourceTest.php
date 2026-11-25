<?php

namespace Tests\Feature\Filament\Resources;

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Filament\Resources\Components\Pages\CreateComponent;
use Cachet\Filament\Resources\Components\Pages\EditComponent;
use Cachet\Filament\Resources\Components\Pages\ListComponents;
use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Filament\Facades\Filament;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));

    actingAs(User::factory()->administrator()->create());
});

it('groups component availability controls with concise labels', function () {
    livewire(CreateComponent::class)
        ->assertSchemaComponentVisible('availability')
        ->assertSee(__('cachet::component.form.availability_section_title'))
        ->assertSee(__('cachet::component.form.enabled_label'))
        ->assertSee(__('cachet::component.form.checked_label'))
        ->assertSee(__('cachet::component.form.checked_helper'));
});

it('stores meta as key/value pairs when creating a component from the dashboard', function () {
    livewire(CreateComponent::class)
        ->fillForm([
            'name' => 'Dashboard Component',
            'status' => ComponentStatusEnum::operational,
            'enabled' => true,
        ])
        ->set('data.meta', [
            ['key' => 'region', 'value' => 'eu-west-1'],
            ['key' => 'awake', 'value' => '1'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $component = Component::query()->firstWhere('name', 'Dashboard Component');

    expect($component->metaValues())->toBe([
        'region' => 'eu-west-1',
        'awake' => '1',
    ]);
});

it('updates meta when editing a component from the dashboard', function () {
    $component = Component::factory()->create([
        'description' => 'A short description.',
    ]);
    $component->syncMeta(['region' => 'eu-west-1', 'stale' => 'yes']);

    livewire(EditComponent::class, ['record' => $component->getKey()])
        ->assertFormSet(['meta' => ['region' => 'eu-west-1', 'stale' => 'yes']])
        ->fillForm([
            'meta' => ['region' => 'us-east-1'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($component->fresh()->metaValues())->toBe([
        'region' => 'us-east-1',
    ]);
});

it('is filterable through the API after being written from the dashboard', function () {
    livewire(CreateComponent::class)
        ->fillForm([
            'name' => 'Filterable Component',
            'status' => ComponentStatusEnum::operational,
            'enabled' => true,
            'meta' => ['region' => 'eu-west-1'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $response = $this->getJson('/status/api/components?include=meta&filter[meta][region]=eu-west-1');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.attributes.meta', ['region' => 'eu-west-1']);
});

it('filters components by status, group, tag and enabled state', function () {
    $group = ComponentGroup::factory()->create();
    $down = Component::factory()->for($group, 'group')->create(['status' => ComponentStatusEnum::major_outage]);
    $up = Component::factory()->create(['status' => ComponentStatusEnum::operational]);
    $disabled = Component::factory()->create(['status' => ComponentStatusEnum::operational, 'enabled' => false]);
    $down->syncTags(['finance']);

    livewire(ListComponents::class)
        ->filterTable('status', [ComponentStatusEnum::major_outage->value])
        ->assertCanSeeTableRecords([$down])
        ->assertCanNotSeeTableRecords([$up, $disabled])
        ->resetTableFilters()
        ->filterTable('group', $group->id)
        ->assertCanSeeTableRecords([$down])
        ->assertCanNotSeeTableRecords([$up, $disabled])
        ->resetTableFilters()
        ->filterTable('tags', [$down->tags->first()->id])
        ->assertCanSeeTableRecords([$down])
        ->assertCanNotSeeTableRecords([$up, $disabled])
        ->resetTableFilters()
        ->filterTable('enabled', false)
        ->assertCanSeeTableRecords([$disabled])
        ->assertCanNotSeeTableRecords([$down, $up]);
});

it('does not offer to list every component on one page', function () {
    livewire(ListComponents::class)
        ->assertSuccessful();

    expect(livewire(ListComponents::class)->instance()->getTable()->getPaginationPageOptions())->toBe([10, 25, 50, 100]);
});
