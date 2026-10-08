<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { templateApi } from '@/api'
import { galatValidasi, pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const ui = useUiStore()
const memuat = ref(true)
const templates = ref([])
const dipilih = ref(null)
const detail = ref(null)
const memuatDetail = ref(false)
const modalItem = ref(false)
const menyimpan = ref(false)
const galat = ref({})
const sedangUbahItem = ref(null)
const form = reactive({ category: '', name: '', sort_order: 0, is_active: true })

const kelompok = computed(() => {
  const grup = new Map()
  ;(detail.value?.items || []).forEach((i) => {
    if (!grup.has(i.category)) grup.set(i.category, [])
    grup.get(i.category).push(i)
  })
  return [...grup.entries()].map(([kategori, items]) => ({ kategori, items }))
})

async function muatTemplate() {
  memuat.value = true
  try {
    const res = await templateApi.daftar()
    templates.value = res.data || []
    if (templates.value.length) {
      dipilih.value = templates.value[0].id
      await muatDetail()
    }
  } finally {
    memuat.value = false
  }
}

async function muatDetail() {
  if (!dipilih.value) return
  memuatDetail.value = true
  try {
    const res = await templateApi.detail(dipilih.value)
    detail.value = res.data
  } finally {
    memuatDetail.value = false
  }
}

onMounted(muatTemplate)

function bukaTambah() {
  sedangUbahItem.value = null
  Object.assign(form, { category: '', name: '', sort_order: 0, is_active: true })
  galat.value = {}
  modalItem.value = true
}

function bukaUbah(item) {
  sedangUbahItem.value = item
  Object.assign(form, { category: item.category, name: item.name, sort_order: item.sort_order, is_active: item.is_active })
  galat.value = {}
  modalItem.value = true
}

async function simpanItem() {
  galat.value = {}
  menyimpan.value = true
  try {
    if (sedangUbahItem.value) {
      await templateApi.ubahItem(dipilih.value, sedangUbahItem.value.id, { ...form })
    } else {
      await templateApi.tambahItem(dipilih.value, { ...form })
    }
    ui.sukses('Item pemeriksaan tersimpan.')
    modalItem.value = false
    await muatDetail()
    await muatTemplate()
  } catch (e) {
    galat.value = galatValidasi(e)
    if (!Object.keys(galat.value).length) ui.gagal(pesanGalat(e))
  } finally {
    menyimpan.value = false
  }
}

async function hapusItem(item) {
  const ya = await ui.tanya({
    judul: 'Hapus item',
    pesan: `Hapus item "${item.name}"? Riwayat check up lama tidak berubah karena nama item sudah disalin.`,
    teksOk: 'Hapus',
  })
  if (!ya) return
  await templateApi.hapusItem(dipilih.value, item.id)
  ui.sukses('Item dihapus.')
  await muatDetail()
  await muatTemplate()
}

async function ubahUrutan(daftar, indeks, arah) {
  const target = indeks + arah
  if (target < 0 || target >= daftar.length) return
  const baru = [...daftar]
  ;[baru[indeks], baru[target]] = [baru[target], baru[indeks]]
  await templateApi.urutItem(dipilih.value, { items: baru.map((i) => i.id) })
  await muatDetail()
}

async function toggleAktif(item) {
  await templateApi.ubahItem(dipilih.value, item.id, {
    category: item.category, name: item.name, sort_order: item.sort_order, is_active: !item.is_active,
  })
  await muatDetail()
}
</script>

<template>
  <div class="space-y-4">
    <div class="card p-4">
      <div class="flex flex-wrap items-end gap-3">
        <div class="w-72">
          <label class="label-form">Pilih Template</label>
          <select v-model="dipilih" class="input-form" @change="muatDetail">
            <option v-for="t in templates" :key="t.id" :value="t.id">
              {{ t.name }} ({{ t.vehicle_type || 'semua jenis' }})
            </option>
          </select>
        </div>
        <BaseButton varian="garis" ikon="refresh" @click="muatTemplate">Muat Ulang</BaseButton>
        <div class="ml-auto">
          <BaseButton ikon="plus" @click="bukaTambah" :nonaktif="!dipilih">Item Baru</BaseButton>
        </div>
      </div>
      <p class="mt-2 text-xs text-slate-500">
        Item pemeriksaan dipakai saat membuat Check Up. Saat check up disimpan, nama item
        <strong>disalin</strong> ke hasil, sehingga perubahan template tidak merusak riwayat.
      </p>
    </div>

    <LoadingBlock v-if="memuat || memuatDetail" />

    <div v-else-if="detail" class="space-y-3">
      <div v-for="g in kelompok" :key="g.kategori" class="card">
        <div class="card-header">
          <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <AppIcon nama="listChecks" :ukuran="15" class="text-brand-600" /> {{ g.kategori }}
          </h3>
          <span class="text-xs text-slate-400">{{ g.items.length }} item</span>
        </div>
        <ul class="divide-y divide-slate-100">
          <li v-for="(item, idx) in g.items" :key="item.id" class="flex items-center justify-between gap-2 px-4 py-2">
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-400">{{ item.sort_order }}</span>
              <span class="text-sm" :class="item.is_active ? 'text-slate-700' : 'text-slate-400 line-through'">
                {{ item.name }}
              </span>
              <BaseBadge v-if="!item.is_active" varian="netral" ukuran="sm">nonaktif</BaseBadge>
            </div>
            <div class="flex items-center gap-1">
              <BaseButton ukuran="ikon" varian="halus" ikon="chevronUp" title="Naik" @click="ubahUrutan(g.items, idx, -1)" />
              <BaseButton ukuran="ikon" varian="halus" ikon="chevronDown" title="Turun" @click="ubahUrutan(g.items, idx, 1)" />
              <BaseButton ukuran="ikon" varian="garis" :ikon="item.is_active ? 'ban' : 'check'" :title="item.is_active ? 'Nonaktifkan' : 'Aktifkan'" @click="toggleAktif(item)" />
              <BaseButton ukuran="ikon" varian="garis" ikon="pencil" title="Ubah" @click="bukaUbah(item)" />
              <BaseButton ukuran="ikon" varian="bahaya" ikon="trash" title="Hapus" @click="hapusItem(item)" />
            </div>
          </li>
        </ul>
      </div>

      <div v-if="!kelompok.length" class="card p-8 text-center text-sm text-slate-400">
        Template ini belum punya item.
      </div>
    </div>

    <BaseModal v-model="modalItem" :judul="sedangUbahItem ? 'Ubah Item Pemeriksaan' : 'Item Pemeriksaan Baru'" lebar="sm">
      <div class="space-y-3">
        <BaseInput v-model="form.category" label="Kategori" diperlukan :galat="galat.category" placeholder="mis. Mesin, Rem, Kelistrikan" />
        <BaseInput v-model="form.name" label="Nama Item" diperlukan :galat="galat.name" placeholder="mis. Kampas rem depan" />
        <BaseInput v-model="form.sort_order" label="Urutan" tipe="number" />
        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input v-model="form.is_active" type="checkbox" /> Aktif (muncul di form Check Up)
        </label>
      </div>
      <template #footer>
        <BaseButton varian="garis" @click="modalItem = false">Batal</BaseButton>
        <BaseButton ikon="save" :memuat="menyimpan" @click="simpanItem">Simpan</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
