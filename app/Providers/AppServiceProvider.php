<?php

namespace App\Providers;

use App\Models\FoundItem;
use App\Models\LostItem;
use App\Observers\FoundItemObserver;
use App\Observers\LostItemObserver;
use App\Services\AutoMatchingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AutoMatchingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LostItem::observe(LostItemObserver::class);
        FoundItem::observe(FoundItemObserver::class);
    }
}
