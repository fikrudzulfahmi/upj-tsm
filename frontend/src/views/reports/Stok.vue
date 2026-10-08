<script setup>
import { computed, onMounted, ref } from 'vue'
import { laporanApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { formatAngka, formatRupiah } from '@/utils/format'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import StatCard from '@/components/ui/StatCard.vue'

const ui = useUiStore()
const data = ref(null)
const memuat = ref(true)
const hanyaMenipis = ref(false)

async function muat() {
  memuat.value = true
  try {
    const res = await laporanApi.stok({ menipis: hanyaMenipis.value ? 1 : undefined })
    data.value = res.data
  } finally {
    memuat.value = false
  }
}

onMounted(muat)

/** Buka halaman cetak HTML di tab baru (otomatis memunculkan dialog cetak). */
function cetakLangsung() {
  window.open(`/cetak/laporan-stok?menipis=${hanyaMenipis.value ? 1 : 0}`, '_blank')
}

async function cetakPdf() {
  try {
    await laporanApi.stokPdf({ menipis: hanyaMenipis.value ? 1 : undefined })
    ui.sukses('PDF laporan stok sedang diunduh.')
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}

const baris = computed(() => data.value?.baris || [])
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <label class="flex items-center gap-1.5 text-sm text-slate-600">
          <input v-model="hanyaMenipis" type="checkbox" @change="muat" /> Hanya stok menipis
        </label>
      </div>
      <div class="flex gap-2">
        <BaseButton varian="garis" ikon="refresh" @click="muat">Muat Ulang</BaseButton>
        <BaseButton varian="garis" ikon="fileText" @click="cetakPdf">Pratinjau PDF</BaseButton>
        <BaseButton ikon="printer" @click="cetakLangsung">Cetak Langsung</BaseButton>
      </div>
    </div>

    <LoadingBlock v-if="memuat" />

    <template v-else-if="data">
      <div class="grid gap-3 sm:grid-cols-3">
        <StatCard label="Jumlah item" :nilai="formatAngka(data.ringkasan.jumlah_item)" ikon="package" />
        <StatCard label="Item menipis" :nilai="formatAngka(data.ringkasan.jumlah_menipis)" ikon="alert" warna="amber" />
        <StatCard label="Total nilai persediaan" :nilai="formatRupiah(data.ringkasan.total_nilai_persediaan)" ikon="wallet" warna="emerald" keterangan="stok × harga beli" />
      </div>

      <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Daftar Stok</h3></div>
        <div class="overflow-x-auto">
          <table class="table-app">
            <thead>
              <tr>
                <th>Sparepart</th><th>Satuan</th>
                <th class="text-right">Stok</th><th class="text-right">Min</th>
                <th class="text-right">Harga Beli</th><th class="text-right">Harga Jual</th>
                <th class="text-right">Nilai Persediaan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!baris.length">
                <td colspan="7" class="py-8 text-center text-xs text-slate-400">Tidak ada data.</td>
              </tr>
              <tr v-for="b in baris" :key="b.id">
                <td class="text-sm">
                  {{ b.name }}
                  <BaseBadge v-if="b.menipis" varian="bahaya" ukuran="sm">menipis</BaseBadge>
                </td>
                <td class="text-xs text-slate-500">{{ b.unit }}</td>
                <td class="tabular text-right text-sm" :class="b.menipis ? 'font-semibold text-brand-600' : ''">{{ b.stock }}</td>
                <td class="tabular text-right text-sm text-slate-400">{{ b.min_stock }}</td>
                <td class="tabular text-right text-sm">{{ formatRupiah(b.buy_price) }}</td>
                <td class="tabular text-right text-sm">{{ formatRupiah(b.sell_price) }}</td>
                <td class="tabular text-right text-sm font-medium">{{ formatRupiah(b.nilai_persediaan) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-slate-50">
              <tr>
                <td colspan="6" class="px-3 py-2 text-right font-semibold text-slate-600">Total nilai persediaan</td>
                <td class="tabular px-3 py-2 text-right font-bold">{{ formatRupiah(data.ringkasan.total_nilai_persediaan) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>