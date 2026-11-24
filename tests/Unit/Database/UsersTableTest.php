<?php

use Illuminate\Support\Facades\Schema;
use Workbench\App\User;

it('does not default preferred_locale to a locale', function () {
    $column = collect(Schema::getColumns('users'))->firstWhere('name', 'preferred_locale');

    expect($column['nullable'])->toBeTrue()
        ->and($column['default'])->toBeNull();
});

it('creates users that follow the system default locale', function () {
    $user = User::factory()->create();

    expect($user->fresh()->preferred_locale)->toBeNull();
});
