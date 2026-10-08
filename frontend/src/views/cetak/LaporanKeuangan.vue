<script setup>
/**
 * Halaman CETAK LANGSUNG laporan keuangan (tanpa PDF).
 * Parameter: ?period=mingguan|bulanan|tahunan|rentang&date=YYYY-MM-DD&sampai=YYYY-MM-DD
 * Otomatis memunculkan dialog cetak; `?cetak=0` untuk melihat dulu.
 */
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { laporanApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { usePengaturanStore } from '@/stores/pengaturan'
import { formatRupiah, formatTanggal, formatTanggalJam, tanggalHariIni } from '@/utils/format'
import BaseButton from '@/components/ui/BaseButton.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const pengaturan = usePengaturanStore()

const data = ref(null)
const memuat = ref(true)

const parameter = computed(() => ({
  period: route.query.period || 'bulanan',
  date: route.query.date || tanggalHariIni(),
  sampai: route.query.sampai || undefined,
}))

function cetakSekarang() {
  window.print()
}

onMounted(async () => {
  try {
    await pengaturan.muat()
    const res = await laporanApi.keuangan(parameter.value)
    data.value = res.data
    await nextTick()
    if (route.query.cetak !== '0') {
      setTimeout(() => window.print(), 600)
    }
  } catch {
    ui.gagal('Data laporan keuangan tidak dapat dimuat.')
  } finally {
    memuat.value = false
  }
})

async function cetakPdf() {
  try {
    await laporanApi.keuanganPdf(parameter.value)
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-100 py-6 print:bg-white print:py-0">
    <div class="no-print mx-auto mb-4 flex w-full max-w-4xl flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
      <div class="flex items-center gap-2 text-sm font-medium text-slate-700">
        <AppIcon nama="chart" :ukuran="17" class="text-brand-600" /> Cetak Laporan Keuangan
      </div>
      <div class="flex flex-wrap gap-2">
        <BaseButton varian="garis" ikon="arrowLeft" @click="router.back()">Kembali</BaseButton>
        <BaseButton varian="garis" ikon="fileText" @click="cetakPdf">Pratinjau PDF</BaseButton>
        <BaseButton ikon="printer" @click="cetakSekarang">Cetak Sekarang</BaseButton>
      </div>
    </div>

    <LoadingBlock v-if="memuat" teks="Menyiapkan laporan…" />

    <div v-else-if="data" class="mx-auto w-full max-w-4xl bg-white p-8 shadow-sm print:max-w-none print:p-0 print:shadow-none">
      <div class="flex items-start justify-between border-b-2 border-brand-600 pb-3">
        <div>
          <h1 class="text-xl font-bold text-brand-700">{{ pengaturan.namaBengkel }}</h1>
          <p class="text-xs text-slate-600">{{ pengaturan.alamatBengkel }}</p>
          <p class="text-xs text-slate-600">{{ pengaturan.teleponBengkel }}</p>
        </div>
        <div class="text-right">
          <p class="text-lg font-bold text-slate-800">LAPORAN KEUANGAN</p>
          <p class="text-xs text-slate-500">{{ data.label_periode }}</p>
          <p class="text-xs text-slate-500">
            {{ formatTanggal(data.dari) }} – {{ formatTanggal(data.sampai) }}
          </p>
          <p class="text-xs text-slate-500">Dicetak {{ formatTanggalJam(new Date().toISOString()) }}</p>
        </div>
      </div>

      <!-- Ringkasan -->
      <h2 class="mt-4 mb-1 text-sm font-semibold text-slate-700">Ringkasan</h2>
      <table class="w-full border-collapse text-sm">
        <tbody>
          <tr><td class="border border-slate-200 px-2 py-1">Total pemasukan</td><td class="tabular w-40 border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(data.ringkasan.total_pemasukan) }}</td></tr>
          <tr><td class="border border-slate-200 px-2 py-1">Total pengeluaran</td><td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(data.ringkasan.total_pengeluaran) }}</td></tr>
          <tr class="bg-brand-50 font-semibold text-brand-700">
            <td class="border border-slate-200 px-2 py-1">Arus kas (uang masuk − uang keluar)</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(data.ringkasan.arus_kas) }}</td>
          </tr>
          <tr><td class="border border-slate-200 px-2 py-1">Pendapatan jasa</td><td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(data.ringkasan.pendapatan_jasa) }}</td></tr>
          <tr><td class="border border-slate-200 px-2 py-1">Penjualan sparepart</td><td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(data.ringkasan.pendapatan_sparepart) }}</td></tr>
          <tr><td class="border border-slate-200 px-2 py-1">HPP sparepart terjual</td><td class="tabular border border-slate-200 px-2 py-1 text-right">- {{ formatRupiah(data.ringkasan.hpp_sparepart) }}</td></tr>
          <tr class="bg-brand-50 font-semibold text-brand-700">
            <td class="border border-slate-200 px-2 py-1">Laba kotor</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(data.ringkasan.laba_kotor) }}</td>
          </tr>
          <tr><td class="border border-slate-200 px-2 py-1">Jumlah SA dibayar</td><td class="tabular border border-slate-200 px-2 py-1 text-right">{{ data.ringkasan.jumlah_sa_dibayar }}</td></tr>
          <tr><td class="border border-slate-200 px-2 py-1">Rata-rata nilai per SA</td><td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(data.ringkasan.rata_rata_per_sa) }}</td></tr>
        </tbody>
      </table>

      <!-- Rincian -->
      <div class="mt-5 grid grid-cols-2 gap-5">
        <div>
          <h2 class="mb-1 text-sm font-semibold text-slate-700">Rincian Pemasukan</h2>
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <th class="border border-slate-200 px-2 py-1">Kategori</th>
                <th class="w-28 border border-slate-200 px-2 py-1 text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in data.pemasukan" :key="p.kategori">
                <td class="border border-slate-200 px-2 py-1">{{ p.label }}</td>
                <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(p.total) }}</td>
              </tr>
              <tr v-if="!data.pemasukan.length">
                <td colspan="2" class="border border-slate-200 px-2 py-2 text-center text-xs text-slate-400">Tidak ada</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div>
          <h2 class="mb-1 text-sm font-semibold text-slate-700">Rincian Pengeluaran</h2>
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <th class="border border-slate-200 px-2 py-1">Kategori</th>
                <th class="w-28 border border-slate-200 px-2 py-1 text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in data.pengeluaran" :key="p.kategori">
                <td class="border border-slate-200 px-2 py-1">{{ p.label }}</td>
                <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(p.total) }}</td>
              </tr>
              <tr v-if="!data.pengeluaran.length">
                <td colspan="2" class="border border-slate-200 px-2 py-2 text-center text-xs text-slate-400">Tidak ada</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Rekap periode -->
      <h2 class="mt-5 mb-1 text-sm font-semibold text-slate-700">Rekap per Periode</h2>
      <table class="w-full border-collapse text-sm">
        <thead>
          <tr class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <th class="border border-slate-200 px-2 py-1">Periode</th>
            <th class="border border-slate-200 px-2 py-1 text-right">Pemasukan</th>
            <th class="border border-slate-200 px-2 py-1 text-right">Pengeluaran</th>
            <th class="border border-slate-200 px-2 py-1 text-right">Selisih</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in data.seri" :key="s.label">
            <td class="border border-slate-200 px-2 py-1">{{ s.label }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(s.pemasukan) }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(s.pengeluaran) }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right" :class="s.pemasukan - s.pengeluaran < 0 ? 'text-brand-600' : ''">
              {{ formatRupiah(s.pemasukan - s.pengeluaran) }}
            </td>
          </tr>
        </tbody>
      </table>

      <p class="mt-3 text-[11px] text-slate-500">
        Catatan: pembelian sparepart dicatat sebagai pengeluaran dan penjualan sparepart sebagai pemasukan,
        sehingga bulan dengan belanja stok besar dapat terlihat minus pada Arus Kas. Laba kotor
        (pendapatan jasa + penjualan sparepart − HPP terjual) menunjukkan kondisi sebenarnya.
      </p>

      <div class="mt-10 grid grid-cols-2 gap-6 text-center text-xs text-slate-600">
        <div>
          <p>Dibuat oleh</p>
          <p class="mt-12 border-t border-slate-300 pt-1">( kasir )</p>
        </div>
        <div>
          <p>{{ pengaturan.namaBengkel }}</p>
          <p class="mt-12 border-t border-slate-300 pt-1">( pemilik / manager )</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
@page {
  size: A4;
  margin: 12mm;
}
</style>
