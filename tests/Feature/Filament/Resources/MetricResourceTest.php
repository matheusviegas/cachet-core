<?php

namespace Tests\Feature\Filament\Resources;

use Cachet\Filament\Resources\Metrics\Pages\CreateMetric;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));

    actingAs(User::factory()->create(['is_admin' => true]));
});

it('translates the suffix placeholder', function () {
    app()->setLocale('de');

    livewire(CreateMetric::class)
        ->assertFormFieldExists('suffix', fn (TextInput $field): bool => $field->getPlaceholder() === 'z. B. ms, %, usw.');
});
