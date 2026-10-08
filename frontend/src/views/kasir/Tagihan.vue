<script setup>
/**
 * KASIR — Tagihan Siap Bayar.
 * Hanya Form SA berstatus `selesai` yang muncul di sini; Form SA sendiri
 * tidak lagi dipakai untuk pembayaran.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { cetakApi, kasirApi, saApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useDaftar } from '@/composables/useDaftar'
import { usePrinterThermal } from '@/composables/usePrinterThermal'
import { usePengaturanStore } from '@/stores/pengaturan'
import { formatRupiah, formatTanggalJam } from '@/utils/format'
import { labelMetode, METODE_BAYAR } from '@/utils/status'
import BaseTable from '@/components/ui/BaseTable.vue'
import TombolCetakThermal from '@/components/TombolCetakThermal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import StatCard from '@/components/ui/StatCard.vue'
import Pagination from '@/components/ui/Pagination.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const auth = useAuthStore()
const ui = useUiStore()
const pengaturan = usePengaturanStore()

const kolom = [
  { kunci: 'sa_no', label: 'No. SA' },
  { kunci: 'pelanggan', label: 'Pelanggan' },
  { kunci: 'kendaraan', label: 'Kendaraan' },
  { kunci: 'selesai', label: 'Selesai' },
  { kunci: 'total', label: 'Total Tagihan', align: 'kanan' },
  { kunci: 'aksi', label: '', kelas: 'w-44 text-right' },
]

const daftar = useDaftar((f) => kasirApi.tagihan(f))

const ringkasan = computed(() => daftar.meta.ringkasan || null)

const modalBayar = ref(false)
const proses = ref(false)
const saDipilih = ref(null)
const bayar = reactive({ payment_method: 'cash', paid_amount: 0 })
const cetakSetelahBayar = ref(true)
const printer = usePrinterThermal()

// Satu permintaan saja: ringkasan tagihan ikut pada `meta` respons daftar.
onMounted(daftar.muat)
const muat = daftar.muat

function bukaBayar(baris) {
  saDipilih.value = baris
  bayar.payment_method = 'cash'
  bayar.paid_amount = baris.grand_total
  modalBayar.value = true
}

async function bayarSekarang() {
  proses.value = true
  try {
    const idSa = saDipilih.value.id
    const noSa = saDipilih.value.sa_no

    await saApi.bayar(idSa, {
      payment_method: bayar.payment_method,
      paid_amount: Number(bayar.paid_amount) || saDipilih.value.grand_total,
    })
    modalBayar.value = false
    ui.sukses(`Tagihan ${noSa} sudah dibayar.`)

    // Cetak nota thermal otomatis bila printer tersambung (menghemat 1 klik di kasir).
    if (cetakSetelahBayar.value && printer.tersambung.value) {
      try {
        await pengaturan.muat()
        const detail = (await saApi.detail(idSa)).data
        await printer.cetakNota(detail, {
          namaBengkel: pengaturan.namaBengkel,
          alamatBengkel: pengaturan.alamatBengkel,
          teleponBengkel: pengaturan.teleponBengkel,
        })
        ui.sukses('Nota thermal tercetak.')
      } catch (e) {
        ui.gagal(e?.message || 'Pembayaran tersimpan, tetapi nota thermal gagal dicetak.')
      }
    }

    await muat()
  } catch (e) {
    ui.gagal(pesanGalat(e))
  } finally {
    proses.value = false
  }
}

/** Cetak 58mm lewat dialog peramban (jalur cadangan bila Web Bluetooth tak tersedia). */
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
</script>

<template>
  <div class="space-y-4">
    <div v-if="ringkasan" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <StatCard label="Tagihan belum dibayar" :nilai="ringkasan.jumlah_tagihan" ikon="banknote" warna="brand" :keterangan="`${ringkasan.tagihan_hari_ini} selesai hari ini`" />
      <StatCard label="Nilai tagihan" :nilai="formatRupiah(ringkasan.total_tagihan)" ikon="wallet" warna="amber" />
      <StatCard label="Diterima hari ini" :nilai="formatRupiah(ringkasan.dibayar_hari_ini)" ikon="trendingUp" warna="emerald" :keterangan="`${ringkasan.jumlah_dibayar_hari_ini} transaksi`" />
      <div class="card p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Rekap metode hari ini</p>
        <ul class="mt-1 space-y-0.5 text-sm">
          <li v-for="m in ringkasan.per_metode_hari_ini" :key="m.metode" class="flex justify-between">
            <span class="text-slate-500">{{ m.label }}</span>
            <span class="tabular" :class="m.jumlah ? 'font-medium text-slate-700' : 'text-slate-300'">{{ formatRupiah(m.total) }}</span>
          </li>
        </ul>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="flex flex-wrap items-end gap-2">
          <BaseInput v-model="daftar.filter.search" ikon="search" placeholder="Cari no. SA / pelanggan / nopol…" class="w-72" @input="daftar.cari" />
          <BaseInput v-model="daftar.filter.dari" label="Selesai dari" tipe="date" class="w-40" />
          <BaseInput v-model="daftar.filter.sampai" label="s/d" tipe="date" class="w-40" />
          <BaseButton varian="garis" ikon="filter" @click="daftar.muat">Terapkan</BaseButton>
        </div>
        <span class="text-xs text-slate-400">Urut dari yang paling lama selesai</span>
      </div>

      <BaseTable :kolom="kolom" :baris="daftar.baris.value" :muat="daftar.memuat.value" kosong-teks="Tidak ada tagihan menunggu pembayaran.">
        <template #sel-sa_no="{ baris }">
          <span class="font-medium text-slate-700">{{ baris.sa_no }}</span>
          <p class="text-xs text-slate-400">
            {{ baris.services?.length || 0 }} pekerjaan · {{ baris.parts?.length || 0 }} sparepart
          </p>
        </template>

        <template #sel-pelanggan="{ baris }">
          <p class="text-sm">{{ baris.customer_name }}</p>
          <p class="text-xs text-slate-400">
            {{ baris.phone || '-' }}
            <BaseBadge v-if="baris.is_member_at_entry" varian="sukses" ukuran="sm">MEMBER</BaseBadge>
          </p>
        </template>

        <template #sel-kendaraan="{ baris }">
          <p class="text-sm">{{ baris.plate_number }}</p>
          <p class="text-xs text-slate-400">{{ baris.vehicle_name || '-' }}</p>
        </template>

        <template #sel-selesai="{ baris }">
          <span class="text-sm">{{ formatTanggalJam(baris.finished_at) }}</span>
          <p v-if="baris.mechanic" class="text-xs text-slate-400">{{ baris.mechanic.name }}</p>
        </template>

        <template #sel-total="{ baris }">
          <span class="tabular text-base font-semibold text-slate-800">{{ formatRupiah(baris.grand_total) }}</span>
          <p v-if="baris.discount_services" class="text-xs text-emerald-600">diskon {{ formatRupiah(baris.discount_services) }}</p>
        </template>

        <template #sel-aksi="{ baris }">
          <div class="flex justify-end gap-1">
            <TombolCetakThermal :sa="baris" />
            <BaseButton ukuran="ikon" varian="halus" ikon="printer" title="Cetak 58mm lewat dialog peramban" @click="cetak58(baris)" />
            <BaseButton ukuran="ikon" varian="garis" ikon="fileText" title="Pratinjau PDF" @click="cetakPdf(baris)" />
            <RouterLink :to="`/service-orders/${baris.id}`">
              <BaseButton ukuran="ikon" varian="garis" ikon="eye" title="Detail pekerjaan" />
            </RouterLink>
            <BaseButton ukuran="ikon" ikon="money" title="Bayar" @click="bukaBayar(baris)" />
          </div>
        </template>
      </BaseTable>

      <Pagination :halaman="daftar.meta.halaman" :per-halaman="daftar.meta.per_halaman" :total="daftar.meta.total" @ubah="daftar.keHalaman" />
    </div>

    <BaseModal v-model="modalBayar" judul="Pembayaran Tagihan" lebar="sm">
      <div v-if="saDipilih" class="space-y-3">
        <div class="rounded-lg bg-slate-50 p-3 text-sm">
          <p class="font-medium text-slate-700">{{ saDipilih.sa_no }} — {{ saDipilih.customer_name }}</p>
          <p class="text-xs text-slate-500">{{ saDipilih.plate_number }} · selesai {{ formatTanggalJam(saDipilih.finished_at) }}</p>
        </div>
        <p class="text-sm text-slate-600">
          Total tagihan: <strong class="tabular text-lg">{{ formatRupiah(saDipilih.grand_total) }}</strong>
        </p>
        <BaseSelect v-model="bayar.payment_method" label="Metode Pembayaran" :opsi="METODE_BAYAR" :boleh-kosong="false" />
        <MoneyInput v-model="bayar.paid_amount" label="Jumlah Diterima" petunjuk="Boleh lebih untuk hitung kembalian; minimal sama dengan total" />
        <p v-if="bayar.paid_amount > saDipilih.grand_total" class="text-xs text-emerald-700">
          Kembalian: {{ formatRupiah(bayar.paid_amount - saDipilih.grand_total) }}
        </p>
        <label v-if="printer.tersambung.value" class="flex items-center gap-2 text-sm text-slate-600">
          <input v-model="cetakSetelahBayar" type="checkbox" />
          Cetak nota thermal ({{ printer.namaPrinter.value }}) setelah pembayaran
        </label>
        <p v-else class="text-xs text-slate-400">
          Printer thermal belum tersambung — nota bisa dicetak dari tombol
          <span class="font-medium">Cetak Thermal</span> pada daftar tagihan.
        </p>
      </div>
      <template #footer>
        <BaseButton v-if="saDipilih" varian="garis" ikon="printer" @click="cetak58(saDipilih)">Cetak 58mm</BaseButton>
        <BaseButton varian="garis" @click="modalBayar = false">Batal</BaseButton>
        <BaseButton ikon="money" :memuat="proses" @click="bayarSekarang">Catat Pembayaran</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>