<?php

namespace App\Providers;

use App\View\Composers\PanelAdminComposer;
use Illuminate\Support\Facades\View;
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
        // Semua tab Admin berada di satu halaman (Admin.AdminDashboard),
        // jadi tiap tab harus punya datanya sendiri tanpa bergantung pada
        // URL mana yang sedang dibuka.
        View::composer('Admin.AdminDashboard', PanelAdminComposer::class);
    }
}
