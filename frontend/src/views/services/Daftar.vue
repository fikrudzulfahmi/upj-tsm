<script setup>
import { jasaApi } from '@/api'
import { formatRupiah } from '@/utils/format'
import { useAuthStore } from '@/stores/auth'
import MasterCrud from '@/components/MasterCrud.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'

const auth = useAuthStore()

const kolom = [
  { kunci: 'name', label: 'Pekerjaan' },
  { kunci: 'price', label: 'Harga', align: 'kanan' },
  { kunci: 'harga_member', label: 'Harga Member', align: 'kanan' },
  { kunci: 'status', label: 'Status' },
  { kunci: 'aksi', label: '', kelas: 'w-24 text-right' },
]

const bidang = [
  { kunci: 'name', label: 'Nama Pekerjaan', wajib: true, placeholder: 'mis. Servis Ringan + Ganti Oli' },
  { kunci: 'price', label: 'Harga', tipe: 'uang', wajib: true },
  { kunci: 'member_discount_percent', label: 'Diskon Member (%)', tipe: 'number', petunjuk: 'Kosongkan untuk memakai diskon global dari Pengaturan' },
  { kunci: 'description', label: 'Keterangan', tipe: 'textarea' },
  { kunci: 'is_active', label: 'Aktif (muncul saat membuat Form SA)', tipe: 'checkbox' },
]
</script>

<template>
  <MasterCrud
    judul="Master Pekerjaan"
    teks-tombol="Pekerjaan Baru"
    cari-placeholder="Cari nama pekerjaan…"
    kosong-teks="Belum ada pekerjaan."
    :kolom="kolom"
    :bidang="bidang"
    :nilai-awal="{ name: '', price: 0, member_discount_percent: '', description: '', is_active: true }"
    :ambil="(f) => jasaApi.daftar(f)"
    :simpan="(p) => jasaApi.simpan(p)"
    :ubah="(id, p) => jasaApi.ubah(id, p)"
    :hapus="jasaApi.hapus"
    :pesan-hapus="(b) => `Hapus pekerjaan '${b.name}'? Riwayat Form SA lama tidak berubah.`"
  >
    <template #sel-name="{ baris }">
      <p class="text-sm font-medium text-slate-700">{{ baris.name }}</p>
      <p v-if="baris.description" class="text-xs text-slate-400">{{ baris.description }}</p>
    </template>

    <template #sel-price="{ baris }">
      <span class="tabular text-sm">{{ formatRupiah(baris.price) }}</span>
    </template>

    <template #sel-harga_member="{ baris }">
      <span class="tabular text-sm text-emerald-600">{{ formatRupiah(baris.harga_member) }}</span>
      <p class="text-xs text-slate-400">diskon {{ baris.diskon_efektif }}%</p>
    </template>

    <template #sel-status="{ baris }">
      <BaseBadge :varian="baris.is_active ? 'sukses' : 'netral'">{{ baris.is_active ? 'Aktif' : 'Nonaktif' }}</BaseBadge>
    </template>
  </MasterCrud>
</template>
