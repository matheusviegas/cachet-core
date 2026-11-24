<?php

use Cachet\Models\Component;
use Cachet\Settings\AppSettings;
use Cachet\Settings\CustomizationSettings;
use Cachet\Settings\MailSettings;
use Cachet\Settings\ThemeSettings;
use Illuminate\Support\Facades\DB;

it('resolves each settings class once per request', function (string $settings) {
    expect(app($settings))->toBe(app($settings));
})->with([AppSettings::class, CustomizationSettings::class, MailSettings::class, ThemeSettings::class]);

it('does not read settings from the database for every component', function () {
    $countQueries = function (): int {
        app()->forgetScopedInstances();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('cachet.status-page'))->assertOk();

        return collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'settings'))
            ->count();
    };

    Component::factory()->count(2)->create();
    $few = $countQueries();

    Component::factory()->count(20)->create();
    $many = $countQueries();

    expect($many)->toBeLessThanOrEqual($few);
});
