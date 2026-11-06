<?php

namespace App\Providers;

use App\Models\Complaint;
use App\Models\Event;
use App\Policies\ComplaintPolicy;
use App\Policies\EventPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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
        // Registrasi policy modul pengaduan (RBAC modul complaints).
        Gate::policy(Complaint::class, ComplaintPolicy::class);

        // Registrasi policy modul agenda/peminjaman ruangan (RBAC modul events).
        Gate::policy(Event::class, EventPolicy::class);

        // Tampilan pagination sesuai desain aplikasi (tabel pengajuan: data terbaru dulu, data lama via halaman).
        Paginator::defaultView('vendor.pagination.rapi');
        Paginator::defaultSimpleView('vendor.pagination.rapi');
    }
}
