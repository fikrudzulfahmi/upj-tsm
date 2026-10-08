<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GantiPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\CustomerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $kunci = 'masuk:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            return $this->galat(
                'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.',
                429,
                [],
                'TERLALU_BANYAK_PERCOBAAN'
            );
        }

        $login = trim((string) $request->input('login'));

        $user = User::query()->where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        // Login memakai nomor HP: normalisasi dulu (0812… / 62812… / +62…).
        if (! $user) {
            $hp = app(CustomerService::class)->normalisasiHp($login);
            if ($hp) {
                $user = User::query()->where('phone', $hp)->first();
            }
        }

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::hit($kunci, 60);

            throw ValidationException::withMessages([
                'login' => 'Email/No. HP atau password salah.',
            ]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($kunci, 60);

            return $this->galat('Akun Anda dinonaktifkan. Hubungi pemilik bengkel.', 403, [], 'AKUN_NONAKTIF');
        }

        RateLimiter::clear($kunci);

        $token = $user->createToken('spa')->plainTextToken;

        return $this->sukses($this->bentukProfil($user, $token), 'Berhasil masuk.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->sukses($this->bentukProfil($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->sukses(null, 'Anda telah keluar.');
    }

    public function changePassword(GantiPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->must_change_password && ! Hash::check((string) $request->input('password_lama'), $user->password)) {
            throw ValidationException::withMessages([
                'password_lama' => 'Password lama tidak sesuai.',
            ]);
        }

        $user->update([
            'password' => Hash::make((string) $request->input('password')),
            'must_change_password' => false,
        ]);

        return $this->sukses([
            'must_change_password' => false,
        ], 'Password berhasil diperbarui.');
    }

    /** @return array<string, mixed> */
    private function bentukProfil(User $user, ?string $token = null): array
    {
        $user->loadMissing('roles', 'permissions');

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => (bool) $user->is_active,
                'must_change_password' => (bool) $user->must_change_password,
                'customer_id' => $user->customer_id,
            ],
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
