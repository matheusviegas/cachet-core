<?php

namespace Tests\Feature\Filament\Resources;

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\IncidentStatusEnum;
use Cachet\Enums\ResourceVisibilityEnum;
use Cachet\Filament\Resources\Components\RelationManagers\ComponentsRelationManager;
use Cachet\Filament\Resources\Incidents\IncidentResource;
use Cachet\Filament\Resources\Incidents\Pages\CreateIncident;
use Cachet\Filament\Resources\Incidents\Pages\EditIncident;
use Cachet\Filament\Resources\Incidents\Pages\ListIncidents;
use Cachet\Filament\Resources\Updates\RelationManagers\UpdatesRelationManager;
use Cachet\Models\Component;
use Cachet\Models\Incident;
use Cachet\Models\Subscriber;
use Cachet\Notifications\NewIncidentNotification;
use Cachet\Settings\MailSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Notification;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));

    actingAs(User::factory()->administrator()->create());
});

it('counts every open incident in the navigation badge', function () {
    Incident::factory()->create(['status' => IncidentStatusEnum::investigating, 'visible' => ResourceVisibilityEnum::authenticated]);
    Incident::factory()->create(['status' => IncidentStatusEnum::identified, 'visible' => ResourceVisibilityEnum::hidden]);
    Incident::factory()->create(['status' => IncidentStatusEnum::fixed, 'visible' => ResourceVisibilityEnum::guest]);

    expect(IncidentResource::getNavigationBadge())->toBe('2')
        ->and(IncidentResource::getNavigationBadgeColor())->toBe('danger');
});

it('can create, reload, edit and clear a private note on a public incident', function () {
    livewire(CreateIncident::class)
        ->fillForm([
            'name' => 'Public outage',
            'status' => IncidentStatusEnum::investigating,
            'message' => 'We are investigating.',
            'visible' => ResourceVisibilityEnum::guest,
            'private_note' => 'Internal ticket SEC-482',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $incident = Incident::where('name', 'Public outage')->sole();

    expect($incident->private_note)->toBe('Internal ticket SEC-482')
        ->and($incident->visible)->toBe(ResourceVisibilityEnum::guest);

    livewire(EditIncident::class, ['record' => $incident->getKey()])
        ->assertFormSet(['private_note' => 'Internal ticket SEC-482'])
        ->fillForm(['private_note' => 'Escalated to the security team'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($incident->fresh()->private_note)->toBe('Escalated to the security team');

    livewire(EditIncident::class, ['record' => $incident->getKey()])
        ->assertFormSet(['private_note' => 'Escalated to the security team'])
        ->fillForm(['private_note' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($incident->fresh()->private_note)->toBeNull();
});

it('hides the notify subscribers toggle when subscriptions are not allowed', function () {
    $this->get(CreateIncident::getUrl())
        ->assertOk()
        ->assertDontSee(__('cachet::incident.form.notify_subscribers_label'));
});

it('shows the notify subscribers toggle when subscriptions are allowed', function () {
    $settings = app(MailSettings::class);
    $settings->allow_subscribers = true;
    $settings->save();

    $this->get(CreateIncident::getUrl())
        ->assertOk()
        ->assertSee(__('cachet::incident.form.notify_subscribers_label'));
});

it('defaults the incident update user to the incident reporter', function () {
    $reporter = User::factory()->administrator()->create();
    $incident = Incident::factory()->create(['user_id' => $reporter->id]);

    livewire(EditIncident::class, ['record' => $incident->getKey()])
        ->mountAction('add-update')
        ->assertActionDataSet(['user_id' => $reporter->id]);
});

it('notifies subscribers when creating an incident from the dashboard', function () {
    Notification::fake();

    $settings = app(MailSettings::class);
    $settings->allow_subscribers = true;
    $settings->save();

    $subscriber = Subscriber::factory()->verified()->create(['global' => true]);

    livewire(CreateIncident::class)
        ->fillForm([
            'name' => 'Dashboard Incident',
            'status' => IncidentStatusEnum::investigating,
            'message' => 'Something broke.',
            'visible' => ResourceVisibilityEnum::guest,
            'notifications' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($subscriber, NewIncidentNotification::class);
});

it('sorts incidents by their canonical status column', function () {
    $fixed = Incident::factory()->create(['status' => IncidentStatusEnum::fixed]);
    $investigating = Incident::factory()->create(['status' => IncidentStatusEnum::investigating]);

    livewire(ListIncidents::class)
        ->sortTable('latest_status')
        ->assertCanSeeTableRecords([$investigating, $fixed], inOrder: true);
});

it('prioritizes unresolved incidents and then the most recent occurrence', function () {
    $recentFixed = Incident::factory()->create([
        'status' => IncidentStatusEnum::fixed,
        'occurred_at' => now(),
    ]);
    $olderInvestigating = Incident::factory()->create([
        'status' => IncidentStatusEnum::investigating,
        'occurred_at' => now()->subDay(),
    ]);
    $recentIdentified = Incident::factory()->create([
        'status' => IncidentStatusEnum::identified,
        'occurred_at' => now()->subHour(),
    ]);

    livewire(ListIncidents::class)
        ->assertCanSeeTableRecords([
            $recentIdentified,
            $olderInvestigating,
            $recentFixed,
        ], inOrder: true);
});

it('shows the update message as the primary update information', function () {
    $incident = Incident::factory()->create();
    $update = $incident->updates()->create([
        'message' => 'We have identified the source of the outage.',
        'status' => IncidentStatusEnum::identified,
        'user_id' => auth()->id(),
    ]);

    livewire(UpdatesRelationManager::class, [
        'ownerRecord' => $incident,
        'pageClass' => EditIncident::class,
    ])
        ->assertTableColumnExists('message')
        ->assertTableColumnStateSet('message', $update->message, $update);
});

it('translates the update and component relation managers', function () {
    app()->setLocale('de');
    $incident = Incident::factory()->create();

    livewire(UpdatesRelationManager::class, [
        'ownerRecord' => $incident,
        'pageClass' => EditIncident::class,
    ])
        ->assertTableColumnExists('message', fn (TextColumn $column): bool => $column->getLabel() === 'Nachricht')
        ->assertTableColumnExists('created_at', fn (TextColumn $column): bool => $column->getLabel() === 'Erstellt am');

    expect(ComponentsRelationManager::getTitle($incident, EditIncident::class))->toBe('Komponenten');
});

it('translates the incident user field', function () {
    app()->setLocale('de');

    livewire(CreateIncident::class)
        ->assertFormFieldExists('user_id', fn (Select $field): bool => $field->getLabel() === 'Benutzer');
});

it('translates the update form', function () {
    app()->setLocale('de');
    $incident = Incident::factory()->create();
    $update = $incident->updates()->create([
        'message' => 'Cause identified.',
        'status' => IncidentStatusEnum::identified,
        'user_id' => auth()->id(),
    ]);

    livewire(UpdatesRelationManager::class, [
        'ownerRecord' => $incident,
        'pageClass' => EditIncident::class,
    ])
        ->mountAction(TestAction::make('edit')->table($update))
        ->assertFormFieldExists('message', fn (MarkdownEditor $field): bool => $field->getLabel() === 'Nachricht')
        ->assertFormFieldExists('user_id', fn (Select $field): bool => $field->getLabel() === 'Benutzer'
            && $field->getHint() === 'Benutzer, welcher den Vorfall gemeldet hat.');
});

it('syncs the incident status when an update is deleted from the updates tab', function () {
    $incident = Incident::factory()->create(['status' => IncidentStatusEnum::fixed]);
    $incident->updates()->create([
        'message' => 'Cause identified.',
        'status' => IncidentStatusEnum::identified,
        'user_id' => auth()->id(),
        'created_at' => now()->subHour(),
    ]);
    $fixed = $incident->updates()->create([
        'message' => 'All fixed.',
        'status' => IncidentStatusEnum::fixed,
        'user_id' => auth()->id(),
    ]);

    livewire(UpdatesRelationManager::class, [
        'ownerRecord' => $incident,
        'pageClass' => EditIncident::class,
    ])
        ->callAction(TestAction::make('delete')->table($fixed));

    expect($incident->fresh()->status)->toBe(IncidentStatusEnum::identified);
});

it('syncs the incident status when updates are bulk deleted from the updates tab', function () {
    $incident = Incident::factory()->create(['status' => IncidentStatusEnum::fixed]);
    $incident->updates()->create([
        'message' => 'Cause identified.',
        'status' => IncidentStatusEnum::identified,
        'user_id' => auth()->id(),
        'created_at' => now()->subHour(),
    ]);
    $fixed = $incident->updates()->create([
        'message' => 'All fixed.',
        'status' => IncidentStatusEnum::fixed,
        'user_id' => auth()->id(),
    ]);

    livewire(UpdatesRelationManager::class, [
        'ownerRecord' => $incident,
        'pageClass' => EditIncident::class,
    ])
        ->selectTableRecords([$fixed])
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect($incident->fresh()->status)->toBe(IncidentStatusEnum::identified);
});

it('syncs the incident status when an update status is edited from the updates tab', function () {
    $incident = Incident::factory()->create(['status' => IncidentStatusEnum::identified]);
    $update = $incident->updates()->create([
        'message' => 'Cause identified.',
        'status' => IncidentStatusEnum::identified,
        'user_id' => auth()->id(),
    ]);

    livewire(UpdatesRelationManager::class, [
        'ownerRecord' => $incident,
        'pageClass' => EditIncident::class,
    ])
        ->callAction(TestAction::make('edit')->table($update), data: [
            'status' => IncidentStatusEnum::fixed,
        ]);

    expect($incident->fresh()->status)->toBe(IncidentStatusEnum::fixed);
});

it('lists incidents without an occurred time by their created time', function () {
    $older = Incident::factory()->create([
        'status' => IncidentStatusEnum::investigating,
        'occurred_at' => now()->subDays(2),
    ]);
    $newer = Incident::factory()->create([
        'status' => IncidentStatusEnum::investigating,
        'occurred_at' => null,
    ]);

    livewire(ListIncidents::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
        ->assertTableColumnStateSet('occurred_at', $newer->created_at, $newer);
});

it('preloads the impacted components into the record update action', function () {
    $incident = Incident::factory()->create();
    $component = Component::factory()->create(['name' => 'API']);
    $incident->components()->attach($component->id, ['component_status' => ComponentStatusEnum::major_outage]);

    livewire(EditIncident::class, ['record' => $incident->getKey()])
        ->mountAction('add-update')
        ->assertActionDataSet(function (array $data) use ($component): array {
            // The closure's return value is only asserted when it is an array, so assert here.
            $rows = collect($data['components'] ?? [])->values();
            $status = $rows[0]['status'] ?? null;

            expect($rows)->toHaveCount(1)
                ->and($rows[0]['id'])->toBe($component->id)
                ->and($status instanceof ComponentStatusEnum ? $status : ComponentStatusEnum::tryFrom((int) $status))
                ->toBe(ComponentStatusEnum::major_outage);

            return [];
        });
});

it('updates the incident component statuses when recording an update', function () {
    $incident = Incident::factory()->create(['status' => IncidentStatusEnum::investigating]);
    $component = Component::factory()->create();
    $incident->components()->attach($component->id, ['component_status' => ComponentStatusEnum::major_outage]);

    livewire(EditIncident::class, ['record' => $incident->getKey()])
        ->callAction('add-update', data: [
            'message' => 'The API is recovering.',
            'status' => IncidentStatusEnum::identified,
            'components' => [
                ['id' => $component->id, 'status' => ComponentStatusEnum::performance_issues->value],
            ],
        ])
        ->assertHasNoActionErrors();

    expect($incident->components()->first()->pivot->component_status)
        ->toEqual(ComponentStatusEnum::performance_issues);
});

it('filters incidents by an affected component', function () {
    $component = Component::factory()->create();
    $affected = Incident::factory()->create();
    $unaffected = Incident::factory()->create();
    $affected->components()->attach($component->id, ['component_status' => ComponentStatusEnum::major_outage]);

    livewire(ListIncidents::class)
        ->filterTable('components', [$component->id])
        ->assertCanSeeTableRecords([$affected])
        ->assertCanNotSeeTableRecords([$unaffected]);
});
