<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web (kosong — aplikasi ini API-only)
|--------------------------------------------------------------------------
| Sengaja tanpa closure: `php artisan route:cache` tidak dapat menyerialkan
| route berbasis closure, dan proses deploy menjalankan route:cache.
*/
