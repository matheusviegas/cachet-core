<?php

use Cachet\Enums\ComponentStatusEnum;
use Cachet\Filament\Resources\Schedules\Pages\ListSchedules;
use Cachet\Filament\Resources\Subscribers\Pages\ListSubscribers;
use Cachet\Filament\Resources\Users\Pages\ListUsers;
use Cachet\Filament\Resources\WebhookSubscriptions\Pages\EditWebhookSubscription;
use Cachet\Filament\Widgets\Components;
use Cachet\Models\Component;
use Cachet\Models\Schedule;
use Cachet\Models\Subscriber;
use Cachet\Models\WebhookSubscription;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));

    $this->admin = User::factory()->administrator()->create();
    actingAs($this->admin);
});

it('confirms a subscriber was verified', function () {
    $subscriber = Subscriber::factory()->create();

    livewire(ListSubscribers::class)
        ->callAction(TestAction::make('verify')->table($subscriber))
        ->assertNotified(__('cachet::subscriber.list.actions.verify_success', ['email' => $subscriber->email]));
});

it('confirms a schedule was completed', function () {
    $schedule = Schedule::factory()->inProgress()->create(['name' => 'Database upgrade']);

    livewire(ListSchedules::class)
        ->callAction(TestAction::make('complete')->table($schedule), data: ['completed_at' => now()->toDateTimeString()])
        ->assertNotified(__('cachet::schedule.list.actions.complete_success', ['name' => 'Database upgrade']));
});

it('confirms the verification email was sent to a user', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    livewire(ListUsers::class)
        ->callAction(TestAction::make('verify-email')->table($user))
        ->assertNotified(__('cachet::user.list.actions.verify_email_success', ['email' => $user->email]));
});

it('confirms a webhook secret was updated', function () {
    $webhook = WebhookSubscription::factory()->create();

    livewire(EditWebhookSubscription::class, ['record' => $webhook->getKey()])
        ->callAction(TestAction::make('edit_secret')->schemaComponent('secret_actions'), data: ['secret' => str_repeat('s', 32)])
        ->assertNotified(__('cachet::webhook.form.secret_updated'));

    expect($webhook->fresh()->secret)->toBe(str_repeat('s', 32));
});

it('confirms a component status change from the dashboard', function () {
    $component = Component::factory()->create(['name' => 'Invoice robot', 'status' => ComponentStatusEnum::operational]);

    livewire(Components::class)
        ->call('updateTableColumnState', 'status', (string) $component->getKey(), ComponentStatusEnum::major_outage->value)
        ->assertNotified(__('cachet::dashboard.components.status_updated', [
            'component' => 'Invoice robot',
            'status' => ComponentStatusEnum::major_outage->getLabel(),
        ]));
});
