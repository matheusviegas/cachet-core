<?php

namespace Tests\Feature\Filament\Resources;

use Cachet\Enums\DefaultGroupEnum;
use Cachet\Filament\Resources\Users\Pages\CreateUser;
use Cachet\Filament\Resources\Users\Pages\EditUser;
use Cachet\Filament\Resources\Users\Pages\ListUsers;
use Cachet\Filament\Resources\Users\UserResource;
use Cachet\Models\Group;
use Cachet\Permissions;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));

    $this->admin = User::factory()->administrator()->create();

    actingAs($this->admin);
});

it('can edit a user without changing their email', function () {
    $user = User::factory()->create(['email' => 'james@example.com']);

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['name' => 'Renamed User'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh())
        ->name->toBe('Renamed User')
        ->email->toBe('james@example.com');
});

it('cannot change a user email to one that is already taken', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'james@example.com']);

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['email' => 'taken@example.com'])
        ->call('save')
        ->assertHasFormErrors(['email']);
});

it('sends a verification email to an unverified user', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    livewire(ListUsers::class)
        ->callTableAction('verify-email', $user)
        ->assertHasNoTableActionErrors();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('prevents an administrator from deleting themselves', function () {
    expect(UserResource::canDelete($this->admin))->toBeFalse();
});

it('allows an administrator to delete another administrator', function () {
    $otherAdmin = User::factory()->administrator()->create();

    expect(UserResource::canDelete($otherAdmin))->toBeTrue();
});

it('protects the current administrator during a bulk delete', function () {
    $otherAdmin = User::factory()->administrator()->create();

    livewire(ListUsers::class)
        ->callTableBulkAction('delete', [$this->admin, $otherAdmin]);

    expect($this->admin->fresh())->not->toBeNull()
        ->and($otherAdmin->fresh())->toBeNull();
});

it('adds new users to the read only group by default', function () {
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'ana@example.com')->sole()->groups->pluck('key')->all())
        ->toBe([DefaultGroupEnum::read_only]);
});

it('changes the groups of a user', function () {
    $user = User::factory()->create();
    $group = Group::factory()->withPermissions(['incidents.manage'])->create();

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['groups' => [$group->getKey()]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->groups->modelKeys())->toBe([$group->getKey()])
        ->and($user->fresh()->hasPermission('incidents.manage'))->toBeTrue();
});

it('shows the groups of each user', function () {
    $user = User::factory()->create();
    $user->groups()->attach(Group::factory()->create(['name' => 'Robots team']));

    livewire(ListUsers::class)
        ->assertTableColumnStateSet('groups.name', ['Robots team'], $user);
});

it('does not let users change their own groups', function () {
    livewire(EditUser::class, ['record' => $this->admin->getRouteKey()])
        ->assertFormFieldIsDisabled('groups');
});

it('only offers groups within the editor permissions', function () {
    $team = Group::factory()->withPermissions(['incidents.view'])->create();

    actingAs(User::factory()->withPermissions(['users.manage', 'incidents.manage'])->create());

    livewire(CreateUser::class)
        ->assertFormFieldExists('groups', fn ($field): bool => array_key_exists($team->getKey(), $field->getOptions())
            && ! array_key_exists(Group::findDefault(DefaultGroupEnum::administrators)->getKey(), $field->getOptions())
            && ! array_key_exists(Group::findDefault(DefaultGroupEnum::read_only)->getKey(), $field->getOptions()));
});

it('does not let an editor hand out a group beyond their permissions', function () {
    $user = User::factory()->create();

    actingAs(User::factory()->withPermissions(['users.manage'])->create());

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['groups' => [Group::findDefault(DefaultGroupEnum::administrators)->getKey()]])
        ->call('save');

    expect($user->fresh()->isAdministrator())->toBeFalse();
});

it('does not let an editor change a user holding more permissions', function () {
    $administrator = User::factory()->administrator()->create();

    actingAs(User::factory()->withPermissions(['users.manage', 'users.delete'])->create());

    expect(UserResource::canEdit($administrator))->toBeFalse()
        ->and(UserResource::canDelete($administrator))->toBeFalse();
});

it('does not delete the last administrator', function () {
    Group::findDefault(DefaultGroupEnum::administrators)->users()->detach($this->admin);
    $lastAdministrator = User::factory()->administrator()->create();

    actingAs(User::factory()->withPermissions(Permissions::all())->create());

    expect(UserResource::canDelete($lastAdministrator))->toBeFalse();
});

it('requires the users view permission to list users', function () {
    actingAs(User::factory()->withPermissions(['incidents.view'])->create());

    expect(UserResource::canViewAny())->toBeFalse();
});
