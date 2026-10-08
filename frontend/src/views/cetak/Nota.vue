<script setup>
/**
 * Halaman CETAK LANGSUNG nota/invoice Form SA.
 * Dibuka di tab baru (mis. dari menu Kasir), memuat data, lalu memunculkan
 * dialog cetak peramban otomatis. Tambahkan `?cetak=0` pada URL untuk melihat
 * dulu tanpa langsung mencetak.
 */
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { cetakApi, saApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { usePengaturanStore } from '@/stores/pengaturan'
import { formatRupiah, formatTanggal, formatTanggalJam } from '@/utils/format'
import { labelMetode } from '@/utils/status'
import BaseButton from '@/components/ui/BaseButton.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const pengaturan = usePengaturanStore()

const sa = ref(null)
const memuat = ref(true)

const sudahDibayar = computed(() => sa.value?.status === 'paid')
const sisaBayar = computed(() => Math.max(0, (sa.value?.grand_total || 0) - (sa.value?.paid_amount || 0)))

/** Dipanggil dari tombol; `window` tidak tersedia langsung di template Vue. */
function cetakSekarang() {
  window.print()
}

onMounted(async () => {
  try {
    await pengaturan.muat()
    const res = await saApi.detail(route.params.id)
    sa.value = res.data
    await nextTick()

    // Cetak langsung (default). `?cetak=0` untuk sekadar melihat.
    if (route.query.cetak !== '0') {
      setTimeout(() => window.print(), 700)
    }
  } catch {
    ui.gagal('Data Form SA tidak dapat dimuat.')
  } finally {
    memuat.value = false
  }
})

async function cetakPdf() {
  try {
    await cetakApi.notaSa(sa.value.id)
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-100 py-6 print:bg-white print:py-0">
    <!-- Bilah alat: tidak ikut tercetak -->
    <div class="no-print mx-auto mb-4 flex w-full max-w-3xl flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
      <div class="flex items-center gap-2 text-sm font-medium text-slate-700">
        <AppIcon nama="printer" :ukuran="17" class="text-brand-600" />
        Cetak Nota / Invoice
      </div>
      <div class="flex flex-wrap gap-2">
        <BaseButton varian="garis" ikon="arrowLeft" @click="router.back()">Kembali</BaseButton>
        <BaseButton varian="garis" ikon="fileText" @click="cetakPdf">Pratinjau PDF</BaseButton>
        <BaseButton ikon="printer" @click="cetakSekarang">Cetak Sekarang</BaseButton>
      </div>
    </div>

    <LoadingBlock v-if="memuat" teks="Menyiapkan nota…" />

    <div v-else-if="sa" class="mx-auto w-full max-w-3xl bg-white p-8 shadow-sm print:max-w-none print:p-0 print:shadow-none">
      <!-- Kop -->
      <div class="flex items-start justify-between border-b-2 border-brand-600 pb-3">
        <div>
          <h1 class="text-xl font-bold text-brand-700">{{ pengaturan.namaBengkel }}</h1>
          <p class="text-xs text-slate-600">{{ pengaturan.alamatBengkel }}</p>
          <p class="text-xs text-slate-600">{{ pengaturan.teleponBengkel }}</p>
        </div>
        <div class="text-right">
          <p class="text-lg font-bold text-slate-800">{{ sudahDibayar ? 'NOTA / INVOICE' : 'NOTA' }}</p>
          <p class="text-xs text-slate-500">No. {{ sa.sa_no }}</p>
          <p class="text-xs text-slate-500">{{ formatTanggal(sa.created_at) }}</p>
        </div>
      </div>

      <!-- Identitas -->
      <div class="mt-4 grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
        <div class="flex justify-between gap-2"><span class="text-slate-500">Pelanggan</span><span class="font-medium text-slate-800">{{ sa.customer_name }}</span></div>
        <div class="flex justify-between gap-2"><span class="text-slate-500">No. Polisi</span><span class="font-medium text-slate-800">{{ sa.plate_number }}</span></div>
        <div class="flex justify-between gap-2"><span class="text-slate-500">No. HP</span><span class="text-slate-700">{{ sa.phone || '-' }}</span></div>
        <div class="flex justify-between gap-2"><span class="text-slate-500">Kendaraan</span><span class="text-slate-700">{{ sa.vehicle_name || '-' }}</span></div>
        <div class="flex justify-between gap-2"><span class="text-slate-500">Mekanik</span><span class="text-slate-700">{{ sa.mechanic?.name || '-' }}</span></div>
        <div class="flex justify-between gap-2"><span class="text-slate-500">Odometer</span><span class="tabular text-slate-700">{{ sa.odometer ? sa.odometer.toLocaleString('id-ID') + ' km' : '-' }}</span></div>
        <div class="col-span-2 flex justify-between gap-2"><span class="text-slate-500">Keluhan</span><span class="text-slate-700">{{ sa.complaint || '-' }}</span></div>
      </div>

      <!-- Pekerjaan -->
      <h2 class="mt-5 mb-1 text-sm font-semibold text-slate-700">Pekerjaan</h2>
      <table class="w-full border-collapse text-sm">
        <thead>
          <tr class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <th class="border border-slate-200 px-2 py-1">Nama</th>
            <th class="w-16 border border-slate-200 px-2 py-1 text-right">Qty</th>
            <th class="w-28 border border-slate-200 px-2 py-1 text-right">Harga</th>
            <th class="w-32 border border-slate-200 px-2 py-1 text-right">Diskon</th>
            <th class="w-28 border border-slate-200 px-2 py-1 text-right">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="j in sa.services" :key="j.id">
            <td class="border border-slate-200 px-2 py-1">{{ j.name }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ j.qty }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(j.price) }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">
              {{ j.discount_amount ? `${formatRupiah(j.discount_amount)} (${j.discount_percent}%)` : '-' }}
            </td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(j.subtotal) }}</td>
          </tr>
          <tr v-if="!sa.services?.length">
            <td colspan="5" class="border border-slate-200 px-2 py-2 text-center text-xs text-slate-400">Tidak ada pekerjaan</td>
          </tr>
        </tbody>
      </table>

      <!-- Sparepart -->
      <h2 class="mt-4 mb-1 text-sm font-semibold text-slate-700">Sparepart</h2>
      <table class="w-full border-collapse text-sm">
        <thead>
          <tr class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <th class="border border-slate-200 px-2 py-1">Nama</th>
            <th class="w-16 border border-slate-200 px-2 py-1 text-right">Qty</th>
            <th class="w-28 border border-slate-200 px-2 py-1 text-right">Harga</th>
            <th class="w-28 border border-slate-200 px-2 py-1 text-right">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in sa.parts" :key="p.id">
            <td class="border border-slate-200 px-2 py-1">
              {{ p.name }}<span v-if="p.is_free_reward" class="text-xs text-brand-600"> (hadiah poin)</span>
            </td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ p.qty }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(p.sell_price) }}</td>
            <td class="tabular border border-slate-200 px-2 py-1 text-right">{{ formatRupiah(p.subtotal) }}</td>
          </tr>
          <tr v-if="!sa.parts?.length">
            <td colspan="4" class="border border-slate-200 px-2 py-2 text-center text-xs text-slate-400">Tidak ada sparepart</td>
          </tr>
        </tbody>
      </table>

      <!-- Total -->
      <div class="mt-4 ml-auto w-full max-w-xs text-sm">
        <div class="flex justify-between py-0.5"><span class="text-slate-500">Subtotal jasa</span><span class="tabular">{{ formatRupiah(sa.subtotal_services) }}</span></div>
        <div v-if="sa.discount_services" class="flex justify-between py-0.5"><span class="text-slate-500">Diskon member ({{ sa.member_discount_percent }}%)</span><span class="tabular">- {{ formatRupiah(sa.discount_services) }}</span></div>
        <div class="flex justify-between py-0.5"><span class="text-slate-500">Total sparepart</span><span class="tabular">{{ formatRupiah(sa.total_parts) }}</span></div>
        <div class="mt-1 flex justify-between border-t-2 border-brand-600 pt-1 text-base font-bold text-brand-700">
          <span>TOTAL</span><span class="tabular">{{ formatRupiah(sa.grand_total) }}</span>
        </div>
      </div>

      <!-- Status pembayaran -->
      <div class="mt-4 rounded border border-slate-200 p-2 text-xs">
        <template v-if="sudahDibayar">
          <p class="font-semibold text-emerald-700">LUNAS — dibayar {{ formatTanggalJam(sa.paid_at) }} ({{ labelMetode(sa.payment_method) }})</p>
          <p class="text-slate-600">Jumlah diterima: <span class="tabular">{{ formatRupiah(sa.paid_amount) }}</span>
            <span v-if="sa.paid_amount > sa.grand_total"> · kembalian <span class="tabular">{{ formatRupiah(sa.paid_amount - sa.grand_total) }}</span></span>
          </p>
        </template>
        <template v-else>
          <p class="font-semibold text-slate-700">BELUM DIBAYAR</p>
          <p class="text-slate-600">
            Pembayaran dilakukan di kasir setelah pekerjaan selesai.
            <span v-if="sisaBayar"> Sisa tagihan: <span class="tabular">{{ formatRupiah(sisaBayar) }}</span></span>
          </p>
        </template>
      </div>

      <!-- Tanda tangan -->
      <div class="mt-8 grid grid-cols-2 gap-6 text-center text-xs text-slate-600">
        <div>
          <p>Pelanggan</p>
          <p class="mt-12 border-t border-slate-300 pt-1">( tanda tangan )</p>
        </div>
        <div>
          <p>{{ pengaturan.namaBengkel }}</p>
          <p class="mt-12 border-t border-slate-300 pt-1">( kasir / service advisor )</p>
        </div>
      </div>

      <p class="mt-4 text-center text-[10px] text-slate-400">
        Dicetak {{ formatTanggalJam(new Date().toISOString()) }} · Terima kasih telah mempercayakan kendaraan Anda kepada kami.
      </p>
    </div>

    <div class="no-print mx-auto mt-4 w-full max-w-3xl text-center">
      <p class="text-xs text-slate-400">
        Jika dialog cetak tidak muncul otomatis, klik tombol <strong>Cetak Sekarang</strong> di atas.
      </p>
    </div>
  </div>
</template>

<style>
@page {
  size: A4;
  margin: 12mm;
}
</style>