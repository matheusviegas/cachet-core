<?php

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Workbench\App\User;

it('sends the verification email in the user language', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create(['preferred_locale' => 'pt_BR']);
    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification, array $channels, User $notifiable, ?string $locale): bool {
        return $locale === 'pt_BR';
    });
});

it('translates the verification email into pt_BR', function () {
    app()->setLocale('pt_BR');

    $user = User::factory()->unverified()->create();
    $mail = (new VerifyEmail)->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Verifique seu endereço de e-mail')
        ->and($mail->actionText)->toBe('Verificar endereço de e-mail')
        ->and($html)->toContain('Olá!', 'Atenciosamente,', 'Todos os direitos reservados.', 'Se você estiver com problemas para clicar no botão');
});
