<script setup>
/**
 * Halaman CETAK LANGSUNG laporan stok (tanpa PDF).
 * Otomatis memunculkan dialog cetak; `?cetak=0` untuk melihat dulu.
 */
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { laporanApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { usePengaturanStore } from '@/stores/pengaturan'
import { formatRupiah, formatTanggalJam } from '@/utils/format'
import BaseButton from '@/components/ui/BaseButton.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const pengaturan = usePengaturanStore()

const data = ref(null)
const memuat = ref(true)
const hanyaMenipis = ref(route.query.menipis === '1')

const baris = computed(() => data.value?.baris || [])

/** Dipanggil dari tombol; `window` tidak tersedia langsung di template Vue. */
function cetakSekarang() {
  window.print()
}

async function muat() {
  memuat.value = true
  try {
    await pengaturan.muat()
    const res = await laporanApi.stok({ menipis: hanyaMenipis.value ? 1 : undefined })
    data.value = res.data
    await nextTick()
    if (route.query.cetak !== '0') {
      setTimeout(() => window.print(), 600)
    }
  } catch {
    ui.gagal('Data laporan stok tidak dapat dimuat.')
  } finally {
    memuat.value = false
  }
}

onMounted(muat)

async function cetakPdf() {
  try {
    await laporanApi.stokPdf({ menipis: hanyaMenipis.value ? 1 : undefined })
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-100 py-6 print:bg-white print:py-0">
    <div class="no-print mx-auto mb-4 flex w-full max-w-4xl flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
      <div class="flex items-center gap-2 text-sm font-medium text-slate-700">
        <AppIcon nama="boxes" :ukuran="17" class="text-brand-600" /> Cetak Laporan Stok
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <label class="flex items-center gap-1.5 text-xs text-slate-600">
          <input v-model="hanyaMenipis" type="checkbox" @change="muat" /> Hanya stok menipis
        </label>
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
          <p class="text-lg font-bold text-slate-800">LAPORAN STOK SPAREPART</p>
          <p class="text-xs text-slate-500">{{ hanyaMenipis ? 'Hanya stok menipis' : 'Seluruh item' }}</p>
          <p class="text-xs text-slate-500">Dicetak {{ formatTanggalJam(new Date().toISOString()) }}</p>
        </div>
      </div>

      <table class="mt-4 w-full border-collapse text-sm">
        <thead>
          <tr class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <th class="w-10 border border-slate-200 px-2 py-1">No</th>
            <th class="border border-slate-200 px-2 py-1">Nama Sparepart</th>
            <th class="w-20 border border-slate-200 px-2 py-1">Satuan</th>
            <th class="w-20 border border-slate-200 px-2 py-1 text-right">Stok</th>
            <th class="w-16 border border-slate-200 px-2 py-1 text-right">Min</th>
            <th class="w-28 border border-slate-200 px-2 py-1 text-right">Harga Beli</th>
            <th class="w-28 border border-slate-200 px-2 py-1 text-right">Harga Jual</th>
            <th class="w-32 border border-slate-200 px-2 py-1 text-right">Nilai Persediaan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(b, i) in baris" :key="b.id">
            <td class="border border-slate-200 px-2 py-1 text-center">{{ i + 1 }}</td>
            <td class="border border-slate-200 px-2 py-1">
              {{ b.name }}<span v-if="b.menipis" class="text-xs font-semibold text-brand-600"> *</span>
            </td>
            <td class="border border-slate-200 px-2 py-1">{{ b.unit }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right" :class="b.menipis ? 'font-semibold text-brand-600' : ''">{{ b.stock }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right text-slate-500">{{ b.min_stock }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(b.buy_price) }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(b.sell_price) }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(b.nilai_persediaan) }}</td>
          </tr>
          <tr v-if="!baris.length">
            <td colspan="8" class="border border-slate-200 px-2 py-3 text-center text-xs text-slate-400">Tidak ada data.</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="bg-brand-50 font-semibold text-brand-700">
            <td colspan="7" class="border border-slate-200 px-2 py-1 text-right">Total nilai persediaan</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(data.ringkasan.total_nilai_persediaan) }}</td>
          </tr>
        </tfoot>
      </table>

      <div class="mt-3 grid grid-cols-3 gap-3 text-sm">
        <p>Jumlah item: <strong>{{ data.ringkasan.jumlah_item }}</strong></p>
        <p>Item menipis: <strong class="text-brand-600">{{ data.ringkasan.jumlah_menipis }}</strong></p>
        <p class="text-xs text-slate-500">* = stok mencapai/melampaui batas minimum</p>
      </div>

      <div class="mt-10 grid grid-cols-2 gap-6 text-center text-xs text-slate-600">
        <div>
          <p>Diperiksa oleh</p>
          <p class="mt-12 border-t border-slate-300 pt-1">( petugas gudang )</p>
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
  size: A4 landscape;
  margin: 10mm;
}
</style>