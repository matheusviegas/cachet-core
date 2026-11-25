<?php

use Cachet\Enums\DefaultGroupEnum;
use Cachet\Filament\Resources\Groups\GroupResource;
use Cachet\Filament\Resources\Groups\Pages\CreateGroup;
use Cachet\Filament\Resources\Groups\Pages\EditGroup;
use Cachet\Filament\Resources\Groups\Pages\ListGroups;
use Cachet\Filament\Resources\Groups\Pages\ViewGroup;
use Cachet\Filament\Resources\Groups\RelationManagers\UsersRelationManager;
use Cachet\Models\Group;
use Cachet\Permissions;
use Filament\Facades\Filament;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));
});

it('always has the default groups', function () {
    expect(Group::findDefault(DefaultGroupEnum::administrators))->not->toBeNull()
        ->and(Group::findDefault(DefaultGroupEnum::read_only))->not->toBeNull();
});

it('names the default groups in the user language', function () {
    app()->setLocale('pt_BR');

    expect(Group::findDefault(DefaultGroupEnum::administrators)->name)->toBe('Administradores')
        ->and(Group::findDefault(DefaultGroupEnum::read_only)->name)->toBe('Somente leitura');
});

it('gives the default groups the permissions of the catalog', function () {
    expect(Group::findDefault(DefaultGroupEnum::administrators)->grantedPermissions())->toBe(Permissions::all())
        ->and(Group::findDefault(DefaultGroupEnum::read_only)->grantedPermissions())->toBe(Permissions::viewOnly());
});

it('lists the groups', function () {
    actingAs(User::factory()->administrator()->create());

    $group = Group::factory()->withPermissions(['incidents.view', 'incidents.manage'])->create(['name' => 'Incident team']);

    livewire(ListGroups::class)
        ->assertCanSeeTableRecords([$group, Group::findDefault(DefaultGroupEnum::administrators)])
        ->assertTableColumnStateSet('permissions', 2, $group);
});

it('creates a group with the selected permissions', function () {
    actingAs(User::factory()->administrator()->create());

    livewire(CreateGroup::class)
        ->fillForm([
            'name' => 'Incident team',
            'permissions' => ['incidents.view', 'incidents.manage', 'incident-updates.manage'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Group::query()->where('name', 'Incident team')->sole()->permissions)
        ->toBe(['incidents.view', 'incidents.manage', 'incident-updates.manage']);
});

it('only offers the permissions the user holds', function () {
    actingAs(User::factory()->withPermissions(['groups.manage', 'incidents.manage'])->create());

    livewire(CreateGroup::class)
        ->assertFormFieldExists('permissions', fn ($field): bool => array_keys($field->getOptions()) === [
            'incidents.view',
            'incidents.manage',
            'groups.view',
            'groups.manage',
        ]);
});

it('rejects permissions the user does not hold', function () {
    actingAs(User::factory()->withPermissions(['groups.manage'])->create());

    livewire(CreateGroup::class)
        ->fillForm([
            'name' => 'Sneaky',
            'permissions' => ['users.manage'],
        ])
        ->call('create')
        ->assertHasFormErrors(['permissions.0']);

    expect(Group::query()->where('name', 'Sneaky')->exists())->toBeFalse();
});

it('edits a group', function () {
    actingAs(User::factory()->administrator()->create());

    $group = Group::factory()->withPermissions(['incidents.view'])->create();

    livewire(EditGroup::class, ['record' => $group->getRouteKey()])
        ->fillForm(['permissions' => ['incidents.view', 'incidents.delete']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($group->fresh()->permissions)->toBe(['incidents.view', 'incidents.delete']);
});

it('keeps the default groups fixed', function (DefaultGroupEnum $key) {
    actingAs(User::factory()->administrator()->create());

    $group = Group::findDefault($key);

    expect(GroupResource::canEdit($group))->toBeFalse()
        ->and(GroupResource::canDelete($group))->toBeFalse()
        ->and(GroupResource::canView($group))->toBeTrue();
})->with([DefaultGroupEnum::administrators, DefaultGroupEnum::read_only]);

it('shows the default group permissions on its view page', function () {
    actingAs(User::factory()->administrator()->create());

    livewire(ViewGroup::class, ['record' => Group::findDefault(DefaultGroupEnum::read_only)->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaStateSet(['permissions' => Permissions::viewOnly()]);
});

it('does not let users change a group granting more than they hold', function () {
    actingAs(User::factory()->withPermissions(['groups.manage', 'groups.delete'])->create());

    $group = Group::factory()->withPermissions(['users.delete'])->create();

    expect(GroupResource::canEdit($group))->toBeFalse()
        ->and(GroupResource::canDelete($group))->toBeFalse();
});

it('lists the members of a group', function () {
    actingAs(User::factory()->administrator()->create());

    $group = Group::factory()->create();
    $member = User::factory()->create(['name' => 'Ana']);
    $group->users()->attach($member);

    livewire(UsersRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditGroup::class])
        ->assertCanSeeTableRecords([$member]);
});

it('removes memberships when a group is deleted', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    $group->users()->attach($member);

    $group->delete();

    expect($member->fresh()->groups)->toBeEmpty();
});
