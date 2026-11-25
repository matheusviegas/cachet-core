<?php

use Cachet\Filament\Pages\Integrations\OhDear;
use Cachet\Filament\Pages\Settings\ManageCachet;
use Cachet\Filament\Pages\Settings\ManageCustomization;
use Cachet\Filament\Pages\Settings\ManageLocalization;
use Cachet\Filament\Pages\Settings\ManageNotifications;
use Cachet\Filament\Pages\Settings\ManageTheme;
use Cachet\Filament\Resources\ApiKeys\ApiKeyResource;
use Cachet\Filament\Resources\ComponentGroups\ComponentGroupResource;
use Cachet\Filament\Resources\Components\ComponentResource;
use Cachet\Filament\Resources\Groups\GroupResource;
use Cachet\Filament\Resources\Incidents\IncidentResource;
use Cachet\Filament\Resources\Incidents\Pages\EditIncident;
use Cachet\Filament\Resources\Incidents\Pages\ListIncidents;
use Cachet\Filament\Resources\Incidents\Pages\ViewIncident;
use Cachet\Filament\Resources\IncidentTemplates\IncidentTemplateResource;
use Cachet\Filament\Resources\Metrics\MetricResource;
use Cachet\Filament\Resources\Schedules\Pages\EditSchedule;
use Cachet\Filament\Resources\Schedules\ScheduleResource;
use Cachet\Filament\Resources\Subscribers\SubscriberResource;
use Cachet\Filament\Resources\Updates\RelationManagers\UpdatesRelationManager;
use Cachet\Filament\Resources\Users\UserResource;
use Cachet\Filament\Resources\WebhookSubscriptions\WebhookSubscriptionResource;
use Cachet\Filament\Widgets\Components;
use Cachet\Filament\Widgets\OpenIncidents;
use Cachet\Filament\Widgets\Overview;
use Cachet\Filament\Widgets\SystemHealth;
use Cachet\Filament\Widgets\UpcomingMaintenance;
use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Cachet\Models\Group;
use Cachet\Models\Incident;
use Cachet\Models\IncidentTemplate;
use Cachet\Models\Metric;
use Cachet\Models\Schedule;
use Cachet\Models\Subscriber;
use Cachet\Models\WebhookSubscription;
use Cachet\Permissions;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));
});

/**
 * Every dashboard resource with a factory for one of its records.
 *
 * @return array<string, array{0: class-string, 1: string, 2: Closure(): Model}>
 */
dataset('resources', [
    'components' => [ComponentResource::class, 'components', fn () => Component::factory()->create()],
    'component groups' => [ComponentGroupResource::class, 'component-groups', fn () => ComponentGroup::factory()->create()],
    'incidents' => [IncidentResource::class, 'incidents', fn () => Incident::factory()->create()],
    'incident templates' => [IncidentTemplateResource::class, 'incident-templates', fn () => IncidentTemplate::factory()->create()],
    'metrics' => [MetricResource::class, 'metrics', fn () => Metric::factory()->create()],
    'schedules' => [ScheduleResource::class, 'schedules', fn () => Schedule::factory()->create()],
    'subscribers' => [SubscriberResource::class, 'subscribers', fn () => Subscriber::factory()->create()],
    'webhooks' => [WebhookSubscriptionResource::class, 'webhooks', fn () => WebhookSubscription::factory()->create()],
    'users' => [UserResource::class, 'users', fn () => User::factory()->create()],
    'groups' => [GroupResource::class, 'groups', fn () => Group::factory()->create()],
]);

it('hides every resource from a user without groups', function (string $resource, string $permission, Closure $record) {
    $model = $record();

    actingAs(User::factory()->create());

    $this->get($resource::getUrl('index'))->assertForbidden();
    $this->get($resource::getUrl('create'))->assertForbidden();
    $this->get($resource::getUrl('view', ['record' => $model]))->assertForbidden();
    $this->get($resource::getUrl('edit', ['record' => $model]))->assertForbidden();
})->with('resources');

it('lets a viewer list and open records but not change them', function (string $resource, string $permission, Closure $record) {
    $model = $record();

    actingAs(User::factory()->withPermissions(["{$permission}.view"])->create());

    $this->get($resource::getUrl('index'))->assertOk();
    $this->get($resource::getUrl('view', ['record' => $model]))->assertOk();
    $this->get($resource::getUrl('create'))->assertForbidden();
    $this->get($resource::getUrl('edit', ['record' => $model]))->assertForbidden();

    expect($resource::canDelete($model))->toBeFalse();
})->with('resources');

it('lets a manager create and edit records but not delete them', function (string $resource, string $permission, Closure $record) {
    $model = $record();

    actingAs(User::factory()->withPermissions(["{$permission}.manage"])->create());

    $this->get($resource::getUrl('index'))->assertOk();
    $this->get($resource::getUrl('create'))->assertOk();
    $this->get($resource::getUrl('edit', ['record' => $model]))->assertOk();

    expect($resource::canDelete($model))->toBeFalse();
})->with('resources');

it('lets a user with the delete permission delete records', function (string $resource, string $permission, Closure $record) {
    $model = $record();

    actingAs(User::factory()->withPermissions(["{$permission}.delete"])->create());

    expect($resource::canDelete($model))->toBeTrue()
        ->and($resource::canViewAny())->toBeTrue();
})->with('resources');

it('lets administrators do everything', function (string $resource, string $permission, Closure $record) {
    $model = $record();

    actingAs(User::factory()->administrator()->create());

    $this->get($resource::getUrl('index'))->assertOk();
    $this->get($resource::getUrl('create'))->assertOk();
    $this->get($resource::getUrl('edit', ['record' => $model]))->assertOk();

    expect($resource::canDelete($model))->toBeTrue();
})->with('resources');

it('requires the settings permission for settings pages', function (string $page, array $permissions, int $status) {
    actingAs(User::factory()->withPermissions($permissions)->create());

    $this->get($page::getUrl())->assertStatus($status);
})->with([
    ManageCachet::class,
    ManageCustomization::class,
    ManageLocalization::class,
    ManageNotifications::class,
    ManageTheme::class,
    OhDear::class,
])->with([
    'read only' => [Permissions::viewOnly(), 403],
    'settings' => [[Permissions::SETTINGS], 200],
]);

it('requires the api keys permission for api keys', function (array $permissions, int $status) {
    actingAs(User::factory()->withPermissions($permissions)->create());

    $this->get(ApiKeyResource::getUrl('index'))->assertStatus($status);
})->with([
    'read only' => [Permissions::viewOnly(), 403],
    'api keys' => [[Permissions::API_KEYS], 200],
]);

it('hides dashboard widgets for resources the user cannot view', function () {
    actingAs(User::factory()->create());

    expect(SystemHealth::canView())->toBeFalse()
        ->and(Overview::canView())->toBeFalse()
        ->and(OpenIncidents::canView())->toBeFalse()
        ->and(UpcomingMaintenance::canView())->toBeFalse()
        ->and(Components::canView())->toBeFalse();

    actingAs(User::factory()->withPermissions(['incidents.view'])->create());

    expect(Overview::canView())->toBeTrue()
        ->and(OpenIncidents::canView())->toBeTrue()
        ->and(SystemHealth::canView())->toBeFalse();
});

it('shows only the overview stats the user may view', function () {
    actingAs(User::factory()->withPermissions(['subscribers.view'])->create());

    $overview = new class extends Overview
    {
        public function stats(): array
        {
            return $this->getStats();
        }
    };

    expect($overview->stats())->toHaveCount(1)
        ->and($overview->stats()[0]->getUrl())->toBe(SubscriberResource::getUrl('index'));
});

it('hides the record update action without the update permission', function () {
    $incident = Incident::factory()->create();

    actingAs(User::factory()->withPermissions(['incidents.manage'])->create());

    livewire(ListIncidents::class)
        ->assertActionHidden(TestAction::make('add-update')->table($incident));

    actingAs(User::factory()->withPermissions(['incidents.manage', 'incident-updates.manage'])->create());

    livewire(ListIncidents::class)
        ->assertActionVisible(TestAction::make('add-update')->table($incident));
});

it('shows each kind of update only with its own permission', function () {
    $incident = Incident::factory()->create();
    $schedule = Schedule::factory()->create();

    actingAs(User::factory()->withPermissions(['incident-updates.view'])->create());

    expect(UpdatesRelationManager::canViewForRecord($incident, EditIncident::class))->toBeTrue()
        ->and(UpdatesRelationManager::canViewForRecord($schedule, EditSchedule::class))->toBeFalse();
});

it('renders the incident view page read only', function () {
    $incident = Incident::factory()->create();

    actingAs(User::factory()->withPermissions(['incidents.view'])->create());

    livewire(ViewIncident::class, ['record' => $incident->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaStateSet(['name' => $incident->name])
        ->assertActionHidden('edit');
});

it('links the status page incident to the dashboard only for incident managers', function (string $permission, string $assertion) {
    $incident = Incident::factory()->create();

    actingAs(User::factory()->withPermissions([$permission])->create())
        ->get(route('cachet.status-page.incident', $incident))
        ->{$assertion}($incident->filamentDashboardEditUrl());
})->with([
    'viewer' => ['incidents.view', 'assertDontSee'],
    'manager' => ['incidents.manage', 'assertSee'],
]);
