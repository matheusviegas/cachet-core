<?php

use Cachet\Enums\DefaultGroupEnum;
use Cachet\Models\Group;
use Cachet\Models\User;

/**
 * @return list<?DefaultGroupEnum>
 */
function memberOf(string $email): array
{
    $user = User::query()->where('email', $email)->where('name', 'Dan')->sole();

    return $user->groups->map(fn (Group $group): ?DefaultGroupEnum => $group->key)->all();
}

it('creates the admin user', function () {
    $this->artisan('cachet:make:user', [
        'email' => 'dan@example.com',
        '--name' => 'Dan',
        '--admin' => true,
        '--password' => 'password',
    ]);

    expect(memberOf('dan@example.com'))->toBe([DefaultGroupEnum::administrators]);
});

it('creates the standard user', function () {
    $this->artisan('cachet:make:user', [
        'email' => 'dan@example.com',
        '--name' => 'Dan',
        '--admin' => false,
        '--password' => 'password',
    ]);

    expect(memberOf('dan@example.com'))->toBe([DefaultGroupEnum::read_only]);
});

it('creates the standard user using prompts', function () {
    $this->artisan('cachet:make:user')
        ->expectsQuestion('What is the user\'s name?', 'Dan')
        ->expectsQuestion('What is the user\'s email?', 'dan@example.com')
        ->expectsConfirmation('Is the user an admin?', 'No')
        ->expectsQuestion('What is the user\'s password?', 'password');

    expect(memberOf('dan@example.com'))->toBe([DefaultGroupEnum::read_only]);
});

it('creates the admin user using prompts', function () {
    $this->artisan('cachet:make:user')
        ->expectsQuestion('What is the user\'s name?', 'Dan')
        ->expectsQuestion('What is the user\'s email?', 'dan@example.com')
        ->expectsConfirmation('Is the user an admin?', 'Yes')
        ->expectsQuestion('What is the user\'s password?', 'password');

    expect(memberOf('dan@example.com'))->toBe([DefaultGroupEnum::administrators]);
});
