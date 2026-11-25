<?php

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Filament\Pages\Settings\ManageTheme;
use Cachet\Models\Component;
use Cachet\Settings\AppSettings;
use Cachet\Settings\ThemeSettings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Workbench\App\User;

beforeEach(function () {
    Storage::fake('public');
    config(['cachet.uploads.disk' => 'public']);
});

/**
 * Store an uploaded favicon in the theme settings.
 */
function useCustomFavicon(string $path = 'favicons/brand.png'): string
{
    Storage::disk('public')->put($path, 'fake-icon-bytes');

    $settings = app(ThemeSettings::class);
    $settings->favicon = $path;
    $settings->save();

    return Storage::disk('public')->url($path);
}

function useDynamicFavicon(): void
{
    $settings = app(AppSettings::class);
    $settings->dynamic_favicon = true;
    $settings->save();
}

it('uses the default favicon without a custom one', function () {
    $this->get(route('cachet.status-page'))
        ->assertOk()
        ->assertSee('<link rel="shortcut icon" href="'.asset('vendor/cachethq/cachet/favicon.ico').'" />', escape: false)
        ->assertSee('<link rel="apple-touch-icon" href="'.asset('vendor/cachethq/cachet/apple-touch-icon.png').'" />', escape: false);
});

it('uses the custom favicon on every status page view', function () {
    $url = useCustomFavicon();

    $this->get(route('cachet.status-page'))
        ->assertOk()
        ->assertSee('<link rel="icon" href="'.$url.'" type="image/png" />', escape: false)
        ->assertSee('<link rel="apple-touch-icon" href="'.$url.'" />', escape: false)
        ->assertDontSee('vendor/cachethq/cachet/favicon.ico');
});

it('sends ICO favicons with their own type', function () {
    $url = useCustomFavicon('favicons/brand.ico');

    $this->get(route('cachet.status-page'))
        ->assertSee('<link rel="icon" href="'.$url.'" type="image/x-icon" />', escape: false);
});

it('keeps the custom favicon while everything is operational and dynamic favicons are on', function () {
    useDynamicFavicon();
    $url = useCustomFavicon();
    Component::factory()->create(['status' => ComponentStatusEnum::operational]);

    $this->get(route('cachet.status-page'))
        ->assertSee('<link rel="icon" href="'.$url.'" type="image/png" />', escape: false)
        ->assertDontSee('favicon-major-outage.svg');
});

it('shows the status favicon over the custom one during incidents and maintenance', function (ComponentStatusEnum $status, string $favicon) {
    useDynamicFavicon();
    useCustomFavicon();
    Component::factory()->create(['status' => $status]);

    $this->get(route('cachet.status-page'))
        ->assertSee('<link rel="icon" href="'.asset('vendor/cachethq/cachet/'.$favicon).'" type="image/svg+xml" sizes="any" />', escape: false);
})->with([
    'major outage' => [ComponentStatusEnum::major_outage, 'favicon-major-outage.svg'],
    'under maintenance' => [ComponentStatusEnum::under_maintenance, 'favicon-under-maintenance.svg'],
]);

it('falls back to the default favicon when the uploaded file is gone', function () {
    $settings = app(ThemeSettings::class);
    $settings->favicon = 'favicons/missing.png';
    $settings->save();

    $this->get(route('cachet.status-page'))
        ->assertSee('vendor/cachethq/cachet/favicon.ico')
        ->assertDontSee('favicons/missing.png');
});

it('uses the custom favicon in the dashboard', function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));
    $this->actingAs(User::factory()->administrator()->create());

    $this->get(ManageTheme::getUrl())->assertSee('vendor/cachethq/cachet/favicon.ico');

    $url = useCustomFavicon();

    $this->get(ManageTheme::getUrl())
        ->assertOk()
        ->assertSee('href="'.$url.'"', escape: false)
        ->assertDontSee('vendor/cachethq/cachet/favicon.ico');
});
