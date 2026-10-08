<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\MembantuBengkel;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use MembantuBengkel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_berhasil_memakai_email_dan_mengembalikan_token(): void
    {
        $user = User::query()->create([
            'name' => 'Kasir Satu', 'email' => 'kasir@bengkel.test',
            'password' => Hash::make('rahasia123'), 'is_active' => true,
        ]);
        $user->assignRole('kasir');

        $res = $this->postJson('/api/v1/auth/login', ['login' => 'kasir@bengkel.test', 'password' => 'rahasia123']);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name'], 'roles', 'permissions']]);

        $this->assertNotEmpty($res->json('data.token'));
        $this->assertContains('kasir', $res->json('data.roles'));
    }

    public function test_login_memakai_nomor_hp_yang_dinormalisasi(): void
    {
        $user = User::query()->create([
            'name' => 'Member Satu', 'phone' => '081234567890',
            'password' => Hash::make('rahasia123'), 'is_active' => true,
        ]);
        $user->assignRole('member');

        // Dikirim dalam format 62…, harus tetap cocok dengan 0812…
        $this->postJson('/api/v1/auth/login', ['login' => '6281234567890', 'password' => 'rahasia123'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_password_salah_ditolak_422(): void
    {
        User::query()->create([
            'name' => 'Kasir', 'email' => 'kasir@bengkel.test',
            'password' => Hash::make('rahasia123'), 'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', ['login' => 'kasir@bengkel.test', 'password' => 'salah'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_akun_nonaktif_ditolak_dengan_kode_akun_nonaktif(): void
    {
        $user = User::query()->create([
            'name' => 'Kasir Nonaktif', 'email' => 'nonaktif@bengkel.test',
            'password' => Hash::make('rahasia123'), 'is_active' => false,
        ]);
        $user->assignRole('kasir');

        $this->postJson('/api/v1/auth/login', ['login' => 'nonaktif@bengkel.test', 'password' => 'rahasia123'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'AKUN_NONAKTIF');
    }

    public function test_akses_tanpa_login_ditolak_401(): void
    {
        $this->getJson('/api/v1/customers')->assertStatus(401);
    }

    public function test_kasir_tidak_boleh_mengakses_menu_pengguna_403(): void
    {
        $this->actingAs($this->buatPengguna('kasir'), 'sanctum');

        $this->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertJsonPath('code', 'TIDAK_BERWENANG');
    }

    public function test_ganti_password_membersihkan_flag_wajib_ganti(): void
    {
        $user = $this->buatPengguna('kasir');
        $user->update(['must_change_password' => true]);
        $this->actingAs($user->fresh(), 'sanctum');

        $this->postJson('/api/v1/auth/change-password', [
            'password' => 'passwordBaru123',
            'password_confirmation' => 'passwordBaru123',
        ])->assertOk()->assertJsonPath('data.must_change_password', false);

        $this->assertFalse($user->fresh()->must_change_password);
    }
}
