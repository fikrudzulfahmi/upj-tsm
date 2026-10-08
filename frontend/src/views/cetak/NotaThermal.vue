<script setup>
/**
 * Cetak nota lewat DIALOG PERAMBAN untuk kertas thermal 58mm (32 kolom).
 *
 * Ini jalur cadangan bila perangkat kasir tidak mendukung Web Bluetooth
 * (mis. iPad/Safari): printer dipasangkan ke perangkat, lalu halaman ini dicetak
 * dengan ukuran kertas 58mm. Tata letaknya sengaja dibuat sempit & monospace
 * agar mirip nota thermal sungguhan.
 *
 * Kertas harus diset "58mm" pada dialog cetak; `@page` di bawah sudah
 * memberitahukannya ke peramban.
 */
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { saApi } from '@/api'
import { useUiStore } from '@/stores/ui'
import { usePengaturanStore } from '@/stores/pengaturan'
import { formatTanggalJam } from '@/utils/format'
import { angka } from '@/utils/escpos'
import BaseButton from '@/components/ui/BaseButton.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const pengaturan = usePengaturanStore()

const sa = ref(null)
const memuat = ref(true)

const METODE = { cash: 'Tunai', transfer: 'Transfer', qris: 'QRIS' }
const sudahBayar = computed(() => sa.value?.status === 'paid')
const kembali = computed(() => Math.max(0, (sa.value?.paid_amount || 0) - (sa.value?.grand_total || 0)))

function cetakSekarang() {
  window.print()
}

function rupiah(nilai) {
  return 'Rp ' + angka(nilai)
}

onMounted(async () => {
  try {
    await pengaturan.muat()
    sa.value = (await saApi.detail(route.params.id)).data
    await nextTick()
    if (route.query.cetak !== '0') setTimeout(() => window.print(), 500)
  } catch {
    ui.gagal('Data Form SA tidak dapat dimuat.')
  } finally {
    memuat.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-slate-200 py-6 print:bg-white print:py-0">
    <div class="no-print mx-auto mb-4 flex w-full max-w-sm flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
      <div class="flex items-center gap-1.5 text-xs font-medium text-slate-700">
        <AppIcon nama="printer" :ukuran="15" class="text-brand-600" /> Nota 58mm
      </div>
      <div class="flex gap-1.5">
        <BaseButton ukuran="sm" varian="garis" ikon="arrowLeft" @click="router.back()">Kembali</BaseButton>
        <BaseButton ukuran="sm" ikon="printer" @click="cetakSekarang">Cetak</BaseButton>
      </div>
    </div>

    <LoadingBlock v-if="memuat" teks="Menyiapkan nota…" />

    <div v-else-if="sa" class="nota-thermal mx-auto bg-white p-2 shadow-sm print:shadow-none">
      <!-- kop -->
      <p class="tengah tebal ganda">{{ (pengaturan.namaBengkel || 'Bengkel').toUpperCase() }}</p>
      <p v-if="pengaturan.alamatBengkel" class="tengah">{{ pengaturan.alamatBengkel }}</p>
      <p v-if="pengaturan.teleponBengkel" class="tengah">{{ pengaturan.teleponBengkel }}</p>
      <p class="garis">================================</p>

      <!-- identitas -->
      <p class="kolom"><span>No. {{ sa.sa_no }}</span><span>{{ formatTanggalJam(sa.created_at) }}</span></p>
      <p class="kolom"><span>Pelanggan</span><span>{{ sa.customer_name }}</span></p>
      <p class="kolom"><span>No. Polisi</span><span>{{ sa.plate_number }}</span></p>
      <p v-if="sa.vehicle_name" class="kolom"><span>Kendaraan</span><span>{{ sa.vehicle_name }}</span></p>
      <p v-if="sa.mechanic?.name" class="kolom"><span>Mekanik</span><span>{{ sa.mechanic.name }}</span></p>
      <p v-if="sa.odometer" class="kolom"><span>Odometer</span><span>{{ angka(sa.odometer) }} km</span></p>
      <p v-if="sa.is_member_at_entry">* Member (diskon {{ sa.member_discount_percent || 0 }}%)</p>

      <!-- pekerjaan -->
      <template v-if="sa.services?.length">
        <p class="garis">--------------------------------</p>
        <p class="tebal">PEKERJAAN</p>
        <template v-for="j in sa.services" :key="j.id">
          <p>{{ j.name }}</p>
          <p class="kolom"><span>&nbsp;&nbsp;{{ j.qty }} x {{ angka(j.price) }}</span><span>{{ angka(j.subtotal) }}</span></p>
          <p v-if="Number(j.discount_amount) > 0" class="kolom"><span>&nbsp;&nbsp;diskon member</span><span>-{{ angka(j.discount_amount) }}</span></p>
        </template>
      </template>

      <!-- sparepart -->
      <template v-if="sa.parts?.length">
        <p class="garis">--------------------------------</p>
        <p class="tebal">SPAREPART</p>
        <template v-for="p in sa.parts" :key="p.id">
          <p>{{ p.name }}</p>
          <p class="kolom">
            <span>&nbsp;&nbsp;{{ p.qty }} x {{ angka(p.sell_price) }}</span>
            <span>{{ p.is_free_reward ? 'GRATIS' : angka(p.subtotal) }}</span>
          </p>
        </template>
      </template>

      <!-- total -->
      <p class="garis">--------------------------------</p>
      <p class="kolom"><span>Subtotal jasa</span><span>{{ angka(sa.subtotal_services) }}</span></p>
      <p v-if="Number(sa.discount_services) > 0" class="kolom">
        <span>Diskon member {{ sa.member_discount_percent || 0 }}%</span><span>-{{ angka(sa.discount_services) }}</span>
      </p>
      <p class="kolom"><span>Total sparepart</span><span>{{ angka(sa.total_parts) }}</span></p>
      <p class="garis">--------------------------------</p>
      <p class="kolom tebal ganda"><span>TOTAL</span><span>{{ rupiah(sa.grand_total) }}</span></p>

      <template v-if="sudahBayar">
        <p class="kolom"><span>Bayar ({{ METODE[sa.payment_method] || sa.payment_method }})</span><span>{{ angka(sa.paid_amount) }}</span></p>
        <p v-if="kembali > 0" class="kolom"><span>Kembalian</span><span>{{ angka(kembali) }}</span></p>
        <p class="tengah tebal">*** LUNAS ***</p>
      </template>
      <template v-else>
        <p class="tengah">** BELUM DIBAYAR **</p>
        <p>Pembayaran dilakukan di kasir.</p>
      </template>

      <p class="garis">================================</p>
      <p class="tengah">Terima kasih atas kepercayaan Anda</p>
      <p class="tengah">Barang yang sudah dibeli tidak dapat ditukar</p>
      <p>&nbsp;</p>
    </div>

    <p class="no-print mx-auto mt-3 w-full max-w-sm text-center text-xs text-slate-500">
      Pada dialog cetak: pilih printer thermal, ukuran kertas <strong>58mm</strong>, margin
      <strong>tidak ada</strong>, dan matikan header/footer peramban.
    </p>
  </div>
</template>

<style scoped>
.nota-thermal {
  width: 58mm;
  font-family: 'Courier New', ui-monospace, monospace;
  font-size: 9pt;
  line-height: 1.35;
  color: #000;
}

.nota-thermal p {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
}

.tengah {
  text-align: center;
}

.tebal {
  font-weight: 700;
}

.ganda {
  font-size: 11pt;
}

.garis {
  letter-spacing: -0.5px;
}

.kolom {
  display: flex;
  justify-content: space-between;
  gap: 4px;
}

.kolom > span:last-child {
  text-align: right;
  white-space: nowrap;
}
</style>

<style>
@page {
  size: 58mm auto;
  margin: 0;
}
</style>