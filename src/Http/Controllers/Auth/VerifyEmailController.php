<?php

declare(strict_types=1);

namespace Cachet\Http\Controllers\Auth;

use Cachet\Cachet;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;

final class VerifyEmailController
{
    /**
     * Mark the user's email address as verified from the signed link.
     */
    public function __invoke(string $id, string $hash): RedirectResponse
    {
        $user = Cachet::userModel()::query()->findOrFail($id);

        abort_unless($user instanceof MustVerifyEmail, 404);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('cachet.status-page', absolute: false).'?verified=1');
    }
}
