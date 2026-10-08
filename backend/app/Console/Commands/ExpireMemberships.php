<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Services\MembershipService;
use Illuminate\Console\Command;

class ExpireMemberships extends Command
{
    protected $signature = 'memberships:expire';

    protected $description = 'Tandai membership yang melewati tanggal berakhir sebagai hangus (opsi: poin ikut hangus).';

    public function handle(MembershipService $membershipService): int
    {
        $kedaluwarsa = Membership::query()
            ->where('status', 'active')
            ->whereDate('expires_at', '<', today())
            ->get();

        if ($kedaluwarsa->isEmpty()) {
            $this->info('Tidak ada membership yang kedaluwarsa.');

            return self::SUCCESS;
        }

        foreach ($kedaluwarsa as $membership) {
            $membershipService->hanguskan($membership);
        }

        $this->info('Membership dihanguskan: '.$kedaluwarsa->count().' pelanggan.');

        return self::SUCCESS;
    }
}
