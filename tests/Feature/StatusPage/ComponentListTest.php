<?php

use Cachet\Enums\ComponentLayoutEnum;
use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\IncidentStatusEnum;
use Cachet\Enums\ResourceVisibilityEnum;
use Cachet\Models\Component;
use Cachet\Models\ComponentGroup;
use Cachet\Models\Incident;
use Cachet\Settings\AppSettings;
use Illuminate\Support\Facades\DB;
use Workbench\App\User;

beforeEach(function () {
    $settings = app(AppSettings::class);
    $settings->component_layout = ComponentLayoutEnum::list;
    $settings->save();
});

/**
 * Get the ids of the components listed on the status page, in order.
 *
 * @param  array<string, mixed>  $query
 * @return list<int>
 */
function listedComponentIds(array $query = []): array
{
    $html = test()->get(route('cachet.status-page', $query))->assertOk()->getContent();

    preg_match_all('/data-component="component" data-component-id="(\d+)"/', $html, $matches);

    return array_map('intval', $matches[1]);
}

it('renders the searchable list instead of the component groups', function () {
    $group = ComponentGroup::factory()->create(['name' => 'Finance', 'visible' => ResourceVisibilityEnum::guest]);
    $component = Component::factory()->for($group, 'group')->create(['name' => 'Invoice robot']);

    $this->get(route('cachet.status-page'))
        ->assertOk()
        ->assertSee('data-component="component-explorer"', escape: false)
        ->assertDontSee('data-component="component-group"', escape: false)
        ->assertSee('Invoice robot')
        ->assertSee('Finance')
        ->assertSee(route('cachet.status-page.component', $component));
});

it('keeps the group layout by default', function () {
    $settings = app(AppSettings::class);
    $settings->component_layout = ComponentLayoutEnum::groups;
    $settings->save();

    Component::factory()->create();

    $this->get(route('cachet.status-page'))
        ->assertOk()
        ->assertDontSee('data-component="component-explorer"', escape: false)
        ->assertSee('data-component="component-list"', escape: false);
});

it('paginates the components', function () {
    config(['cachet.component_list.per_page' => 5]);
    Component::factory()->count(7)->sequence(fn ($sequence) => ['name' => sprintf('Robot %02d', $sequence->index)])->create();

    expect(listedComponentIds())->toHaveCount(5)
        ->and(listedComponentIds(['page' => 2]))->toHaveCount(2);

    $this->get(route('cachet.status-page'))
        ->assertSee(__('cachet::component.explorer.showing', ['from' => 1, 'to' => 5, 'total' => 7]))
        ->assertSee('rel="next"', escape: false);
});

it('searches components by name', function () {
    $invoices = Component::factory()->create(['name' => 'Invoice robot']);
    Component::factory()->create(['name' => 'Payroll robot']);

    expect(listedComponentIds(['q' => 'invoice']))->toBe([$invoices->id]);
});

it('filters by effective status and summarises every status of the search', function () {
    $impacted = Component::factory()->create(['name' => 'Robot A', 'status' => ComponentStatusEnum::operational]);
    $down = Component::factory()->create(['name' => 'Robot B', 'status' => ComponentStatusEnum::major_outage]);
    Component::factory()->create(['name' => 'Robot C', 'status' => ComponentStatusEnum::operational]);
    $incident = Incident::factory()->create(['status' => IncidentStatusEnum::investigating, 'visible' => ResourceVisibilityEnum::guest]);
    $incident->components()->attach($impacted->id, ['component_status' => ComponentStatusEnum::major_outage]);

    expect(listedComponentIds(['status' => 'major_outage']))->toBe([$impacted->id, $down->id]);

    $html = $this->get(route('cachet.status-page', ['status' => 'major_outage']))->getContent();

    expect($html)
        ->toMatch('/data-status="all"[^>]*>.*?<span data-slot="count"[^>]*>3<\/span>/s')
        ->toMatch('/data-status="major_outage"[^>]*aria-current="true"[^>]*>.*?<span data-slot="count"[^>]*>2<\/span>/s')
        ->toMatch('/data-status="operational"[^>]*>.*?<span data-slot="count"[^>]*>1<\/span>/s');
});

it('filters by several statuses at once', function () {
    $partial = Component::factory()->create(['status' => ComponentStatusEnum::partial_outage]);
    $down = Component::factory()->create(['status' => ComponentStatusEnum::major_outage]);
    Component::factory()->create(['status' => ComponentStatusEnum::operational]);

    expect(listedComponentIds(['status' => 'major_outage,partial_outage']))->toBe([$down->id, $partial->id]);
});

it('filters by group and by tag', function () {
    $finance = ComponentGroup::factory()->create(['visible' => ResourceVisibilityEnum::guest]);
    $invoices = Component::factory()->for($finance, 'group')->create();
    $payroll = Component::factory()->create();
    $payroll->syncTags(['Human Resources']);

    expect(listedComponentIds(['group' => $finance->id]))->toBe([$invoices->id])
        ->and(listedComponentIds(['tag' => 'human-resources']))->toBe([$payroll->id]);
});

it('only lists components the viewer may see', function () {
    $public = Component::factory()->for(ComponentGroup::factory()->state(['visible' => ResourceVisibilityEnum::guest]), 'group')->create();
    $internal = Component::factory()->for(ComponentGroup::factory()->state(['visible' => ResourceVisibilityEnum::authenticated]), 'group')->create();
    $hidden = Component::factory()->for(ComponentGroup::factory()->state(['visible' => ResourceVisibilityEnum::hidden]), 'group')->create();
    $disabled = Component::factory()->create(['enabled' => false]);
    $ungrouped = Component::factory()->create();

    expect(listedComponentIds())->toEqualCanonicalizing([$public->id, $ungrouped->id]);

    $this->actingAs(User::factory()->create());

    expect(listedComponentIds())->toEqualCanonicalizing([$public->id, $internal->id, $ungrouped->id])
        ->not->toContain($hidden->id, $disabled->id);
});

it('lists the most severe components first by default and sorts by name on request', function () {
    $operational = Component::factory()->create(['name' => 'A', 'status' => ComponentStatusEnum::operational]);
    $down = Component::factory()->create(['name' => 'B', 'status' => ComponentStatusEnum::major_outage]);
    $performance = Component::factory()->create(['name' => 'C', 'status' => ComponentStatusEnum::performance_issues]);

    expect(listedComponentIds())->toBe([$down->id, $performance->id, $operational->id])
        ->and(listedComponentIds(['sort' => 'name']))->toBe([$operational->id, $down->id, $performance->id]);
});

it('sorts by group in the dashboard order', function () {
    $second = ComponentGroup::factory()->create(['order' => 2, 'visible' => ResourceVisibilityEnum::guest]);
    $first = ComponentGroup::factory()->create(['order' => 1, 'visible' => ResourceVisibilityEnum::guest]);
    $ungrouped = Component::factory()->create();
    $inSecond = Component::factory()->for($second, 'group')->create();
    $inFirst = Component::factory()->for($first, 'group')->create();

    expect(listedComponentIds(['sort' => 'group']))->toBe([$inFirst->id, $inSecond->id, $ungrouped->id]);
});

it('keeps the other filters in the summary links', function () {
    Component::factory()->create(['name' => 'Invoice robot', 'status' => ComponentStatusEnum::major_outage]);

    $this->get(route('cachet.status-page', ['q' => 'invoice']))
        ->assertSee(e(route('cachet.status-page', ['q' => 'invoice', 'status' => 'major_outage']).'#components'), escape: false);
});

it('shows every matching component as a tile in the grid view', function () {
    $group = ComponentGroup::factory()->create(['name' => 'Finance', 'visible' => ResourceVisibilityEnum::guest]);
    $down = Component::factory()->for($group, 'group')->create(['name' => 'Invoice robot', 'status' => ComponentStatusEnum::major_outage]);
    Component::factory()->create(['name' => 'Payroll robot']);

    $html = $this->get(route('cachet.status-page', ['view' => 'grid']))->assertOk()->getContent();

    expect(substr_count($html, 'data-slot="tile"'))->toBe(2)
        ->and($html)->toContain('aria-label="Invoice robot: '.ComponentStatusEnum::major_outage->getLabel().'"')
        ->toContain('href="'.route('cachet.status-page.component', $down).'"')
        ->toContain('data-component-group-id="'.$group->id.'"')
        ->not->toContain('data-component="component" data-component-id');
});

it('caps the grid and asks to narrow the filters', function () {
    config(['cachet.component_list.grid_limit' => 3]);
    Component::factory()->count(5)->create();

    $html = $this->get(route('cachet.status-page', ['view' => 'grid']))->getContent();

    expect(substr_count($html, 'data-slot="tile"'))->toBe(3)
        ->and($html)->toContain('data-slot="truncated"');
});

it('ignores invalid filters', function () {
    $component = Component::factory()->create();

    expect(listedComponentIds(['status' => 'bogus', 'group' => 'abc', 'sort' => 'nope', 'view' => 'cards', 'tag' => '']))->toBe([$component->id]);

    $this->get(route('cachet.status-page').'?q[]=x&status[]=major_outage')->assertOk();
});

it('runs the same number of queries whatever the number of components', function () {
    $countQueries = function (): int {
        app()->forgetScopedInstances();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('cachet.status-page'))->assertOk();

        return count(DB::getQueryLog());
    };

    $group = ComponentGroup::factory()->create(['visible' => ResourceVisibilityEnum::guest]);
    Component::factory()->count(3)->for($group, 'group')->create();
    $countQueries();
    $few = $countQueries();

    Component::factory()->count(40)->for($group, 'group')->create();
    Component::factory()->count(20)->create();
    $many = $countQueries();

    expect($many)->toBe($few);
});
