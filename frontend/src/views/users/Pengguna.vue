<script setup>
import { penggunaApi } from '@/api'
import { formatTanggal } from '@/utils/format'
import MasterCrud from '@/components/MasterCrud.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'

const PERAN = [
  { value: 'owner', label: 'Owner — semua akses' },
  { value: 'admin', label: 'Admin bengkel' },
  { value: 'kasir', label: 'Kasir / Service Advisor' },
  { value: 'gudang', label: 'Staf gudang' },
]

const kolom = [
  { kunci: 'name', label: 'Nama' },
  { kunci: 'kontak', label: 'Kontak' },
  { kunci: 'peran', label: 'Peran' },
  { kunci: 'status', label: 'Status' },
  { kunci: 'aksi', label: '', kelas: 'w-24 text-right' },
]

const bidang = [
  { kunci: 'name', label: 'Nama Pengguna', wajib: true },
  { kunci: 'email', label: 'Email', placeholder: 'nama@bengkel.test' },
  { kunci: 'phone', label: 'No. HP', placeholder: '08xxxxxxxxxx' },
  { kunci: 'role', label: 'Peran', tipe: 'select', opsi: PERAN, wajib: true, kosong: false },
  { kunci: 'password', label: 'Password', tipe: 'password', petunjuk: 'Minimal 8 karakter (kosongkan saat mengubah bila tidak diganti)' },
  { kunci: 'password_confirmation', label: 'Ulangi Password', tipe: 'password' },
  { kunci: 'is_active', label: 'Akun aktif (boleh masuk)', tipe: 'checkbox' },
]
</script>

<template>
  <MasterCrud
    judul="Pengguna & Peran"
    teks-tombol="Pengguna Baru"
    cari-placeholder="Cari nama / email / HP…"
    kosong-teks="Belum ada pengguna."
    :kolom="kolom"
    :bidang="bidang"
    :nilai-awal="{ name: '', email: '', phone: '', role: 'kasir', password: '', password_confirmation: '', is_active: true }"
    :ambil="(f) => penggunaApi.daftar(f)"
    :simpan="(p) => penggunaApi.simpan(p)"
    :ubah="(id, p) => penggunaApi.ubah(id, p)"
    :hapus="penggunaApi.hapus"
    :pesan-hapus="(b) => `Hapus pengguna '${b.name}'? Akun portal member dikelola dari data pelanggan.`"
  >
    <template #sel-name="{ baris }">
      <p class="text-sm font-medium text-slate-700">{{ baris.name }}</p>
      <p class="text-xs text-slate-400">Dibuat {{ formatTanggal(baris.created_at) }}</p>
    </template>

    <template #sel-kontak="{ baris }">
      <p class="text-sm text-slate-600">{{ baris.email || '-' }}</p>
      <p class="text-xs text-slate-400">{{ baris.phone || '-' }}</p>
    </template>

    <template #sel-peran="{ baris }">
      <BaseBadge varian="brand" ukuran="sm">{{ (baris.roles || []).join(', ') || '-' }}</BaseBadge>
      <p v-if="baris.must_change_password" class="mt-0.5 text-[11px] text-amber-600">belum ganti password awal</p>
    </template>

    <template #sel-status="{ baris }">
      <BaseBadge :varian="baris.is_active ? 'sukses' : 'bahaya'">{{ baris.is_active ? 'Aktif' : 'Nonaktif' }}</BaseBadge>
    </template>
  </MasterCrud>
</template>
