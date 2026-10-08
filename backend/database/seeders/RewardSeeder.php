<?php

namespace Database\Seeders;

use App\Models\Reward;
use App\Models\Sparepart;
use Illuminate\Database\Seeder;

class RewardSeeder extends Seeder
{
    public function run(): void
    {
        $oli = Sparepart::query()->where('name', 'like', '%oli%')->first();

        Reward::updateOrCreate(
            ['name' => 'Oli Gratis'],
            [
                'points_required' => 100,
                'sparepart_id' => $oli?->id,
                'qty' => 1,
                'is_active' => true,
            ]
        );
    }
}
