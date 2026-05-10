<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');

        ResetPassword::createUrlUsing(function (object $user, string $token) use ($frontendUrl) {
            return $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($user->getEmailForPasswordReset());
        });

        VerifyEmail::createUrlUsing(function (object $user) use ($frontendUrl) {
            $temporarySignedUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ]
            );

            $query = parse_url($temporarySignedUrl, PHP_URL_QUERY);

            return $frontendUrl . '/verify-email?' . $query . '&id=' . $user->getKey() . '&hash=' . sha1($user->getEmailForVerification());
        });
    }
}
