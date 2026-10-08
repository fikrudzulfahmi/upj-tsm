<script setup>
/**
 * Modal tambah/ubah pelanggan beserta daftar kendaraannya.
 */
import { computed, reactive, ref, watch } from 'vue'
import { pelangganApi } from '@/api'
import { galatValidasi, pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { TIPE_KENDARAAN } from '@/utils/status'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  pelanggan: { type: Object, default: null }, // null = tambah baru
})
const emit = defineEmits(['update:modelValue', 'tersimpan'])

const ui = useUiStore()
const memuat = ref(false)
const kunciIdem = ref((globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`))
const galat = ref({})
const form = reactive(bentukAwal())

const judul = computed(() => (props.pelanggan ? `Ubah ${props.pelanggan.name}` : 'Tambah Pelanggan Baru'))

function bentukAwal() {
  return { name: '', gender: 'L', phone: '', address: '', notes: '', vehicles: [] }
}

function kendaraanBaru() {
  return { id: null, plate_number: '', type: 'motor', brand: '', model: '', year: '', color: '', last_odometer: '' }
}

watch(
  () => props.modelValue,
  (buka) => {
    if (!buka) return
    galat.value = {}
    const p = props.pelanggan
    Object.assign(form, bentukAwal())
    if (p) {
      Object.assign(form, {
        name: p.name || '',
        gender: p.gender || 'L',
        phone: p.phone || '',
        address: p.address || '',
        notes: p.notes || '',
        vehicles: (p.vehicles || []).map((k) => ({
          id: k.id,
          plate_number: k.plate_number,
          type: k.type,
          brand: k.brand || '',
          model: k.model || '',
          year: k.year || '',
          color: k.color || '',
          last_odometer: k.last_odometer || '',
        })),
      })
    }
    if (!form.vehicles.length) form.vehicles.push(kendaraanBaru())
  },
)

function tambahKendaraan() {
  form.vehicles.push(kendaraanBaru())
}
function hapusKendaraan(i) {
  form.vehicles.splice(i, 1)
}

async function simpan() {
  if (memuat.value) return
  galat.value = {}
  if (!form.name.trim()) {
    galat.value = { name: 'Nama pelanggan wajib diisi.' }
    return
  }

  memuat.value = true
  try {
    const muatan = {
      idempotency_key: props.pelanggan ? undefined : kunciIdem.value,
      name: form.name.trim(),
      gender: form.gender,
      phone: form.phone.trim() || null,
      address: form.address.trim() || null,
      notes: form.notes.trim() || null,
      vehicles: form.vehicles
        .filter((k) => k.plate_number.trim() !== '')
        .map((k) => ({
          id: k.id || undefined,
          plate_number: k.plate_number,
          type: k.type,
          brand: k.brand || null,
          model: k.model || null,
          year: k.year ? Number(k.year) : null,
          color: k.color || null,
          last_odometer: k.last_odometer ? Number(k.last_odometer) : null,
        })),
    }

    const res = props.pelanggan
      ? await pelangganApi.ubah(props.pelanggan.id, muatan)
      : await pelangganApi.simpan(muatan)

    ui.sukses(props.pelanggan ? 'Data pelanggan diperbarui.' : 'Pelanggan berhasil disimpan.')
    emit('tersimpan', res.data)
    emit('update:modelValue', false)
  } catch (e) {
    galat.value = galatValidasi(e)
    if (!Object.keys(galat.value).length) ui.gagal(pesanGalat(e))
  } finally {
    memuat.value = false
  }
}
</script>

<template>
  <BaseModal :model-value="modelValue" :judul="judul" lebar="xl" @update:model-value="emit('update:modelValue', $event)">
    <div class="space-y-4">
      <div class="grid gap-3 sm:grid-cols-2">
        <BaseInput v-model="form.name" label="Nama Pelanggan" diperlukan :galat="galat.name" placeholder="Nama lengkap" />
        <BaseSelect
          v-model="form.gender"
          label="Jenis Kelamin"
          :opsi="[{ value: 'L', label: 'Laki-laki' }, { value: 'P', label: 'Perempuan' }]"
          :boleh-kosong="false"
        />
        <BaseInput v-model="form.phone" label="No. HP" :galat="galat.phone" placeholder="08xxxxxxxxxx" petunjuk="Dipakai untuk login portal member" />
        <BaseTextarea v-model="form.address" label="Alamat" :baris="1" />
      </div>
      <BaseTextarea v-model="form.notes" label="Catatan Internal" :baris="2" />

      <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
        <div class="mb-2 flex items-center justify-between">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kendaraan</p>
          <BaseButton ukuran="sm" varian="halus" ikon="plus" @click="tambahKendaraan">Tambah Kendaraan</BaseButton>
        </div>

        <p v-if="!form.vehicles.length" class="py-3 text-center text-xs text-slate-400">
          Belum ada kendaraan. Klik "Tambah Kendaraan".
        </p>

        <div v-for="(k, i) in form.vehicles" :key="i" class="mb-2 rounded-lg border border-slate-200 bg-white p-3">
          <div class="grid gap-2 sm:grid-cols-3">
            <BaseInput v-model="k.plate_number" label="Nomor Polisi" :galat="galat[`vehicles.${i}.plate_number`]" placeholder="AB 1234 CD" />
            <BaseSelect v-model="k.type" label="Jenis" :opsi="TIPE_KENDARAAN" :boleh-kosong="false" />
            <BaseInput v-model="k.brand" label="Merek" placeholder="Honda" />
            <BaseInput v-model="k.model" label="Model" placeholder="Beat" />
            <BaseInput v-model="k.year" label="Tahun" tipe="number" placeholder="2021" />
            <BaseInput v-model="k.color" label="Warna" placeholder="Hitam" />
          </div>
          <div class="mt-2 flex justify-end">
            <BaseButton ukuran="sm" varian="bahaya" ikon="trash" @click="hapusKendaraan(i)">Hapus baris</BaseButton>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <BaseButton varian="garis" @click="emit('update:modelValue', false)">Batal</BaseButton>
      <BaseButton ikon="save" :memuat="memuat" @click="simpan">Simpan</BaseButton>
    </template>
  </BaseModal>
</template>
