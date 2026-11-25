<?php

namespace App\Providers;

use Illuminate\Foundation\DevCommands;
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
        // Worker mặc định đọc hàng đợi chung. Danh mục Sapo có kết nối hàng đợi riêng.
        DevCommands::artisan('queue:work sapo --queue=sapo --sleep=2 --timeout=3600 --tries=1 --no-interaction', 'sapo');
    }
}
