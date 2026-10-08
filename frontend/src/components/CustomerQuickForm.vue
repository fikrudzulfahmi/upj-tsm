<script setup>
/**
 * Modal tambah pelanggan (+ kendaraan pertama) langsung dari dalam form Check Up / SA.
 */
import { reactive, ref, watch } from 'vue'
import { pelangganApi } from '@/api'
import { galatValidasi } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import { TIPE_KENDARAAN } from '@/utils/status'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  tampilkanKendaraan: { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue', 'tersimpan'])

const ui = useUiStore()
const memuat = ref(false)
const galat = ref({})
const form = reactive(bentukAwal())

function bentukAwal() {
  return {
    name: '',
    gender: 'L',
    phone: '',
    address: '',
    notes: '',
    plate_number: '',
    type: 'motor',
    brand: '',
    model: '',
    year: '',
    color: '',
  }
}

watch(
  () => props.modelValue,
  (buka) => {
    if (buka) {
      Object.assign(form, bentukAwal())
      galat.value = {}
    }
  },
)

async function simpan() {
  galat.value = {}
  if (!form.name.trim()) {
    galat.value = { name: 'Nama pelanggan wajib diisi.' }
    return
  }
  memuat.value = true
  try {
    const muatan = {
      name: form.name.trim(),
      gender: form.gender,
      phone: form.phone.trim() || null,
      address: form.address.trim() || null,
      notes: form.notes.trim() || null,
      vehicles: form.plate_number.trim()
        ? [
            {
              plate_number: form.plate_number.trim(),
              type: form.type,
              brand: form.brand.trim() || null,
              model: form.model.trim() || null,
              year: form.year ? Number(form.year) : null,
              color: form.color.trim() || null,
            },
          ]
        : [],
    }
    const res = await pelangganApi.simpan(muatan)
    ui.sukses('Pelanggan baru berhasil disimpan.')
    emit('tersimpan', res.data)
    emit('update:modelValue', false)
  } catch (error) {
    galat.value = galatValidasi(error)
    if (!Object.keys(galat.value).length) ui.gagal(error?.response?.data?.message || 'Gagal menyimpan pelanggan.')
  } finally {
    memuat.value = false
  }
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    judul="Tambah Pelanggan Baru"
    lebar="lg"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="space-y-3">
      <div class="grid gap-3 sm:grid-cols-2">
        <BaseInput v-model="form.name" label="Nama Pelanggan" diperlukan :galat="galat.name" placeholder="Nama lengkap" />
        <BaseSelect v-model="form.gender" label="Jenis Kelamin" :opsi="[{ value: 'L', label: 'Laki-laki' }, { value: 'P', label: 'Perempuan' }]" :boleh-kosong="false" />
        <BaseInput v-model="form.phone" label="No. HP" :galat="galat.phone" placeholder="08xxxxxxxxxx" />
        <div class="sm:col-span-2">
          <BaseTextarea v-model="form.address" label="Alamat" :baris="2" placeholder="Alamat (opsional)" />
        </div>
      </div>

      <div v-if="tampilkanKendaraan" class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Kendaraan pertama</p>
        <div class="grid gap-3 sm:grid-cols-2">
          <BaseInput v-model="form.plate_number" label="Nomor Polisi" :galat="galat['vehicles.0.plate_number']" placeholder="AB 1234 CD" />
          <BaseSelect v-model="form.type" label="Jenis" :opsi="TIPE_KENDARAAN" :boleh-kosong="false" />
          <BaseInput v-model="form.brand" label="Merek" placeholder="Honda / Yamaha" />
          <BaseInput v-model="form.model" label="Model" placeholder="Beat / Vario" />
          <BaseInput v-model="form.year" label="Tahun" tipe="number" placeholder="2020" />
          <BaseInput v-model="form.color" label="Warna" placeholder="Hitam" />
        </div>
        <p class="mt-2 text-[11px] text-slate-400">Kosongkan nomor polisi bila belum ada kendaraan.</p>
      </div>

      <BaseTextarea v-model="form.notes" label="Catatan" :baris="2" placeholder="Catatan internal (opsional)" />
    </div>

    <template #footer>
      <BaseButton varian="garis" @click="emit('update:modelValue', false)">Batal</BaseButton>
      <BaseButton ikon="save" :memuat="memuat" @click="simpan">Simpan Pelanggan</BaseButton>
    </template>
  </BaseModal>
</template>
