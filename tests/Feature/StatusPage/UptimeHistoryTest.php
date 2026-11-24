<?php

use Cachet\Enums\ComponentLayoutEnum;
use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\ResourceVisibilityEnum;
use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Cachet\Models\ComponentUptimeDay;
use Cachet\Settings\AppSettings;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-24 12:00:00');

    $settings = app(AppSettings::class);
    $settings->component_layout = ComponentLayoutEnum::list;
    $settings->display_system_status = true;
    $settings->save();
});

/**
 * Store a recorded uptime day for the component.
 */
function recordUptimeDay(Component $component, string $date, ComponentStatusEnum $status, int $available, int $operational): void
{
    ComponentUptimeDay::query()->insert([
        'component_id' => $component->id,
        'date' => $date,
        'status' => $status->value,
        'severity' => $status->severity(),
        'available_seconds' => $available,
        'operational_seconds' => $operational,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Get the HTML of the uptime history row for the given group, or for all components.
 */
function uptimeRow(string $html, ?int $groupId = null): string
{
    $rows = explode('data-slot="row"', $html);
    array_shift($rows);

    foreach ($rows as $row) {
        $isGroupRow = str_starts_with(trim($row), 'data-component-group-id=');

        if ($groupId === null ? ! $isGroupRow : str_starts_with(trim($row), 'data-component-group-id="'.$groupId.'"')) {
            return $row;
        }
    }

    return '';
}

it('shows the worst status and the uptime of all components and of each group', function () {
    $finance = ComponentGroup::factory()->create(['name' => 'Finance', 'visible' => ResourceVisibilityEnum::guest]);
    $payroll = ComponentGroup::factory()->create(['name' => 'Payroll', 'visible' => ResourceVisibilityEnum::guest]);
    $invoices = Component::factory()->for($finance, 'group')->create();
    $salaries = Component::factory()->for($payroll, 'group')->create();

    recordUptimeDay($invoices, '2026-11-23', ComponentStatusEnum::major_outage, 86400, 43200);
    recordUptimeDay($salaries, '2026-11-23', ComponentStatusEnum::operational, 86400, 86400);

    $html = $this->get(route('cachet.status-page'))->assertOk()->getContent();

    expect(uptimeRow($html))
        ->toContain('data-date="2026-11-23" data-status="major_outage"')
        ->toContain(__('cachet::system_status.uptime', ['uptime' => '75.00']))
        ->and(uptimeRow($html, $finance->id))
        ->toContain('data-date="2026-11-23" data-status="major_outage"')
        ->toContain(__('cachet::system_status.uptime', ['uptime' => '50.00']))
        ->toContain(e(route('cachet.status-page', ['group' => $finance->id]).'#components'))
        ->and(uptimeRow($html, $payroll->id))
        ->toContain('data-date="2026-11-23" data-status="operational"')
        ->toContain(__('cachet::system_status.uptime', ['uptime' => '100.00']));

    expect(substr_count(uptimeRow($html), 'data-slot="day"'))->toBe(90);
});

it('ignores days without available time when picking the worst status', function () {
    $component = Component::factory()->create();
    $newer = Component::factory()->create();

    recordUptimeDay($component, '2026-11-23', ComponentStatusEnum::operational, 86400, 86400);
    recordUptimeDay($newer, '2026-11-23', ComponentStatusEnum::unknown, 0, 0);

    expect(uptimeRow($this->get(route('cachet.status-page'))->getContent()))
        ->toContain('data-date="2026-11-23" data-status="operational"');
});

it('leaves out components the viewer may not see', function () {
    $hidden = ComponentGroup::factory()->create(['name' => 'Secret', 'visible' => ResourceVisibilityEnum::hidden]);
    $visible = Component::factory()->create();
    $secret = Component::factory()->for($hidden, 'group')->create();

    recordUptimeDay($visible, '2026-11-23', ComponentStatusEnum::operational, 86400, 86400);
    recordUptimeDay($secret, '2026-11-23', ComponentStatusEnum::major_outage, 86400, 0);

    $html = $this->get(route('cachet.status-page'))->getContent();

    expect(uptimeRow($html))->toContain('data-date="2026-11-23" data-status="operational"')
        ->and($html)->not->toContain('data-component-group-id="'.$hidden->id.'"');
});

it('explains when no uptime has been recorded yet', function () {
    Component::factory()->create();

    $this->get(route('cachet.status-page'))
        ->assertSee(__('cachet::system_status.history_empty'))
        ->assertDontSee('data-component="system-status"', escape: false);
});

it('is not shown when the system status is turned off', function () {
    $settings = app(AppSettings::class);
    $settings->display_system_status = false;
    $settings->save();

    $this->get(route('cachet.status-page'))->assertDontSee('data-component="uptime-history"', escape: false);
});
