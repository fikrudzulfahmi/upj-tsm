<script setup>
import { computed, onMounted, ref } from 'vue'
import { laporanApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { formatRupiah, formatTanggal, tanggalHariIni } from '@/utils/format'
import { KATEGORI_TRANSAKSI, labelMetode } from '@/utils/status'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import StatCard from '@/components/ui/StatCard.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import {
  BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Tooltip,
} from 'chart.js'
import { Bar } from 'vue-chartjs'

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend)

const ui = useUiStore()
const data = ref(null)
const memuat = ref(true)
const periode = ref('bulanan')
const tanggal = ref(tanggalHariIni())
const dari = ref(tanggalHariIni().slice(0, 8) + '01')
const sampai = ref(tanggalHariIni())
const tab = ref('kas')

async function muat() {
  memuat.value = true
  try {
    const res = await laporanApi.keuangan({
      period: periode.value,
      date: periode.value === 'rentang' ? dari.value : tanggal.value,
      sampai: periode.value === 'rentang' ? sampai.value : undefined,
    })
    data.value = res.data
  } catch (e) {
    ui.gagal(pesanGalat(e))
  } finally {
    memuat.value = false
  }
}

onMounted(muat)

function parameter() {
  return {
    period: periode.value,
    date: periode.value === 'rentang' ? dari.value : tanggal.value,
    sampai: periode.value === 'rentang' ? sampai.value : undefined,
  }
}

async function cetak(format) {
  try {
    if (format === 'excel') await laporanApi.keuanganExcel(parameter())
    else await laporanApi.keuanganPdf(parameter())
    ui.sukses(`Laporan ${format === 'excel' ? 'Excel' : 'PDF'} sedang diunduh.`)
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}

/** Halaman cetak langsung: parameter periode ikut dibawa agar hasilnya sama dengan di layar. */
const tautanCetak = computed(() => {
  const p = new URLSearchParams({ period: periode.value, date: tanggal.value })
  if (periode.value === 'rentang') {
    p.set('date', dari.value)
    p.set('sampai', sampai.value)
  }
  return `/cetak/laporan-keuangan?${p.toString()}`
})

const grafik = computed(() => ({
  labels: (data.value?.seri || []).map((s) => s.label),
  datasets: [
    { label: 'Pemasukan', data: (data.value?.seri || []).map((s) => s.pemasukan), backgroundColor: '#dc2626', borderRadius: 4 },
    { label: 'Pengeluaran', data: (data.value?.seri || []).map((s) => s.pengeluaran), backgroundColor: '#cbd5e1', borderRadius: 4 },
  ],
}))

const opsiGrafik = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
  scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } },
}
</script>

<template>
  <div class="space-y-4">
    <div class="card p-4">
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="label-form">Periode</label>
          <div class="flex flex-wrap gap-1">
            <button
              v-for="p in [
                { v: 'mingguan', l: 'Mingguan' },
                { v: 'bulanan', l: 'Bulanan' },
                { v: 'tahunan', l: 'Tahunan' },
                { v: 'rentang', l: 'Rentang' },
              ]"
              :key="p.v"
              class="rounded-lg border px-3 py-2 text-sm font-medium"
              :class="periode === p.v ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-300 text-slate-600 hover:border-brand-400'"
              @click="periode = p.v; muat()"
            >
              {{ p.l }}
            </button>
          </div>
        </div>
        <BaseInput v-if="periode !== 'rentang'" v-model="tanggal" label="Acuan Tanggal" tipe="date" class="w-44" @change="muat" />
        <template v-else>
          <BaseInput v-model="dari" label="Dari" tipe="date" class="w-40" />
          <BaseInput v-model="sampai" label="Sampai" tipe="date" class="w-40" />
          <BaseButton ikon="filter" @click="muat">Terapkan</BaseButton>
        </template>
        <div class="ml-auto flex gap-2">
          <BaseButton varian="garis" ikon="fileExcel" @click="cetak('excel')">Excel</BaseButton>
          <BaseButton ikon="fileText" @click="cetak('pdf')">Pratinjau PDF</BaseButton>
          <a :href="tautanCetak" target="_blank">
            <BaseButton ikon="printer">Cetak Langsung</BaseButton>
          </a>
        </div>
      </div>
    </div>

    <LoadingBlock v-if="memuat" />

    <template v-else-if="data">
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Pemasukan" :nilai="formatRupiah(data.ringkasan.total_pemasukan)" ikon="trendingUp" warna="emerald" />
        <StatCard label="Pengeluaran" :nilai="formatRupiah(data.ringkasan.total_pengeluaran)" ikon="trendingDown" warna="brand" />
        <StatCard
          label="Arus kas"
          :nilai="formatRupiah(data.ringkasan.arus_kas)"
          ikon="wallet"
          :warna="data.ringkasan.arus_kas >= 0 ? 'sky' : 'amber'"
          keterangan="Uang masuk − uang keluar"
        />
        <StatCard
          label="Laba kotor"
          :nilai="formatRupiah(data.ringkasan.laba_kotor)"
          ikon="chart"
          warna="violet"
          :keterangan="`HPP sparepart ${formatRupiah(data.ringkasan.hpp_sparepart)}`"
        />
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="text-sm font-semibold text-slate-700">Grafik {{ data.label_periode }}</h3>
          <span class="text-xs text-slate-400">{{ formatTanggal(data.dari) }} – {{ formatTanggal(data.sampai) }}</span>
        </div>
        <div class="h-64 p-3">
          <Bar :data="grafik" :options="opsiGrafik" />
        </div>
      </div>

      <div class="flex gap-1 border-b border-slate-200">
        <button
          class="border-b-2 px-3 py-2 text-sm font-medium"
          :class="tab === 'kas' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500'"
          @click="tab = 'kas'"
        >
          Arus Kas
        </button>
        <button
          class="border-b-2 px-3 py-2 text-sm font-medium"
          :class="tab === 'laba' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500'"
          @click="tab = 'laba'"
        >
          Laba Kotor
        </button>
        <button
          class="border-b-2 px-3 py-2 text-sm font-medium"
          :class="tab === 'transaksi' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500'"
          @click="tab = 'transaksi'"
        >
          Transaksi
        </button>
      </div>

      <!-- ARUS KAS -->
      <div v-if="tab === 'kas'" class="grid gap-4 lg:grid-cols-2">
        <div class="card">
          <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Rincian Pemasukan</h3></div>
          <table class="table-app">
            <thead><tr><th>Kategori</th><th class="text-right">Transaksi</th><th class="text-right">Total</th></tr></thead>
            <tbody>
              <tr v-if="!data.pemasukan.length"><td colspan="3" class="py-6 text-center text-xs text-slate-400">Tidak ada pemasukan.</td></tr>
              <tr v-for="p in data.pemasukan" :key="p.kategori">
                <td class="text-sm">{{ p.label }}</td>
                <td class="tabular text-right text-sm text-slate-500">{{ p.jumlah }}</td>
                <td class="tabular text-right text-sm font-medium">{{ formatRupiah(p.total) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-slate-50">
              <tr><td colspan="2" class="px-3 py-2 text-right font-semibold text-slate-600">Total</td>
                <td class="tabular px-3 py-2 text-right font-bold">{{ formatRupiah(data.ringkasan.total_pemasukan) }}</td></tr>
            </tfoot>
          </table>
        </div>

        <div class="card">
          <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Rincian Pengeluaran</h3></div>
          <table class="table-app">
            <thead><tr><th>Kategori</th><th class="text-right">Transaksi</th><th class="text-right">Total</th></tr></thead>
            <tbody>
              <tr v-if="!data.pengeluaran.length"><td colspan="3" class="py-6 text-center text-xs text-slate-400">Tidak ada pengeluaran.</td></tr>
              <tr v-for="p in data.pengeluaran" :key="p.kategori">
                <td class="text-sm">{{ p.label }}</td>
                <td class="tabular text-right text-sm text-slate-500">{{ p.jumlah }}</td>
                <td class="tabular text-right text-sm font-medium text-brand-600">{{ formatRupiah(p.total) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-slate-50">
              <tr><td colspan="2" class="px-3 py-2 text-right font-semibold text-slate-600">Total</td>
                <td class="tabular px-3 py-2 text-right font-bold text-brand-600">{{ formatRupiah(data.ringkasan.total_pengeluaran) }}</td></tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- LABA KOTOR -->
      <div v-else-if="tab === 'laba'" class="grid gap-4 lg:grid-cols-2">
        <div class="card">
          <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Perhitungan Laba Kotor</h3></div>
          <dl class="divide-y divide-slate-100 text-sm">
            <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">Pendapatan jasa</dt><dd class="tabular">{{ formatRupiah(data.ringkasan.pendapatan_jasa) }}</dd></div>
            <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">Penjualan sparepart</dt><dd class="tabular">{{ formatRupiah(data.ringkasan.pendapatan_sparepart) }}</dd></div>
            <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">HPP sparepart terjual</dt><dd class="tabular text-brand-600">- {{ formatRupiah(data.ringkasan.hpp_sparepart) }}</dd></div>
            <div class="flex justify-between bg-brand-50 px-4 py-3"><dt class="font-semibold text-brand-700">Laba kotor</dt>
              <dd class="tabular text-lg font-bold text-brand-700">{{ formatRupiah(data.ringkasan.laba_kotor) }}</dd></div>
          </dl>
          <p class="border-t border-slate-100 px-4 py-2 text-[11px] text-slate-400">
            Laba kotor = pendapatan jasa + penjualan sparepart − HPP sparepart yang benar-benar terjual.
          </p>
        </div>
        <div class="card">
          <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Statistik Servis</h3></div>
          <dl class="divide-y divide-slate-100 text-sm">
            <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">Jumlah SA dibayar</dt><dd class="tabular">{{ data.ringkasan.jumlah_sa_dibayar }}</dd></div>
            <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">Rata-rata nilai per SA</dt><dd class="tabular">{{ formatRupiah(data.ringkasan.rata_rata_per_sa) }}</dd></div>
          </dl>
          <div class="p-4">
            <EmptyState
              ikon="info"
              judul="Catatan"
              pesan="Bulan dengan belanja stok besar bisa terlihat rugi pada Arus Kas, padahal Laba Kotor tetap sehat. Gunakan kedua tab untuk menilai."
            />
          </div>
        </div>
      </div>

      <!-- TRANSAKSI -->
      <div v-else class="card">
        <div class="card-header">
          <h3 class="text-sm font-semibold text-slate-700">Daftar Transaksi Keuangan</h3>
          <span class="text-xs text-slate-400">Termasuk baris pembalik bila ada pembatalan</span>
        </div>
        <div class="overflow-x-auto">
          <table class="table-app">
            <thead><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th class="text-right">Nominal</th></tr></thead>
            <tbody>
              <tr v-if="!data.transaksi?.length"><td colspan="4" class="py-8 text-center text-xs text-slate-400">Tidak ada transaksi.</td></tr>
              <tr v-for="t in data.transaksi" :key="t.id">
                <td class="text-sm">{{ formatTanggal(t.transaction_date) }}</td>
                <td>
                  <BaseBadge :varian="t.type === 'income' ? 'sukses' : 'bahaya'" ukuran="sm">
                    {{ t.label_kategori }}
                  </BaseBadge>
                  <span v-if="t.reversal_of" class="ml-1 text-[10px] text-slate-400">pembalik</span>
                </td>
                <td class="text-sm text-slate-600">{{ t.description || '-' }}</td>
                <td class="tabular text-right text-sm" :class="t.type === 'income' ? 'text-emerald-600' : 'text-brand-600'">
                  {{ t.type === 'income' ? '+' : '-' }} {{ formatRupiah(t.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>