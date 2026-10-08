<script setup>
/** KASIR — riwayat penerimaan uang + rekap per metode bayar. */
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { cetakApi, kasirApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { useDaftar } from '@/composables/useDaftar'
import { formatRupiah, formatTanggalJam, tanggalHariIni } from '@/utils/format'
import { labelMetode, METODE_BAYAR } from '@/utils/status'
import BaseTable from '@/components/ui/BaseTable.vue'
import TombolCetakThermal from '@/components/TombolCetakThermal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import StatCard from '@/components/ui/StatCard.vue'
import Pagination from '@/components/ui/Pagination.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const ui = useUiStore()

const kolom = [
  { kunci: 'paid_at', label: 'Waktu Bayar' },
  { kunci: 'sa_no', label: 'No. SA' },
  { kunci: 'pelanggan', label: 'Pelanggan' },
  { kunci: 'metode', label: 'Metode' },
  { kunci: 'total', label: 'Diterima', align: 'kanan' },
  { kunci: 'aksi', label: '', kelas: 'w-28 text-right' },
]

const daftar = useDaftar((f) => kasirApi.transaksi(f), {
  filter: { dari: tanggalHariIni(), sampai: tanggalHariIni(), payment_method: '' },
})
// Rekap per metode ikut pada `meta` respons daftar → cukup satu permintaan.
const rekap = computed(() => daftar.meta.rekap || null)

onMounted(daftar.muat)
const muat = daftar.muat

/** Cetak 58mm lewat dialog peramban. */
function cetak58(baris) {
  window.open(`/cetak/nota-thermal/${baris.id}`, '_blank')
}

/** Cetak nota A4 lewat dialog peramban. */
function cetakLangsung(baris) {
  window.open(`/cetak/nota/${baris.id}`, '_blank')
}

async function cetakPdf(baris) {
  try {
    await cetakApi.notaSa(baris.id)
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}

const hariIni = computed(() => daftar.filter.dari === tanggalHariIni() && daftar.filter.sampai === tanggalHariIni())
</script>

<template>
  <div class="space-y-4">
    <div v-if="rekap" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <StatCard label="Jumlah transaksi" :nilai="rekap.jumlah_transaksi" ikon="handCoins" :keterangan="hariIni ? 'Hari ini' : 'Periode pilihan'" />
      <StatCard label="Total diterima" :nilai="formatRupiah(rekap.total)" ikon="wallet" warna="emerald" />
      <div class="card p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Per metode bayar</p>
        <ul class="mt-1 space-y-0.5 text-sm">
          <li v-for="m in rekap.per_metode" :key="m.metode" class="flex justify-between">
            <span class="text-slate-500">{{ m.label }} <span class="text-slate-400">({{ m.jumlah }})</span></span>
            <span class="tabular" :class="m.jumlah ? 'font-medium text-slate-700' : 'text-slate-300'">{{ formatRupiah(m.total) }}</span>
          </li>
        </ul>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="flex flex-wrap items-end gap-2">
          <BaseInput v-model="daftar.filter.dari" label="Dari" tipe="date" class="w-40" />
          <BaseInput v-model="daftar.filter.sampai" label="Sampai" tipe="date" class="w-40" />
          <BaseSelect
            v-model="daftar.filter.payment_method"
            label="Metode"
            :opsi="METODE_BAYAR"
            placeholder="Semua metode"
            class="w-40"
          />
          <BaseButton ikon="filter" @click="muat">Terapkan</BaseButton>
        </div>
        <BaseButton varian="garis" ikon="refresh" @click="muat">Muat Ulang</BaseButton>
      </div>

      <BaseTable :kolom="kolom" :baris="daftar.baris.value" :muat="daftar.memuat.value" kosong-teks="Belum ada transaksi pada periode ini.">
        <template #sel-paid_at="{ baris }">
          <span class="text-sm">{{ formatTanggalJam(baris.paid_at) }}</span>
        </template>

        <template #sel-sa_no="{ baris }">
          <RouterLink :to="`/service-orders/${baris.id}`" class="font-medium text-slate-700 hover:text-brand-600">
            {{ baris.sa_no }}
          </RouterLink>
        </template>

        <template #sel-pelanggan="{ baris }">
          <p class="text-sm">{{ baris.customer_name }}</p>
          <p class="text-xs text-slate-400">{{ baris.plate_number }}</p>
        </template>

        <template #sel-metode="{ baris }">
          <BaseBadge :varian="baris.payment_method === 'cash' ? 'sukses' : baris.payment_method === 'qris' ? 'info' : 'netral'" ukuran="sm">
            <AppIcon :nama="baris.payment_method === 'cash' ? 'banknote' : 'creditCard'" :ukuran="11" />
            {{ labelMetode(baris.payment_method) }}
          </BaseBadge>
        </template>

        <template #sel-total="{ baris }">
          <span class="tabular text-sm font-medium">{{ formatRupiah(baris.paid_amount || baris.grand_total) }}</span>
        </template>

        <template #sel-aksi="{ baris }">
          <div class="flex justify-end gap-1">
            <TombolCetakThermal :sa="baris" />
            <BaseButton ukuran="ikon" varian="halus" ikon="printer" title="Cetak 58mm lewat dialog peramban" @click="cetak58(baris)" />
            <BaseButton ukuran="ikon" varian="garis" ikon="fileText" title="Pratinjau PDF" @click="cetakPdf(baris)" />
          </div>
        </template>
      </BaseTable>

      <Pagination :halaman="daftar.meta.halaman" :per-halaman="daftar.meta.per_halaman" :total="daftar.meta.total" @ubah="daftar.keHalaman" />
    </div>
  </div>
</template>