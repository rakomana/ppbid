<?php

namespace App\Providers;

use App\Http\Middleware\BlockWhileImpersonating;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        // Re-check on Livewire updates too, so "Save" on a settings page is blocked as well.
        Livewire::addPersistentMiddleware([
            BlockWhileImpersonating::class,
        ]);

        // users.last_login_at is kept up to date by App\Listeners\RecordLastLogin (found automatically).
    }
}
