<?php

namespace Tests\Feature\Filament\Settings;

use Cachet\Enums\ThemeModeEnum;
use Cachet\Filament\Pages\Settings\ManageTheme;
use Cachet\Settings\ThemeSettings;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));

    actingAs(User::factory()->create(['is_admin' => true]));
    Storage::fake('public');

    config()->set([
        'cachet.uploads.disk' => 'public',
        'cachet.uploads.max_size' => 256,
        'cachet.uploads.image_mime_types' => ['image/jpeg', 'image/png'],
    ]);
});

it('renders the manage theme page', function () {
    $this->get(ManageTheme::getUrl())->assertOk();
});

it('hides the duplicate banner field label beneath the section heading', function () {
    livewire(ManageTheme::class)
        ->assertSchemaComponentExists(
            'app_banner',
            'form',
            fn (FileUpload $field): bool => $field->isLabelHidden()
                && $field->getLabel() === __('cachet::settings.manage_theme.app_banner_label'),
        );
});

it('configures app logo upload validation', function () {
    livewire(ManageTheme::class)
        ->assertFormFieldExists('app_banner', function (FileUpload $field): bool {
            return $field->getDiskName() === 'public'
                && $field->getMaxSize() === 256
                && $field->getAcceptedFileTypes() === ['image/jpeg', 'image/png']
                && $field->shouldPreventFilePathTampering();
        });
});

it('rejects app logos with disallowed image types', function () {
    livewire(ManageTheme::class)
        ->fillForm([
            'app_banner' => UploadedFile::fake()->create('app-logo.gif', 32, 'image/gif'),
        ])
        ->call('save')
        ->assertHasFormErrors(['app_banner']);
});

it('rejects app logos larger than the configured maximum', function () {
    livewire(ManageTheme::class)
        ->fillForm([
            'app_banner' => UploadedFile::fake()->create('app-logo.png', 257, 'image/png'),
        ])
        ->call('save')
        ->assertHasFormErrors(['app_banner']);
});

it('accepts app logos within the configured constraints', function () {
    livewire(ManageTheme::class)
        ->fillForm([
            'app_banner' => UploadedFile::fake()->create('app-logo.png', 256, 'image/png'),
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('allows saving when a banner image is already stored', function () {
    Storage::disk('public')->put('app-banner.png', 'fake-image-bytes');

    $settings = app(ThemeSettings::class);
    $settings->app_banner = 'app-banner.png';
    $settings->save();

    livewire(ManageTheme::class)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(ThemeSettings::class)->refresh()->app_banner)->toBe('app-banner.png');
});

it('saves the theme mode setting', function (ThemeModeEnum $mode) {
    expect(app(ThemeSettings::class)->theme_mode)->toBe(ThemeModeEnum::auto);

    livewire(ManageTheme::class)
        ->fillForm(['theme_mode' => $mode->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(ThemeSettings::class)->refresh()->theme_mode)->toBe($mode);
})->with([ThemeModeEnum::light, ThemeModeEnum::dark, ThemeModeEnum::auto]);

it('uploads a favicon', function () {
    livewire(ManageTheme::class)
        ->fillForm(['favicon' => UploadedFile::fake()->create('brand.png', 16, 'image/png')])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = app(ThemeSettings::class)->refresh()->favicon;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('accepts ICO favicons', function (string $mimeType) {
    livewire(ManageTheme::class)
        ->fillForm(['favicon' => UploadedFile::fake()->create('brand.ico', 16, $mimeType)])
        ->call('save')
        ->assertHasNoFormErrors();
})->with(['image/x-icon', 'image/vnd.microsoft.icon']);

it('rejects favicons that could run scripts or are not icons', function (string $name, string $mimeType) {
    livewire(ManageTheme::class)
        ->fillForm(['favicon' => UploadedFile::fake()->create($name, 16, $mimeType)])
        ->call('save')
        ->assertHasFormErrors(['favicon']);
})->with([
    'svg' => ['brand.svg', 'image/svg+xml'],
    'html' => ['brand.html', 'text/html'],
]);

it('rejects favicons larger than the configured maximum', function () {
    livewire(ManageTheme::class)
        ->fillForm(['favicon' => UploadedFile::fake()->create('brand.png', 257, 'image/png')])
        ->call('save')
        ->assertHasFormErrors(['favicon']);
});

it('allows saving when a favicon is already stored and removing it', function () {
    Storage::disk('public')->put('brand.png', 'fake-icon-bytes');

    $settings = app(ThemeSettings::class);
    $settings->favicon = 'brand.png';
    $settings->save();

    livewire(ManageTheme::class)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(ThemeSettings::class)->refresh()->favicon)->toBe('brand.png');

    livewire(ManageTheme::class)
        ->fillForm(['favicon' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(ThemeSettings::class)->refresh()->favicon)->toBeNull();
});
