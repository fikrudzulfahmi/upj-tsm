<script setup>
import { mekanikApi } from '@/api'
import MasterCrud from '@/components/MasterCrud.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'

const kolom = [
  { kunci: 'name', label: 'Nama Mekanik' },
  { kunci: 'phone', label: 'No. HP' },
  { kunci: 'status', label: 'Status' },
  { kunci: 'aksi', label: '', kelas: 'w-24 text-right' },
]

const bidang = [
  { kunci: 'name', label: 'Nama Mekanik', wajib: true },
  { kunci: 'phone', label: 'No. HP', placeholder: '08xxxxxxxxxx' },
  { kunci: 'is_active', label: 'Aktif (muncul di pilihan Form SA)', tipe: 'checkbox' },
]
</script>

<template>
  <MasterCrud
    judul="Data Mekanik"
    teks-tombol="Mekanik Baru"
    cari-placeholder="Cari nama mekanik…"
    kosong-teks="Belum ada mekanik."
    :kolom="kolom"
    :bidang="bidang"
    :nilai-awal="{ name: '', phone: '', is_active: true }"
    :ambil="(f) => mekanikApi.daftar(f)"
    :simpan="(p) => mekanikApi.simpan(p)"
    :ubah="(id, p) => mekanikApi.ubah(id, p)"
    :hapus="mekanikApi.hapus"
    :pesan-hapus="(b) => `Hapus mekanik '${b.name}'?`"
  >
    <template #sel-phone="{ baris }">
      <span class="text-sm text-slate-600">{{ baris.phone || '-' }}</span>
    </template>
    <template #sel-status="{ baris }">
      <BaseBadge :varian="baris.is_active ? 'sukses' : 'netral'">{{ baris.is_active ? 'Aktif' : 'Nonaktif' }}</BaseBadge>
    </template>
  </MasterCrud>
</template>
