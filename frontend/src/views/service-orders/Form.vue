<script setup>
/**
 * Form SA — seluruh angka final dihitung server; panel ringkasan di sini hanya
 * perkiraan agar kasir tahu besaran biaya sebelum menyimpan.
 */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { jasaApi, mekanikApi, saApi, sparepartApi, checkupApi } from '@/api'
import { galatValidasi, pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { usePengaturanStore } from '@/stores/pengaturan'
import { formatRupiah, selisihHari } from '@/utils/format'
import CustomerPicker from '@/components/CustomerPicker.vue'
import CheckupItemsForm from '@/components/CheckupItemsForm.vue'
import FuelGauge from '@/components/FuelGauge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import SearchSelect from '@/components/ui/SearchSelect.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const pengaturan = usePengaturanStore()

const idUbah = computed(() => route.params.id || null)
const memuat = ref(true)
const menyimpan = ref(false)
const galat = ref({})
const mekanik = ref([])
const pelanggan = ref(null)
const kendaraan = ref(null)
const kondisi = ref([])
const adaKondisi = ref(false)

const form = reactive({
  pelanggan_id: null,
  kendaraan_id: null,
  checkup_id: null,
  unit_entry_id: null,
  mechanic_id: '',
  odometer: '',
  fuel_level: 0,
  complaint: '',
  vehicle_condition_notes: '',
  notes: '',
})

const jasa = ref([])
const part = ref([])

const isMember = computed(() => pelanggan.value?.membership?.status === 'active')
const persenDiskon = computed(() => {
  if (!isMember.value) return 0
  return pelanggan.value.membership.discount_percent_override ?? pengaturan.diskonMemberGlobal
})

const ringkas = computed(() => {
  let subtotalJasa = 0
  let diskon = 0
  jasa.value.forEach((j) => {
    const kotor = (Number(j.price) || 0) * (Number(j.qty) || 1)
    const persen = isMember.value ? (j.member_discount_percent ?? persenDiskon.value) : 0
    subtotalJasa += kotor
    diskon += Math.round((kotor * persen) / 100)
  })
  const totalPart = part.value.reduce((t, p) => t + (Number(p.sell_price) || 0) * (Number(p.qty) || 1), 0)
  const totalJasa = subtotalJasa - diskon
  return { subtotalJasa, diskon, totalJasa, totalPart, grandTotal: totalJasa + totalPart }
})

async function muatMekanik() {
  const res = await mekanikApi.daftar({ aktif: 1, per_page: 100 })
  mekanik.value = res.data || []
}

function setelahPelanggan({ customer, vehicle }) {
  pelanggan.value = customer
  kendaraan.value = vehicle
  form.pelanggan_id = customer?.id || null
  form.kendaraan_id = vehicle?.id || null
  if (!idUbah.value && vehicle?.last_odometer && form.odometer === '') {
    form.odometer = vehicle.last_odometer
  }
}

async function cariJasa(q) {
  const res = await jasaApi.pilihan({ search: q || undefined, limit: 30 })
  return res.data || []
}

async function cariPart(q) {
  const res = await sparepartApi.pilihan({ search: q || undefined, limit: 30 })
  return res.data || []
}

function tambahJasa(item) {
  if (!item) return
  if (jasa.value.some((j) => j.service_id === item.id)) {
    ui.info('Jasa ini sudah ada di daftar.')
    return
  }
  jasa.value.push({
    service_id: item.id,
    name: item.name,
    price: item.price,
    member_discount_percent: item.member_discount_percent,
    qty: 1,
  })
}

function tambahPart(item) {
  if (!item) return
  if (part.value.some((p) => p.sparepart_id === item.id)) {
    ui.info('Sparepart ini sudah ada di daftar.')
    return
  }
  part.value.push({
    sparepart_id: item.id,
    name: item.name,
    unit: item.unit,
    sell_price: item.sell_price,
    stock: item.stock,
    qty: 1,
    is_free_reward: false,
  })
}

function hapusJasa(i) {
  jasa.value.splice(i, 1)
}
function hapusPart(i) {
  part.value.splice(i, 1)
}

onMounted(async () => {
  try {
    await Promise.all([muatMekanik(), pengaturan.muat()])

    if (idUbah.value) {
      const res = await saApi.detail(idUbah.value)
      const sa = res.data

      form.pelanggan_id = sa.customer_id
      form.kendaraan_id = sa.vehicle_id
      form.checkup_id = sa.checkup_id
      form.unit_entry_id = sa.unit_entry_id
      form.mechanic_id = sa.mechanic_id || ''
      form.odometer = sa.odometer ?? ''
      form.fuel_level = sa.fuel_level
      form.complaint = sa.complaint || ''
      form.vehicle_condition_notes = sa.vehicle_condition_notes || ''
      form.notes = sa.notes || ''

      pelanggan.value = sa.customer
      kendaraan.value = sa.vehicle

      jasa.value = (sa.services || []).map((s) => ({
        service_id: s.service_id, name: s.name, price: s.price,
        member_discount_percent: null, qty: s.qty,
      }))

      part.value = (sa.parts || []).map((p) => ({
        sparepart_id: p.sparepart_id, name: p.name, unit: 'pcs',
        sell_price: p.sell_price, stock: p.stok_tersedia ?? 0, qty: p.qty,
        is_free_reward: p.is_free_reward,
      }))

      kondisi.value = (sa.conditions || []).map((c) => ({
        category: c.category, item_name: c.item_name, status: c.status,
        note: c.note || '', sort_order: c.sort_order,
      }))
      adaKondisi.value = kondisi.value.length > 0
    }
  } catch {
    router.replace('/service-orders')
  } finally {
    memuat.value = false
  }
})

/** Kondisi kendaraan dari check up (kalau SA dibuat dari check up tapi belum ada kondisi tersalin). */
async function muatKondisiDariCheckup() {
  if (!form.checkup_id) return
  const res = await checkupApi.detail(form.checkup_id)
  kondisi.value = (res.data.results || []).map((r) => ({
    category: r.category, item_name: r.item_name, status: r.status,
    note: r.note || '', sort_order: r.sort_order,
  }))
  adaKondisi.value = kondisi.value.length > 0
}

function muatan() {
  return {
    customer_id: form.pelanggan_id,
    vehicle_id: form.kendaraan_id,
    checkup_id: form.checkup_id,
    unit_entry_id: form.unit_entry_id,
    mechanic_id: form.mechanic_id === '' ? null : Number(form.mechanic_id),
    odometer: form.odometer === '' ? null : Number(form.odometer),
    fuel_level: Number(form.fuel_level) || 0,
    complaint: form.complaint,
    vehicle_condition_notes: form.vehicle_condition_notes,
    notes: form.notes,
    services: jasa.value.map((j) => ({ service_id: j.service_id, qty: Number(j.qty) || 1 })),
    parts: part.value.map((p) => ({
      sparepart_id: p.sparepart_id, qty: Number(p.qty) || 1, is_free_reward: !!p.is_free_reward,
    })),
    conditions: kondisi.value,
  }
}

async function simpan(diam = false) {
  galat.value = {}
  if (!form.pelanggan_id || !form.kendaraan_id) {
    ui.gagal('Pelanggan dan kendaraan wajib dipilih.')
    return null
  }

  menyimpan.value = true
  try {
    const res = idUbah.value ? await saApi.ubah(idUbah.value, muatan()) : await saApi.simpan(muatan())
    if (!diam) ui.sukses(`Form SA ${res.data.sa_no} tersimpan.`)
    return res.data
  } catch (e) {
    galat.value = galatValidasi(e)
    if (!Object.keys(galat.value).length) ui.gagal(pesanGalat(e))
    return null
  } finally {
    menyimpan.value = false
  }
}

async function simpanDanTutup() {
  const data = await simpan()
  if (data) router.push(`/service-orders/${data.id}`)
}

async function mulai() {
  const data = await simpan(true)
  if (!data) return
  try {
    await saApi.mulai(data.id)
    ui.sukses('Pengerjaan dimulai.')
    router.push(`/service-orders/${data.id}`)
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}
</script>

<template>
  <div class="space-y-4">
    <LoadingBlock v-if="memuat" />

    <template v-else>
      <div class="card p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <AppIcon nama="clipboardPen" :ukuran="18" class="text-brand-600" />
            <h2 class="text-sm font-semibold text-slate-700">Data Pelanggan &amp; Kendaraan</h2>
          </div>
          <BaseBadge v-if="pelanggan?.membership?.status === 'active'" varian="sukses">
            <AppIcon nama="award" :ukuran="12" />
            MEMBER · sisa {{ selisihHari(pelanggan.membership.expires_at) }} hari · diskon {{ persenDiskon }}%
          </BaseBadge>
          <BaseBadge v-else-if="pelanggan?.membership" varian="bahaya">Membership hangus — tanpa diskon</BaseBadge>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <CustomerPicker
            :pelanggan-id="form.pelanggan_id"
            :kendaraan-id="form.kendaraan_id"
            @terpilih="setelahPelanggan"
            @update:pelanggan-id="form.pelanggan_id = $event"
            @update:kendaraan-id="form.kendaraan_id = $event"
          />

          <div class="space-y-3">
            <div class="grid gap-3 sm:grid-cols-2">
              <BaseSelect v-model="form.mechanic_id" label="Mekanik Pengerjaan" :opsi="mekanik.map((m) => ({ value: m.id, label: m.name }))" />
              <BaseInput v-model="form.odometer" label="Odometer (km)" tipe="number" />
            </div>
            <FuelGauge v-model="form.fuel_level" :maks="pengaturan.jumlahBarBensin" />
            <BaseTextarea v-model="form.complaint" label="Keluhan Konsumen" :baris="2" />
          </div>
        </div>
      </div>

      <div v-if="adaKondisi" class="space-y-2">
        <CheckupItemsForm v-model:baris="kondisi" :bisa-edit="true" judul="Kondisi Kendaraan (dari Check Up)" />
      </div>
      <div v-else class="card flex flex-wrap items-center justify-between gap-2 p-4">
        <p class="text-sm text-slate-500">Kondisi kendaraan belum diisi.</p>
        <div class="flex gap-2">
          <BaseButton v-if="form.checkup_id" varian="garis" ukuran="sm" ikon="refresh" @click="muatKondisiDariCheckup">
            Ambil dari Check Up
          </BaseButton>
          <BaseButton
            v-else
            varian="garis"
            ukuran="sm"
            ikon="plus"
            @click="kondisi = []; adaKondisi = true; kondisi.push({ category: 'Umum', item_name: 'Catatan kondisi', status: 'ok', note: '', sort_order: 1 })"
          >
            Tambah Kondisi Manual
          </BaseButton>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
          <!-- PEKERJAAN -->
          <div class="card">
            <div class="card-header">
              <h3 class="text-sm font-semibold text-slate-700">Daftar Pekerjaan (Jasa)</h3>
              <span class="text-xs text-slate-400">Diskon member hanya berlaku untuk jasa</span>
            </div>
            <div class="border-b border-slate-100 p-3">
              <SearchSelect
                :pencari="cariJasa"
                placeholder="Cari pekerjaan dari master jasa…"
                kosong-teks="Jasa tidak ditemukan."
                @pilih="tambahJasa"
              >
                <template #item="{ item }">
                  <div class="flex items-center justify-between gap-2">
                    <span>{{ item.name }}</span>
                    <span class="tabular text-xs text-slate-500">{{ formatRupiah(item.price) }}</span>
                  </div>
                </template>
              </SearchSelect>
            </div>
            <div class="overflow-x-auto">
              <table class="table-app">
                <thead>
                  <tr>
                    <th>Pekerjaan</th><th>Harga</th><th class="w-20">Qty</th>
                    <th class="text-right">Diskon</th><th class="text-right">Subtotal</th><th class="w-10" />
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!jasa.length">
                    <td colspan="6" class="py-6 text-center text-xs text-slate-400">Belum ada pekerjaan dipilih.</td>
                  </tr>
                  <tr v-for="(j, i) in jasa" :key="i">
                    <td class="text-sm">{{ j.name }}</td>
                    <td class="tabular text-sm">{{ formatRupiah(j.price) }}</td>
                    <td>
                      <input v-model="j.qty" type="number" min="1" class="input-form py-1 text-sm" />
                    </td>
                    <td class="text-right text-xs text-emerald-600">
                      {{ isMember ? `${j.member_discount_percent ?? persenDiskon}%` : '-' }}
                    </td>
                    <td class="tabular text-right text-sm">
                      {{ formatRupiah((Number(j.price) * Number(j.qty)) * (1 - (isMember ? (j.member_discount_percent ?? persenDiskon) : 0) / 100)) }}
                    </td>
                    <td>
                      <BaseButton ukuran="ikon" varian="bahaya" ikon="trash" @click="hapusJasa(i)" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- SPAREPART -->
          <div class="card">
            <div class="card-header">
              <h3 class="text-sm font-semibold text-slate-700">Sparepart Diganti</h3>
              <span class="text-xs text-slate-400">Stok berkurang saat Form SA diselesaikan</span>
            </div>
            <div class="border-b border-slate-100 p-3">
              <SearchSelect
                :pencari="cariPart"
                placeholder="Cari sparepart dari master…"
                kosong-teks="Sparepart tidak ditemukan."
                @pilih="tambahPart"
              >
                <template #item="{ item }">
                  <div class="flex items-center justify-between gap-2">
                    <span>{{ item.name }}</span>
                    <span class="text-xs" :class="item.stock <= item.min_stock ? 'text-brand-600' : 'text-slate-500'">
                      stok {{ item.stock }} {{ item.unit }}
                    </span>
                  </div>
                </template>
              </SearchSelect>
            </div>
            <div class="overflow-x-auto">
              <table class="table-app">
                <thead>
                  <tr>
                    <th>Sparepart</th><th class="text-right">Harga</th><th class="w-20">Qty</th>
                    <th>Stok</th><th class="text-right">Subtotal</th><th class="w-10" />
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!part.length">
                    <td colspan="6" class="py-6 text-center text-xs text-slate-400">Belum ada sparepart dipilih.</td>
                  </tr>
                  <tr v-for="(p, i) in part" :key="i">
                    <td class="text-sm">
                      {{ p.name }}
                      <label class="mt-1 flex items-center gap-1 text-[11px] text-slate-500">
                        <input v-model="p.is_free_reward" type="checkbox" /> hadiah tukar poin (tanpa biaya)
                      </label>
                    </td>
                    <td class="tabular text-right text-sm">{{ formatRupiah(p.is_free_reward ? 0 : p.sell_price) }}</td>
                    <td>
                      <input v-model="p.qty" type="number" min="1" class="input-form py-1 text-sm" />
                    </td>
                    <td>
                      <span class="tabular text-sm" :class="Number(p.qty) > p.stock ? 'font-semibold text-brand-600' : ''">
                        {{ p.stock }}
                      </span>
                      <p v-if="Number(p.qty) > p.stock" class="text-[11px] text-brand-600">melebihi stok</p>
                    </td>
                    <td class="tabular text-right text-sm">{{ formatRupiah(p.is_free_reward ? 0 : Number(p.sell_price) * Number(p.qty)) }}</td>
                    <td>
                      <BaseButton ukuran="ikon" varian="bahaya" ikon="trash" @click="hapusPart(i)" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <BaseTextarea v-model="form.notes" label="Catatan Tambahan" :baris="2" />
        </div>

        <!-- RINGKASAN -->
        <div class="xl:col-span-1">
          <div class="card sticky top-20">
            <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Ringkasan Biaya (perkiraan)</h3></div>
            <dl class="divide-y divide-slate-100 text-sm">
              <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">Subtotal jasa</dt><dd class="tabular">{{ formatRupiah(ringkas.subtotalJasa) }}</dd></div>
              <div class="flex justify-between px-4 py-2.5">
                <dt class="text-slate-500">Diskon member {{ isMember ? `(${persenDiskon}%)` : '' }}</dt>
                <dd class="tabular text-emerald-600">- {{ formatRupiah(ringkas.diskon) }}</dd>
              </div>
              <div class="flex justify-between px-4 py-2.5"><dt class="font-medium text-slate-600">Total jasa</dt><dd class="tabular font-medium">{{ formatRupiah(ringkas.totalJasa) }}</dd></div>
              <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">Total sparepart</dt><dd class="tabular">{{ formatRupiah(ringkas.totalPart) }}</dd></div>
              <div class="flex justify-between bg-brand-50 px-4 py-3">
                <dt class="font-semibold text-brand-700">Total SA</dt>
                <dd class="tabular text-lg font-bold text-brand-700">{{ formatRupiah(ringkas.grandTotal) }}</dd>
              </div>
            </dl>
            <p class="border-t border-slate-100 px-4 py-2 text-[11px] text-slate-400">
              Angka final dihitung server saat disimpan.
            </p>

            <div class="flex flex-col gap-2 p-4">
              <BaseButton ikon="save" blok :memuat="menyimpan" @click="simpanDanTutup">Simpan Form SA</BaseButton>
              <BaseButton varian="garis" ikon="play" blok :memuat="menyimpan" @click="mulai">Simpan &amp; Mulai Dikerjakan</BaseButton>
              <BaseButton varian="garis" ikon="arrowLeft" blok @click="router.push('/service-orders')">Batal</BaseButton>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>