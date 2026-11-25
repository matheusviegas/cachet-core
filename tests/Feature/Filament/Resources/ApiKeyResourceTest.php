<?php

use Cachet\Filament\Resources\ApiKeys\Pages\CreateApiKey;
use Cachet\Permissions;
use Filament\Facades\Filament;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));
});

it('only offers the abilities the user holds', function () {
    actingAs(User::factory()->withPermissions([Permissions::API_KEYS, 'incidents.manage', 'components.delete'])->create());

    livewire(CreateApiKey::class)
        ->assertFormFieldExists('abilities', fn ($field): bool => array_keys($field->getOptions()) === [
            'components.delete',
            'incidents.manage',
        ]);
});

it('offers every ability to administrators', function () {
    actingAs(User::factory()->administrator()->create());

    livewire(CreateApiKey::class)
        ->assertFormFieldExists('abilities', fn ($field): bool => count($field->getOptions()) === 20);
});

it('rejects abilities the user does not hold', function () {
    actingAs(User::factory()->withPermissions([Permissions::API_KEYS, 'incidents.manage'])->create());

    livewire(CreateApiKey::class)
        ->fillForm(['name' => 'Robot', 'abilities' => ['components.delete']])
        ->call('create')
        ->assertHasFormErrors(['abilities.0']);
});
