<?php

namespace Tests\Feature\StatusPage;

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\IncidentStatusEnum;
use Cachet\Models\Component;
use Cachet\Models\Schedule;
use Cachet\Models\Update;
use Cachet\Settings\AppSettings;

use function Pest\Laravel\get;

it('renders the schedule page', function () {
    $schedule = Schedule::factory()->create([
        'name' => 'Database maintenance',
        'message' => 'We will be upgrading the database.',
        'scheduled_at' => now()->addDay(),
    ]);

    $update = new Update([
        'message' => 'Maintenance window confirmed.',
        'status' => IncidentStatusEnum::unknown,
    ]);
    $schedule->updates()->save($update);

    get(route('cachet.status-page.schedule', ['schedule' => $schedule]))
        ->assertOk()
        ->assertSee('Database maintenance')
        ->assertSee('Maintenance window confirmed.');
});

it('translates the affected components label and links each component', function () {
    $settings = app(AppSettings::class);
    $settings->locale = 'de';
    $settings->save();

    $database = Component::factory()->create(['name' => 'Database']);
    $website = Component::factory()->create(['name' => 'Website']);
    $schedule = Schedule::factory()->create(['scheduled_at' => now()->addDay()]);
    $schedule->components()->attach([$database->id, $website->id], [
        'component_status' => ComponentStatusEnum::under_maintenance,
    ]);

    get(route('cachet.status-page.schedule', ['schedule' => $schedule]))
        ->assertOk()
        ->assertSeeInOrder([
            'Betroffene Komponenten:',
            'href="'.route('cachet.status-page.component', $database).'"',
            'Database',
            'href="'.route('cachet.status-page.component', $website).'"',
            'Website',
        ], escape: false)
        ->assertDontSee('Affected Components');
});

it('links to schedules from the status page', function () {
    $schedule = Schedule::factory()->create(['scheduled_at' => now()->addDay()]);

    get(route('cachet.status-page'))
        ->assertOk()
        ->assertSee(route('cachet.status-page.schedule', ['schedule' => $schedule]));
});

it('returns not found for a missing schedule', function () {
    get(route('cachet.status-page.schedule', ['schedule' => 999]))->assertNotFound();
});

it('uses the maintenance name as the page heading', function () {
    $schedule = Schedule::factory()->create(['name' => 'Database maintenance']);

    $page = get(route('cachet.status-page.schedule', ['schedule' => $schedule]))
        ->assertOk()
        ->getContent();

    expect($page)->toMatch('/<h1[^>]*>\s*Database maintenance\s*<\/h1>/');
});

it('renders stable maintenance page attributes', function () {
    $schedule = Schedule::factory()->create();
    $schedule->updates()->save(new Update([
        'message' => 'Maintenance is underway.',
        'status' => IncidentStatusEnum::unknown,
    ]));

    get(route('cachet.status-page.schedule', ['schedule' => $schedule]))
        ->assertOk()
        ->assertSee('data-page="schedule"', escape: false)
        ->assertSee('data-component="schedule"', escape: false)
        ->assertSee('data-component="schedule-update"', escape: false)
        ->assertSee('data-component="badge"', escape: false)
        ->assertSee('data-component="timestamp"', escape: false)
        ->assertSee('data-component="page-navigation"', escape: false)
        ->assertSee('data-slot="main"', escape: false)
        ->assertSee('data-slot="message"', escape: false);
});

it('shows the window duration with its start and expected end', function () {
    $schedule = Schedule::factory()->create([
        'scheduled_at' => now()->addDay(),
        'completed_at' => now()->addDay()->addHours(49)->addMinutes(20),
    ]);

    get(route('cachet.status-page.schedule', ['schedule' => $schedule]))
        ->assertOk()
        ->assertSeeInOrder([
            'data-slot="duration"',
            '2 days, 1 hour and 20 minutes',
            'Start',
            $schedule->scheduled_at->toW3cString(),
            'Expected end',
            $schedule->completed_at->toW3cString(),
        ], escape: false);
});

it('labels the end of a finished window as the end', function () {
    $schedule = Schedule::factory()->completed()->create();

    get(route('cachet.status-page.schedule', ['schedule' => $schedule]))
        ->assertOk()
        ->assertSee('15 minutes')
        ->assertSee('Ended')
        ->assertDontSee('Expected end');
});

it('omits the duration and end when the window has no end', function () {
    $schedule = Schedule::factory()->inProgress()->create();

    get(route('cachet.status-page.schedule', ['schedule' => $schedule]))
        ->assertOk()
        ->assertSee('Start')
        ->assertDontSee('data-slot="duration"', escape: false)
        ->assertDontSee('Expected end');
});
