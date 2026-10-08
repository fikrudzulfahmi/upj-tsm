<?php

use Illuminate\Support\Facades\Schedule;

// Periksa member hangus setiap hari 00:05 (D8 + bagian 6.3 blueprint).
Schedule::command('memberships:expire')->dailyAt('00:05');
