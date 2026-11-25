<?php

use Cachet\Enums\ComponentListSortEnum;
use Cachet\Enums\ComponentListViewEnum;
use Cachet\Enums\ComponentStatusEnum;
use Cachet\View\ComponentListFilters;
use Illuminate\Http\Request;

it('reads the filters from the query string', function () {
    $filters = ComponentListFilters::fromRequest(Request::create('/', 'GET', [
        'q' => '  invoice ',
        'status' => 'major_outage,partial_outage,major_outage',
        'group' => '3',
        'tag' => 'Human Resources',
        'sort' => 'name',
        'view' => 'grid',
    ]));

    expect($filters)
        ->search->toBe('invoice')
        ->statuses->toBe([ComponentStatusEnum::major_outage, ComponentStatusEnum::partial_outage])
        ->group->toBe(3)
        ->tag->toBe('human-resources')
        ->sort->toBe(ComponentListSortEnum::name)
        ->view->toBe(ComponentListViewEnum::grid)
        ->and($filters->isFiltered())->toBeTrue();
});

it('drops invalid values', function () {
    $filters = ComponentListFilters::fromRequest(Request::create('/', 'GET', [
        'q' => ['array'],
        'status' => 'bogus',
        'group' => '-1',
        'sort' => 'random',
        'view' => 'cards',
    ]));

    expect($filters)
        ->search->toBe('')
        ->statuses->toBe([])
        ->group->toBeNull()
        ->tag->toBeNull()
        ->sort->toBe(ComponentListSortEnum::severity)
        ->view->toBe(ComponentListViewEnum::list)
        ->and($filters->isFiltered())->toBeFalse();
});

it('limits the search term length', function () {
    $filters = ComponentListFilters::fromRequest(Request::create('/', 'GET', ['q' => str_repeat('a', 500)]));

    expect(mb_strlen($filters->search))->toBe(100);
});

it('builds query string parameters without defaults', function () {
    $filters = new ComponentListFilters(search: 'invoice', statuses: [ComponentStatusEnum::major_outage]);

    expect($filters->parameters())->toBe(['q' => 'invoice', 'status' => 'major_outage'])
        ->and($filters->parameters(['status' => null, 'view' => 'grid']))->toBe(['q' => 'invoice', 'view' => 'grid']);
});
