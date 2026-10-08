<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
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
        // Batas 191 karakter = 764 byte pada utf8mb4, aman untuk InnoDB dengan format
        // baris COMPACT (cap indeks 767 byte) yang masih umum di hosting bersama.
        // Ini pelengkap dari 'engine' => 'InnoDB' di config/database.php: keduanya
        // dipasang sekaligus supaya migrasi tidak gagal dua kali di server.
        Schema::defaultStringLength(191);
    }
}
