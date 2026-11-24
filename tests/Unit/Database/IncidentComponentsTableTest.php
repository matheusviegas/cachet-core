<?php

use Illuminate\Support\Facades\Schema;

it('does not keep a single column index on incident_id', function () {
    $indexes = collect(Schema::getIndexes('incident_components'));

    expect($indexes->contains(fn (array $index): bool => $index['columns'] === ['incident_id']))->toBeFalse();
});

it('keeps incident_id indexed through the composite unique', function () {
    $indexes = collect(Schema::getIndexes('incident_components'));

    expect($indexes->contains(fn (array $index): bool => $index['columns'] === ['incident_id', 'component_id'] && $index['unique']))->toBeTrue();
});
