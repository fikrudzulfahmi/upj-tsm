<?php

namespace Database\Seeders;

use App\Models\CheckupTemplate;
use Illuminate\Database\Seeder;

class CheckupTemplateSeeder extends Seeder
{
    public const TEMPLATE = [
        'motor' => [
            'Mesin' => ['Oli mesin', 'Filter udara', 'Busi', 'Suara mesin', 'Rantai / CVT', 'Kebocoran oli'],
            'Rem' => ['Kampas rem depan', 'Kampas rem belakang', 'Minyak rem', 'Tuas & kabel rem'],
            'Kelistrikan' => ['Lampu depan', 'Lampu rem & sein', 'Klakson', 'Aki & terminal', 'Starter / engkol'],
            'Ban & Kaki-kaki' => ['Ban depan', 'Ban belakang', 'Tekanan angin', 'Shockbreaker', 'Komstir / bearing'],
            'Cairan' => ['Oli gardan', 'Cairan radiator', 'Minyak rem', 'Air wiper'],
            'Body' => ['Kaca spion', 'Panel & cat', 'Jok', 'Kunci kontak', 'Speedometer'],
        ],
        'mobil' => [
            'Mesin' => ['Oli mesin', 'Filter oli', 'Filter udara', 'Busi', 'Tali kipas', 'Kebocoran oli'],
            'Rem' => ['Kampas rem depan', 'Kampas rem belakang', 'Minyak rem', 'Rem tangan'],
            'Kelistrikan' => ['Lampu depan', 'Lampu rem & sein', 'Klakson', 'Aki & terminal', 'Wiper', 'AC'],
            'Ban & Kaki-kaki' => ['Tekanan angin 4 ban', 'Keausan ban', 'Shockbreaker', 'Spooring / balancing'],
            'Cairan' => ['Air radiator', 'Oli transmisi', 'Minyak power steering', 'Air wiper'],
            'Body' => ['Body & cat', 'Kaca & karet pintu', 'Kunci & central lock', 'Interior'],
        ],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATE as $tipe => $kategori) {
            $template = CheckupTemplate::updateOrCreate(
                ['name' => 'General Check Up '.ucfirst($tipe), 'vehicle_type' => $tipe],
                ['is_active' => true]
            );

            if ($template->items()->exists()) {
                continue;
            }

            $urut = 1;
            foreach ($kategori as $namaKategori => $items) {
                foreach ($items as $nama) {
                    $template->items()->create([
                        'category' => $namaKategori,
                        'name' => $nama,
                        'sort_order' => $urut++,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
