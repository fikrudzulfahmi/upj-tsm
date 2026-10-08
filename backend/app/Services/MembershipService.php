<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Exceptions\AturanBisnisException;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MembershipService
{
    public function __construct(
        private readonly DocumentNumberService $nomor,
        private readonly SettingService $setting,
        private readonly PointService $poin,
    ) {}

    /**
     * Jadikan pelanggan sebagai member + buat akun portal (login = No. HP).
     *
     * @return array{membership: Membership, password_awal: ?string}
     */
    public function aktifkan(Customer $customer, ?Carbon $mulai = null): array
    {
        $mulai = $mulai?->copy() ?? today();

        return DB::transaction(function () use ($customer, $mulai) {
            $membership = $customer->membership;

            if ($membership && $membership->status === MembershipStatus::Active->value && $membership->expires_at?->gte(today())) {
                throw new AturanBisnisException('Pelanggan ini sudah menjadi member aktif.');
            }

            $durasi = $this->setting->int('membership_duration_months', 6);
            $kadaluarsa = $mulai->copy()->addMonths($durasi)->toDateString();

            if ($membership) {
                $membership->update([
                    'status' => MembershipStatus::Active->value,
                    'started_at' => $membership->started_at ?? $mulai->toDateString(),
                    'expires_at' => $kadaluarsa,
                ]);
                $passwordAwal = null;
            } else {
                $membership = Membership::create([
                    'customer_id' => $customer->id,
                    'member_no' => $this->nomor->nomorUrut('M', false, 6),
                    'status' => MembershipStatus::Active->value,
                    'started_at' => $mulai->toDateString(),
                    'expires_at' => $kadaluarsa,
                    'points_balance' => 0,
                ]);
                $passwordAwal = $this->siapkanAkunPortal($customer);
            }

            return ['membership' => $membership->fresh(), 'password_awal' => $passwordAwal];
        });
    }

    /** Perpanjang dari servis yang selesai (D2): expires_at = tanggal servis + durasi. */
    public function perpanjangDariServis(Customer $customer, ?Carbon $tanggal = null): ?Membership
    {
        $membership = $customer->membership;

        if (! $membership) {
            return null;
        }

        $tanggal = $tanggal?->copy() ?? today();
        $durasi = $this->setting->int('membership_duration_months', 6);

        $membership->update([
            'status' => MembershipStatus::Active->value,
            'expires_at' => $tanggal->copy()->addMonths($durasi)->toDateString(),
            'last_service_at' => $tanggal->toDateString(),
        ]);

        return $membership->fresh();
    }

    /**
     * Perpanjang manual oleh kasir/admin (tanpa menunggu servis).
     * Dihitung dari tanggal berakhir yang masih berlaku, atau dari hari ini bila sudah lewat.
     */
    public function perpanjangManual(Customer $customer, ?Carbon $tanggal = null): Membership
    {
        $membership = $customer->membership;

        if (! $membership) {
            throw new AturanBisnisException('Pelanggan ini belum terdaftar sebagai member.');
        }

        $durasi = $this->setting->int('membership_duration_months', 6);
        $acuan = $membership->expires_at && $membership->expires_at->greaterThan(today())
            ? $membership->expires_at->copy()
            : ($tanggal?->copy() ?? today());

        $membership->update([
            'status' => MembershipStatus::Active->value,
            'expires_at' => $acuan->copy()->addMonths($durasi)->toDateString(),
        ]);

        return $membership->fresh();
    }

    /** Hanguskan membership (dipanggil scheduler harian bila lewat tanggal). */
    public function hanguskan(Membership $membership): Membership
    {
        return DB::transaction(function () use ($membership) {
            if ($this->setting->bool('member_points_expire_with_membership', true)) {
                $this->poin->hanguskan($membership);
            }

            $membership->update(['status' => MembershipStatus::Expired->value]);

            return $membership->fresh();
        });
    }

    /** Persen diskon efektif untuk pelanggan (membership → setting global). */
    public function persenDiskon(?Membership $membership): int
    {
        if (! $membership || ! $membership->aktif()) {
            return 0;
        }

        return (int) ($membership->discount_percent_override
            ?? $this->setting->int('member_discount_percent', 10));
    }

    /** Buat akun portal; kembalikan password awal (hanya ditampilkan sekali). */
    private function siapkanAkunPortal(Customer $customer): ?string
    {
        if (! $customer->phone) {
            return null; // tanpa nomor HP tidak bisa login (login memakai No. HP)
        }

        $sudahAda = User::query()->where('customer_id', $customer->id)->first();

        if ($sudahAda) {
            if (! $sudahAda->hasRole('member')) {
                $sudahAda->assignRole('member');
            }

            return null;
        }

        $passwordAwal = Str::upper(Str::random(4)).random_int(1000, 9999);

        $user = User::create([
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => null,
            'password' => Hash::make($passwordAwal),
            'is_active' => true,
            'must_change_password' => true,
            'customer_id' => $customer->id,
        ]);

        $user->assignRole('member');

        return $passwordAwal;
    }
}