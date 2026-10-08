<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OwnerUserSeeder extends Seeder
{
    /** Akun owner awal — kredensial diambil dari .env, tidak pernah di-hardcode. */
    public function run(): void
    {
        $email = env('OWNER_EMAIL');
        $password = env('OWNER_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('OWNER_EMAIL / OWNER_PASSWORD belum diisi di .env — akun owner dilewati.');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('OWNER_NAME', 'Pemilik Bengkel'),
                'password' => Hash::make($password),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );

        if (! $user->hasRole('owner')) {
            $user->assignRole('owner');
        }

        $this->command?->info("Akun owner siap: {$email}");
    }
}
