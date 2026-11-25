<?php

use Cachet\Filament\Pages\EditProfile;
use Filament\Facades\Filament;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));
});

it('lets any user choose the dashboard language in their profile', function () {
    $user = User::factory()->create(['is_admin' => false, 'preferred_locale' => 'en']);
    actingAs($user);

    livewire(EditProfile::class)
        ->assertFormSet(['preferred_locale' => 'en'])
        ->fillForm(['preferred_locale' => 'pt_BR'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->preferred_locale)->toBe('pt_BR');
});

it('stores the system default as no preference', function () {
    $user = User::factory()->create(['is_admin' => false, 'preferred_locale' => 'pt_BR']);
    actingAs($user);

    livewire(EditProfile::class)
        ->fillForm(['preferred_locale' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->preferred_locale)->toBeNull();
});

it('rejects unsupported languages', function () {
    $user = User::factory()->create(['is_admin' => false, 'preferred_locale' => 'en']);
    actingAs($user);

    livewire(EditProfile::class)
        ->fillForm(['preferred_locale' => 'xx'])
        ->call('save')
        ->assertHasFormErrors(['preferred_locale']);

    expect($user->fresh()->preferred_locale)->toBe('en');
});

it('shows the dashboard in the chosen language', function () {
    actingAs(User::factory()->create(['is_admin' => false, 'preferred_locale' => 'pt_BR']));

    $this->get(EditProfile::getUrl())
        ->assertOk()
        ->assertSee('lang="pt-BR"', escape: false)
        ->assertSee(__('cachet::user.profile_information_title', locale: 'pt_BR'));
});

it('follows the browser language without a preference', function (?string $preference) {
    actingAs(User::factory()->create(['is_admin' => false, 'preferred_locale' => $preference]));

    $this->get(EditProfile::getUrl(), ['Accept-Language' => 'pt-BR,pt;q=0.9'])
        ->assertOk()
        ->assertSee('lang="pt-BR"', escape: false);
})->with(['no preference' => [null], 'empty preference' => ['']]);
