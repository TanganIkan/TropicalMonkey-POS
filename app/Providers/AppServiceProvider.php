<?php

namespace App\Providers;

use App\Http\Middleware\EnsureValidPosSession;
use Carbon\Carbon;
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
        // Tanggal & durasi tampil dalam Bahasa Indonesia (mis. "Okt", "10 menit")
        Carbon::setLocale('id');

        // Jalankan juga pada setiap aksi Livewire (wire:click, dll), bukan hanya saat halaman dibuka
        Livewire::addPersistentMiddleware([
            EnsureValidPosSession::class,
        ]);
    }
}
