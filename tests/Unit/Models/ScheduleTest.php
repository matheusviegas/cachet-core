<?php

use Cachet\Enums\ScheduleStatusEnum;
use Cachet\Models\Schedule;
use Cachet\Models\ScheduleComponent;
use Illuminate\Support\Carbon;

it('has components', function () {
    $schedule = Schedule::factory()->create();
    ScheduleComponent::factory()
        ->count(2)
        ->create([
            'schedule_id' => $schedule->id,
        ]);

    expect($schedule)
        ->components->toHaveCount(2);
});

it('has meta', function () {
    $schedule = Schedule::factory()->withMeta()->create();

    expect($schedule->metaValues())->toBe([
        'foo' => 'bar',
    ]);
});

it('can get incomplete schedules', function () {
    $scheduleA = Schedule::factory()->inTheFuture()->create();
    Schedule::factory()->inProgress()->create();
    Schedule::factory()->inThePast()->create();

    expect(Schedule::query()->incomplete()->get())
        ->toHaveCount(2)
        ->first()->id->toBe($scheduleA->id);
});

it('can get incomplete schedules at midnight', function () {
    Carbon::setTestNow(Carbon::create(2024, 12, 23, 0));

    $scheduleA = Schedule::factory()->inTheFuture()->create();
    Schedule::factory()->inProgress()->create();
    Schedule::factory()->inThePast()->create();

    expect(Schedule::query()->incomplete()->get())
        ->toHaveCount(2)
        ->first()->id->toBe($scheduleA->id);
});

it('can get in-progress schedules', function () {
    $schedule = Schedule::factory()->inThePast()->create();
    $scheduleInProgress = Schedule::factory()->inProgress()->create();

    expect(Schedule::query()->inProgress()->get())
        ->toHaveCount(1)
        ->first()->id->toBe($scheduleInProgress->id);
});

it('can get schedules in the future', function () {
    $schedule = Schedule::factory()->inThePast()->create();
    $scheduleInFuture = Schedule::factory()->inTheFuture()->create();

    expect(Schedule::query()->inTheFuture()->get())
        ->toHaveCount(1)
        ->first()->id->toBe($scheduleInFuture->id);
});

it('can get schedules from the past', function () {
    Schedule::factory()->inTheFuture()->create();
    $scheduleInPast = Schedule::factory()->inThePast()->create();

    expect(Schedule::query()->inThePast()->get())
        ->toHaveCount(1)
        ->first()->id->toBe($scheduleInPast->id);
});

it('can determine a schedule\'s upcoming status', function () {
    $schedule = Schedule::factory()->inTheFuture()->create();

    expect($schedule)->status->toBe(ScheduleStatusEnum::upcoming);
});

it('can determine a schedule\'s in-progress status', function () {
    $schedule = Schedule::factory()->inProgress()->create();

    expect($schedule)->status->toBe(ScheduleStatusEnum::in_progress);
});

it('can determine a schedule\'s completed status', function () {
    $schedule = Schedule::factory()->completed()->create();

    expect($schedule)->status->toBe(ScheduleStatusEnum::complete);
});

it('formats the maintenance window duration', function (int $minutes, string $locale, string $expected) {
    app()->setLocale($locale);

    $schedule = Schedule::factory()->make([
        'scheduled_at' => Carbon::create(2026, 11, 25, 10),
        'completed_at' => Carbon::create(2026, 11, 25, 10)->addMinutes($minutes),
    ]);

    expect($schedule->formattedDuration())->toBe($expected);
})->with([
    '30 minutes' => [30, 'pt_BR', '30 minutos'],
    '1 day 30 minutes' => [24 * 60 + 30, 'pt_BR', '1 dia e 30 minutos'],
    '5 hours 15 minutes' => [5 * 60 + 15, 'pt_BR', '5 horas e 15 minutos'],
    '49 hours 20 minutes' => [49 * 60 + 20, 'pt_BR', '2 dias, 1 hora e 20 minutos'],
    '5 hours' => [5 * 60, 'pt_BR', '5 horas'],
    'days instead of weeks' => [10 * 24 * 60, 'pt_BR', '10 dias'],
    'english' => [49 * 60 + 20, 'en', '2 days, 1 hour and 20 minutes'],
]);

it('has no duration without an end or with an empty window', function (?Carbon $completedAt) {
    $schedule = Schedule::factory()->make([
        'scheduled_at' => Carbon::create(2026, 11, 25, 10),
        'completed_at' => $completedAt,
    ]);

    expect($schedule->formattedDuration())->toBeNull();
})->with([
    'no end' => [null],
    'same time' => [Carbon::create(2026, 11, 25, 10)],
    'end before start' => [Carbon::create(2026, 11, 25, 9)],
]);
