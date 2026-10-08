<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { checkupApi, templateApi } from '@/api'
import { galatValidasi, pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { usePengaturanStore } from '@/stores/pengaturan'
import { buatBarisDariTemplate } from '@/utils/checkup'
import { tanggalHariIni } from '@/utils/format'
import CustomerPicker from '@/components/CustomerPicker.vue'
import CheckupItemsForm from '@/components/CheckupItemsForm.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const pengaturan = usePengaturanStore()

const idUbah = computed(() => route.params.id || null)
const memuat = ref(!!route.params.id)
const menyimpan = ref(false)
const galat = ref({})
const modalSelesai = ref(false)
const template = ref(null)
const pelangganTerpilih = ref(null)
const kendaraanTerpilih = ref(null)

const form = reactive({
  pelanggan_id: null,
  kendaraan_id: null,
  checkup_date: tanggalHariIni(),
  odometer: '',
  complaint: '',
  general_notes: '',
  results: [],
})

watch(
  () => form.kendaraan_id,
  async (baru, lama) => {
    if (!baru || baru === lama) return
    await pilihTemplateUntukKendaraan()
  },
)

async function pilihTemplateUntukKendaraan() {
  const tipe = kendaraanTerpilih.value?.type || 'motor'
  const res = await templateApi.daftar({ vehicle_type: tipe })
  const daftar = res.data || []
  template.value = daftar.find((t) => t.vehicle_type === tipe) || daftar[0] || null

  if (!template.value) {
    ui.gagal('Belum ada template check up untuk jenis kendaraan ini. Atur di Pengaturan → Template Check Up.')
    form.results = []
    return
  }

  const detail = await templateApi.detail(template.value.id)
  const hasilLama = form.results || []
  form.results = buatBarisDariTemplate(detail.data.items.filter((i) => i.is_active), hasilLama)
}

function setelahPelanggan({ customer, vehicle }) {
  pelangganTerpilih.value = customer
  kendaraanTerpilih.value = vehicle
  form.pelanggan_id = customer?.id || null
  form.kendaraan_id = vehicle?.id || null
}

onMounted(async () => {
  // Form baru: tampilkan langsung template default (motor) agar item pemeriksaan
  // sudah terlihat sebelum pelanggan/kendaraan dipilih.
  if (!idUbah.value) {
    await pilihTemplateUntukKendaraan()
    return
  }
  try {
    const res = await checkupApi.detail(idUbah.value)
    const c = res.data
    form.pelanggan_id = c.customer_id
    form.kendaraan_id = c.vehicle_id
    form.checkup_date = c.checkup_date
    form.odometer = c.odometer ?? ''
    form.complaint = c.complaint || ''
    form.general_notes = c.general_notes || ''
    form.results = (c.results || []).map((r) => ({
      category: r.category,
      item_name: r.item_name,
      status: r.status,
      note: r.note || '',
      sort_order: r.sort_order,
    }))
    pelangganTerpilih.value = c.customer
    kendaraanTerpilih.value = c.vehicle
  } catch {
    router.replace('/checkups')
  } finally {
    memuat.value = false
  }
})

async function simpan(diam = false) {
  galat.value = {}
  if (!form.pelanggan_id || !form.kendaraan_id) {
    galat.value = { pelanggan_id: 'Pelanggan dan kendaraan wajib dipilih.' }
    ui.gagal('Pelanggan dan kendaraan wajib dipilih.')
    return null
  }

  menyimpan.value = true
  try {
    const muatan = {
      customer_id: form.pelanggan_id,
      vehicle_id: form.kendaraan_id,
      checkup_template_id: template.value?.id || null,
      checkup_date: form.checkup_date,
      odometer: form.odometer === '' ? null : Number(form.odometer),
      complaint: form.complaint,
      general_notes: form.general_notes,
      results: form.results,
    }

    const res = idUbah.value ? await checkupApi.ubah(idUbah.value, muatan) : await checkupApi.simpan(muatan)

    if (!diam) ui.sukses('Data check up tersimpan.')
    return res.data
  } catch (e) {
    galat.value = galatValidasi(e)
    if (!Object.keys(galat.value).length) ui.gagal(pesanGalat(e))
    return null
  } finally {
    menyimpan.value = false
  }
}

async function simpanDraft() {
  const data = await simpan()
  if (data) router.push(`/checkups/${data.id}`)
}

async function selesaikan(hasil) {
  const data = await simpan(true)
  if (!data) return

  try {
    const res = await checkupApi.selesai(data.id, { result: hasil })
    modalSelesai.value = false

    if (hasil === 'continue_service' && res.data.service_order_id) {
      ui.sukses('Check up selesai. Form SA sudah dibuat otomatis.')
      router.push(`/service-orders/${res.data.service_order_id}/edit`)
    } else {
      ui.sukses('Check up selesai (tanpa service).')
      router.push(`/checkups/${data.id}`)
    }
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}
</script>

<template>
  <div class="space-y-4">
    <LoadingBlock v-if="memuat" v-slot />

    <template v-else>
      <div class="card p-4">
        <div class="mb-3 flex items-center gap-2">
          <AppIcon nama="clipboardCheck" :ukuran="18" class="text-brand-600" />
          <h2 class="text-sm font-semibold text-slate-700">Data Kunjungan</h2>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <CustomerPicker
            :pelanggan-id="form.pelanggan_id"
            :kendaraan-id="form.kendaraan_id"
            :galat-pelanggan="galat.pelanggan_id"
            @terpilih="setelahPelanggan"
            @update:pelanggan-id="form.pelanggan_id = $event"
            @update:kendaraan-id="form.kendaraan_id = $event"
          />

          <div class="space-y-3">
            <div class="grid gap-3 sm:grid-cols-2">
              <BaseInput v-model="form.checkup_date" label="Tanggal Check Up" tipe="date" />
              <BaseInput v-model="form.odometer" label="Odometer (km)" tipe="number" placeholder="mis. 12500" />
            </div>
            <BaseTextarea v-model="form.complaint" label="Keluhan Konsumen" :baris="3" placeholder="Apa yang dikeluhkan pelanggan?" />
            <BaseTextarea v-model="form.general_notes" label="Catatan Umum Petugas" :baris="2" />
          </div>
        </div>
      </div>

      <div v-if="template" class="flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800">
        <AppIcon nama="info" :ukuran="14" />
        Template terpakai: <strong>{{ template.name }}</strong> ({{ form.results.length }} item).
        Tambah/ubah item di Pengaturan → Template Check Up.
      </div>

      <CheckupItemsForm v-model:baris="form.results" :bisa-edit="true" />

      <div class="no-print sticky bottom-0 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur">
        <p class="text-xs text-slate-500">
          Jumlah bar bensin: {{ pengaturan.jumlahBarBensin }} · item diperiksa {{ form.results.length }}
        </p>
        <div class="flex flex-wrap gap-2">
          <BaseButton varian="garis" ikon="arrowLeft" @click="router.push('/checkups')">Batal</BaseButton>
          <BaseButton varian="garis" ikon="save" :memuat="menyimpan" @click="simpanDraft">Simpan Draft</BaseButton>
          <BaseButton ikon="checkCircle" @click="modalSelesai = true">Selesai</BaseButton>
        </div>
      </div>
    </template>

    <BaseModal v-model="modalSelesai" judul="Selesaikan Check Up" lebar="sm">
      <p class="text-sm text-slate-600">Pilih tindak lanjut untuk kunjungan ini:</p>
      <div class="mt-4 grid gap-2">
        <button
          class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-left hover:border-brand-400 hover:bg-brand-50"
          @click="selesaikan('checkup_only')"
        >
          <AppIcon nama="clipboardCheck" :ukuran="20" class="mt-0.5 text-slate-500" />
          <span>
            <span class="block text-sm font-semibold text-slate-700">Hanya Check Up</span>
            <span class="block text-xs text-slate-500">Selesai tanpa servis. Unit entry tercatat sebagai check up.</span>
          </span>
        </button>
        <button
          class="flex items-start gap-3 rounded-lg border border-brand-200 bg-brand-50/50 p-3 text-left hover:border-brand-500 hover:bg-brand-50"
          @click="selesaikan('continue_service')"
        >
          <AppIcon nama="wrench" :ukuran="20" class="mt-0.5 text-brand-600" />
          <span>
            <span class="block text-sm font-semibold text-brand-700">Lanjut Service (Form SA)</span>
            <span class="block text-xs text-slate-500">
              Form SA dibuat otomatis: pelanggan, keluhan, dan kondisi kendaraan sudah terisi.
            </span>
          </span>
        </button>
      </div>
    </BaseModal>
  </div>
</template>
