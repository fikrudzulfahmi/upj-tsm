<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { cetakApi, saApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { usePengaturanStore } from '@/stores/pengaturan'
import { formatRupiah, formatTanggalJam } from '@/utils/format'
import { labelMetode, statusSa } from '@/utils/status'
import TombolCetakThermal from '@/components/TombolCetakThermal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import FuelGauge from '@/components/FuelGauge.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()
const pengaturan = usePengaturanStore()

const data = ref(null)
const memuat = ref(true)
const proses = ref(false)
const modalBatal = ref(false)
const alasanBatal = ref('')

const siapDibayar = computed(() => data.value?.status === 'finished')
const bisaUbah = computed(() => ['draft', 'in_progress'].includes(data.value?.status))
const bisaKasir = computed(() => auth.bisa('kasir.manage'))

async function muat() {
  const res = await saApi.detail(route.params.id)
  data.value = res.data
}

onMounted(async () => {
  try {
    await Promise.all([muat(), pengaturan.muat()])
  } catch {
    router.replace('/service-orders')
  } finally {
    memuat.value = false
  }
})

async function jalankan(aksi, pesan) {
  proses.value = true
  try {
    await aksi()
    await muat()
    if (pesan) ui.sukses(pesan)
  } catch (e) {
    ui.gagal(pesanGalat(e))
  } finally {
    proses.value = false
  }
}

const mulai = () => jalankan(() => saApi.mulai(data.value.id), 'Pengerjaan dimulai.')
const selesai = () => jalankan(() => saApi.selesai(data.value.id), 'Form SA selesai, stok sparepart dikurangi.')

/** Cetak langsung: halaman nota HTML di tab baru, otomatis memunculkan dialog cetak. */
function cetakLangsung() {
  window.open(`/cetak/nota/${data.value.id}`, '_blank')
}

async function batalkan() {
  proses.value = true
  try {
    await saApi.batal(data.value.id, { alasan: alasanBatal.value })
    modalBatal.value = false
    alasanBatal.value = ''
    await muat()
    ui.sukses('Form SA dibatalkan. Stok dikembalikan dan keuangan dibalik.')
  } catch (e) {
    ui.gagal(pesanGalat(e))
  } finally {
    proses.value = false
  }
}

async function cetak() {
  try {
    await cetakApi.notaSa(data.value.id)
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}
</script>

<template>
  <div class="space-y-4">
    <LoadingBlock v-if="memuat" />

    <template v-else-if="data">
      <div class="card p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-lg font-semibold text-slate-800">{{ data.sa_no }}</h2>
              <BaseBadge :varian="statusSa(data.status).varian">{{ statusSa(data.status).label }}</BaseBadge>
              <BaseBadge v-if="data.is_member_at_entry" varian="sukses" ukuran="sm">MEMBER</BaseBadge>
              <BaseBadge v-if="data.checkup_id" varian="info" ukuran="sm">Dari Check Up</BaseBadge>
            </div>
            <p class="mt-1 text-sm text-slate-500">
              {{ data.customer_name }} · {{ data.plate_number }} · {{ data.vehicle_name }}
              <span v-if="data.phone"> · {{ data.phone }}</span>
            </p>
            <p class="text-xs text-slate-400">
              Dibuat {{ formatTanggalJam(data.created_at) }}
              <span v-if="data.finished_at"> · Selesai {{ formatTanggalJam(data.finished_at) }}</span>
              <span v-if="data.paid_at"> · Dibayar {{ formatTanggalJam(data.paid_at) }}</span>
            </p>
          </div>

          <div class="flex flex-wrap gap-2">
            <TombolCetakThermal :sa="data" :teks="'Cetak Thermal'" ukuran="sm" />
            <BaseButton varian="garis" ikon="printer" @click="cetakLangsung">Cetak Nota A4</BaseButton>
            <BaseButton varian="garis" ikon="fileText" @click="cetak">Pratinjau PDF</BaseButton>
            <RouterLink v-if="bisaUbah" :to="`/service-orders/${data.id}/edit`">
              <BaseButton varian="garis" ikon="pencil">Ubah</BaseButton>
            </RouterLink>
            <BaseButton v-if="data.status === 'draft'" ikon="play" :memuat="proses" @click="mulai">Mulai Dikerjakan</BaseButton>
            <BaseButton v-if="data.status === 'in_progress'" ikon="checkCircle" :memuat="proses" @click="selesai">Selesaikan</BaseButton>
            <BaseButton v-if="data.status !== 'cancelled'" varian="bahaya" ikon="ban" @click="modalBatal = true">Batalkan</BaseButton>
            <BaseButton varian="garis" ikon="arrowLeft" @click="router.push('/service-orders')">Kembali</BaseButton>
          </div>
        </div>
      </div>

      <div
        v-if="siapDibayar"
        class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      >
        <span class="flex items-center gap-2">
          <AppIcon nama="banknote" :ukuran="18" />
          Pekerjaan sudah <strong>selesai</strong>. Form SA hanya mencatat pekerjaan —
          pembayaran &amp; nota dilakukan di menu <strong>Kasir</strong>.
        </span>
        <RouterLink v-if="bisaKasir" to="/kasir">
          <BaseButton ikon="banknote">Buka Kasir</BaseButton>
        </RouterLink>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <div class="card space-y-3 p-4 lg:col-span-1">
          <h3 class="text-sm font-semibold text-slate-700">Info Kendaraan</h3>
          <FuelGauge :model-value="data.fuel_level" :maks="pengaturan.jumlahBarBensin" :bisa-edit="false" />
          <dl class="space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Mekanik</dt><dd>{{ data.mechanic?.name || '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Odometer</dt><dd class="tabular">{{ data.odometer ? `${data.odometer.toLocaleString('id-ID')} km` : '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Diskon member</dt><dd>{{ data.member_discount_percent }}%</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Unit entry</dt><dd>{{ data.unit_entry?.entry_no || '-' }}</dd></div>
          </dl>
          <div class="rounded-lg bg-slate-50 p-3">
            <p class="text-xs uppercase tracking-wide text-slate-500">Keluhan</p>
            <p class="mt-1 text-sm text-slate-700">{{ data.complaint || '-' }}</p>
          </div>
          <div v-if="data.cancel_reason" class="rounded-lg bg-brand-50 p-3">
            <p class="text-xs uppercase tracking-wide text-brand-700">Alasan pembatalan</p>
            <p class="mt-1 text-sm text-brand-700">{{ data.cancel_reason }}</p>
          </div>
        </div>

        <div class="space-y-4 lg:col-span-2">
          <div v-if="data.conditions?.length" class="card">
            <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Kondisi Kendaraan</h3></div>
            <div class="overflow-x-auto">
              <table class="table-app">
                <thead><tr><th>Item</th><th>Kategori</th><th>Status</th><th>Catatan</th></tr></thead>
                <tbody>
                  <tr v-for="k in data.conditions" :key="k.id">
                    <td class="text-sm">{{ k.item_name }}</td>
                    <td class="text-xs text-slate-500">{{ k.category }}</td>
                    <td><span class="rounded px-1.5 py-0.5 text-[11px]" :class="`status-${k.status}`">{{ k.status.replace('_', ' ') }}</span></td>
                    <td class="text-xs text-slate-500">{{ k.note || '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Pekerjaan &amp; Sparepart</h3></div>
            <div class="overflow-x-auto">
              <table class="table-app">
                <thead>
                  <tr><th>Item</th><th class="text-right">Harga</th><th class="text-right">Qty</th><th class="text-right">Diskon</th><th class="text-right">Subtotal</th></tr>
                </thead>
                <tbody>
                  <tr v-for="j in data.services" :key="`j${j.id}`">
                    <td class="text-sm"><AppIcon nama="wrench" :ukuran="13" class="mr-1 inline text-slate-400" />{{ j.name }}</td>
                    <td class="tabular text-right text-sm">{{ formatRupiah(j.price) }}</td>
                    <td class="tabular text-right text-sm">{{ j.qty }}</td>
                    <td class="tabular text-right text-sm text-emerald-600">{{ j.discount_amount ? `- ${formatRupiah(j.discount_amount)} (${j.discount_percent}%)` : '-' }}</td>
                    <td class="tabular text-right text-sm">{{ formatRupiah(j.subtotal) }}</td>
                  </tr>
                  <tr v-for="p in data.parts" :key="`p${p.id}`">
                    <td class="text-sm">
                      <AppIcon nama="package" :ukuran="13" class="mr-1 inline text-slate-400" />{{ p.name }}
                      <BaseBadge v-if="p.is_free_reward" varian="brand" ukuran="sm">hadiah poin</BaseBadge>
                    </td>
                    <td class="tabular text-right text-sm">{{ formatRupiah(p.sell_price) }}</td>
                    <td class="tabular text-right text-sm">{{ p.qty }}</td>
                    <td class="text-right text-sm text-slate-400">-</td>
                    <td class="tabular text-right text-sm">{{ formatRupiah(p.subtotal) }}</td>
                  </tr>
                  <tr v-if="!data.services?.length && !data.parts?.length">
                    <td colspan="5" class="py-6 text-center text-xs text-slate-400">Belum ada item.</td>
                  </tr>
                </tbody>
                <tfoot class="bg-slate-50 text-sm">
                  <tr><td colspan="4" class="px-3 py-2 text-right text-slate-500">Subtotal jasa</td><td class="tabular px-3 py-2 text-right">{{ formatRupiah(data.subtotal_services) }}</td></tr>
                  <tr><td colspan="4" class="px-3 py-2 text-right text-slate-500">Diskon member</td><td class="tabular px-3 py-2 text-right text-emerald-600">- {{ formatRupiah(data.discount_services) }}</td></tr>
                  <tr><td colspan="4" class="px-3 py-2 text-right text-slate-500">Total sparepart</td><td class="tabular px-3 py-2 text-right">{{ formatRupiah(data.total_parts) }}</td></tr>
                  <tr class="bg-brand-50">
                    <td colspan="4" class="px-3 py-2 text-right font-semibold text-brand-700">Grand Total</td>
                    <td class="tabular px-3 py-2 text-right text-base font-bold text-brand-700">{{ formatRupiah(data.grand_total) }}</td>
                  </tr>
                  <tr v-if="data.status === 'paid'">
                    <td colspan="4" class="px-3 py-2 text-right text-slate-500">
                      Dibayar ({{ labelMetode(data.payment_method) }})
                    </td>
                    <td class="tabular px-3 py-2 text-right">{{ formatRupiah(data.paid_amount) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Modal batal -->
    <BaseModal v-model="modalBatal" judul="Batalkan Form SA" lebar="sm">
      <div class="space-y-3">
        <p class="text-sm text-slate-600">
          Pembatalan akan <strong>mengembalikan stok</strong> dan membuat baris pembalik keuangan
          (data asli tidak dihapus).
        </p>
        <BaseTextarea v-model="alasanBatal" label="Alasan Pembatalan" :baris="2" diperlukan placeholder="mis. pelanggan batal, salah input" />
      </div>
      <template #footer>
        <BaseButton varian="garis" @click="modalBatal = false">Tidak Jadi</BaseButton>
        <BaseButton varian="bahaya" ikon="ban" :memuat="proses" :nonaktif="alasanBatal.trim().length < 3" @click="batalkan">
          Batalkan SA
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>