<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('roles')->whereNull('customer_id');

        if ($cari = $request->query('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$cari}%")
                ->orWhere('email', 'like', "%{$cari}%")
                ->orWhere('phone', 'like', "%{$cari}%"));
        }

        if ($request->filled('role')) {
            $query->role($request->query('role'));
        }

        $halaman = $query->orderBy('name')->paginate((int) $request->query('per_page', 15));

        return $this->halaman($halaman, UserResource::class);
    }

    public function store(SimpanUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make((string) $request->input('password')),
            'is_active' => $request->boolean('is_active', true),
            'must_change_password' => true,
        ]);

        $user->syncRoles([$request->input('role')]);

        return $this->dibuat(new UserResource($user->load('roles')), 'Pengguna berhasil ditambahkan.');
    }

    public function show(User $user): JsonResponse
    {
        return $this->sukses(new UserResource($user->load('roles')));
    }

    public function update(SimpanUserRequest $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id && ! $request->boolean('is_active', true)) {
            return $this->galat('Anda tidak dapat menonaktifkan akun sendiri.', 422);
        }

        $data = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make((string) $request->input('password'));
            $data['must_change_password'] = true;
        }

        $user->update($data);
        $user->syncRoles([$request->input('role')]);

        return $this->sukses(new UserResource($user->fresh('roles')), 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->galat('Anda tidak dapat menghapus akun sendiri.', 422);
        }

        if ($user->hasRole('owner') && User::role('owner')->count() <= 1) {
            return $this->galat('Akun owner terakhir tidak dapat dihapus.', 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->sukses(null, 'Pengguna berhasil dihapus.');
    }
}
