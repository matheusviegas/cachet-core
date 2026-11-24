<?php

use Cachet\Enums\IncidentStatusEnum;
use Cachet\Filament\Resources\Incidents\Pages\EditIncident;
use Cachet\Filament\Resources\Updates\RelationManagers\UpdatesRelationManager;
use Cachet\Models\Incident;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));

    actingAs(User::factory()->create(['is_admin' => true]));
});

it('keeps the status of a recorded update when the edit form is saved afterwards', function () {
    $incident = Incident::factory()->create(['status' => IncidentStatusEnum::investigating]);

    livewire(EditIncident::class, ['record' => $incident->getKey()])
        ->callAction('add-update', data: ['message' => 'Resolved.', 'status' => IncidentStatusEnum::fixed->value])
        ->assertHasNoActionErrors()
        ->call('save')
        ->assertHasNoFormErrors();

    expect($incident->fresh()->status)->toBe(IncidentStatusEnum::fixed);
});

it('shows the status of a recorded update in the edit form', function () {
    $incident = Incident::factory()->create(['status' => IncidentStatusEnum::investigating]);

    livewire(EditIncident::class, ['record' => $incident->getKey()])
        ->callAction('add-update', data: ['message' => 'Resolved.', 'status' => IncidentStatusEnum::fixed->value])
        ->assertSchemaStateSet(['status' => IncidentStatusEnum::fixed]);
});

it('announces status changes made from the updates tab', function () {
    $incident = Incident::factory()->create(['status' => IncidentStatusEnum::investigating]);
    $update = $incident->updates()->create(['message' => 'Looking.', 'status' => IncidentStatusEnum::investigating]);

    livewire(UpdatesRelationManager::class, ['ownerRecord' => $incident, 'pageClass' => EditIncident::class])
        ->callAction(TestAction::make('edit')->table($update), data: ['status' => IncidentStatusEnum::fixed])
        ->assertDispatched('incident-status-synced');
});

it('refreshes the edit form when the updates tab changes the status', function () {
    $incident = Incident::factory()->create(['status' => IncidentStatusEnum::investigating]);

    $page = livewire(EditIncident::class, ['record' => $incident->getKey()]);

    $incident->update(['status' => IncidentStatusEnum::fixed]);

    $page->dispatch('incident-status-synced')
        ->assertSchemaStateSet(['status' => IncidentStatusEnum::fixed])
        ->call('save');

    expect($incident->fresh()->status)->toBe(IncidentStatusEnum::fixed);
});
