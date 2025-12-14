<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
        // Passportの認証画面ビューを設定
        Passport::authorizationView('auth.oauth.authorize');
        
        // Passportのトークン有効期限設定
        // 注意: Laravel 11以降ではPassportのルートは自動的に登録されます
        Passport::tokensExpireIn(now()->addHours(1));
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}
