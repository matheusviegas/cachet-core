<?php

use Cachet\Cachet;
use Cachet\Models\Group;
use Cachet\Permissions;
use Workbench\App\User;

it('adds a view permission to every api resource', function () {
    foreach (array_keys(Cachet::getResourceApiAbilities()) as $resource) {
        expect(Permissions::all())->toContain("{$resource}.view", "{$resource}.manage", "{$resource}.delete");
    }
});

it('includes the dashboard only permissions', function () {
    expect(Permissions::all())->toContain(
        'users.view',
        'groups.manage',
        'webhooks.delete',
        Permissions::API_KEYS,
        Permissions::SETTINGS,
    );
});

it('lists only view permissions as read only', function () {
    expect(Permissions::viewOnly())
        ->toContain('incidents.view', 'users.view')
        ->not->toContain('incidents.manage', Permissions::SETTINGS);
});

it('implies view from manage or delete', function () {
    expect(Permissions::isGranted(['incidents.manage'], 'incidents.view'))->toBeTrue()
        ->and(Permissions::isGranted(['incidents.delete'], 'incidents.view'))->toBeTrue()
        ->and(Permissions::isGranted(['incidents.view'], 'incidents.manage'))->toBeFalse()
        ->and(Permissions::isGranted(['incidents.manage'], 'incidents.delete'))->toBeFalse()
        ->and(Permissions::isGranted(['components.manage'], 'incidents.view'))->toBeFalse();
});

it('translates permission labels', function () {
    expect(Permissions::label('incident-updates.manage'))->toBe('Manage incident updates');

    app()->setLocale('pt_BR');

    expect(Permissions::label('incident-updates.manage'))->toBe('Gerenciar atualizações de incidentes');
});

it('labels every permission', function () {
    foreach (Permissions::labels() as $permission => $label) {
        expect($label)->not->toContain('cachet::', $permission);
    }
});

it('gives users the union of their groups permissions', function () {
    $user = User::factory()->create();
    $user->groups()->attach([
        Group::factory()->withPermissions(['incidents.manage'])->create()->getKey(),
        Group::factory()->withPermissions(['components.delete', 'incidents.manage'])->create()->getKey(),
    ]);

    expect($user->getPermissions())->toBe(['incidents.manage', 'components.delete'])
        ->and($user->hasPermission('components.view'))->toBeTrue()
        ->and($user->hasPermission('subscribers.view'))->toBeFalse()
        ->and($user->hasAllPermissions(['incidents.view', 'components.delete']))->toBeTrue()
        ->and($user->hasAllPermissions(['incidents.view', 'users.view']))->toBeFalse();
});

it('gives users without groups no permissions', function () {
    expect(User::factory()->create()->getPermissions())->toBe([]);
});
