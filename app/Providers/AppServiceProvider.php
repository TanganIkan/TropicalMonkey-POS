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
        // Aplikasi berbahasa Indonesia: pesan validasi, pagination, tanggal & durasi (mis. "Okt", "10 menit")
        app()->setLocale('id');
        Carbon::setLocale('id');

        // Jalankan juga pada setiap aksi Livewire (wire:click, dll), bukan hanya saat halaman dibuka
        Livewire::addPersistentMiddleware([
            EnsureValidPosSession::class,
        ]);
    }
}
