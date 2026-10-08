<script setup>
/**
 * Pengelolaan Template Check Up: daftar template + item pemeriksaannya.
 * Template bisa dibuat, disalin, diubah, dan dihapus — tidak lagi hanya item.
 */
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
const modalTemplate = ref(false)
const menyimpan = ref(false)
const menyimpanTemplate = ref(false)
const galat = ref({})
const galatTemplate = ref({})
const sedangUbahItem = ref(null)
const sedangUbahTemplate = ref(null)
const form = reactive({ category: '', name: '', sort_order: 0, is_active: true })
const formTemplate = reactive({ name: '', vehicle_type: '', is_active: true })

const OPSI_JENIS = [
  { value: 'motor', label: 'Motor' },
  { value: 'mobil', label: 'Mobil' },
]

const kelompok = computed(() => {
  const grup = new Map()
  ;(detail.value?.items || []).forEach((i) => {
    if (!grup.has(i.category)) grup.set(i.category, [])
    grup.get(i.category).push(i)
  })
  return [...grup.entries()].map(([kategori, items]) => ({ kategori, items }))
})

const labelJenis = (v) => (v === 'motor' ? 'Motor' : v === 'mobil' ? 'Mobil' : 'Semua jenis kendaraan')

async function muatTemplate(pilihId = null) {
  memuat.value = true
  try {
    const res = await templateApi.daftar()
    templates.value = res.data || []

    if (pilihId && templates.value.some((t) => t.id === pilihId)) {
      dipilih.value = pilihId
    } else if (!templates.value.some((t) => t.id === dipilih.value)) {
      dipilih.value = templates.value[0]?.id ?? null
    }

    if (dipilih.value) {
      await muatDetail()
    } else {
      detail.value = null
    }
  } finally {
    memuat.value = false
  }
}

async function muatDetail() {
  if (!dipilih.value) {
    detail.value = null
    return
  }
  memuatDetail.value = true
  try {
    const res = await templateApi.detail(dipilih.value)
    detail.value = res.data
  } finally {
    memuatDetail.value = false
  }
}

onMounted(() => muatTemplate())

/* ------------------------------------------------------------ template */

function bukaTambahTemplate() {
  sedangUbahTemplate.value = null
  Object.assign(formTemplate, { name: '', vehicle_type: 'motor', is_active: true })
  galatTemplate.value = {}
  modalTemplate.value = true
}

function bukaUbahTemplate() {
  if (!detail.value) return
  sedangUbahTemplate.value = detail.value
  Object.assign(formTemplate, {
    name: detail.value.name,
    vehicle_type: detail.value.vehicle_type || '',
    is_active: detail.value.is_active,
  })
  galatTemplate.value = {}
  modalTemplate.value = true
}

async function simpanTemplate() {
  galatTemplate.value = {}
  menyimpanTemplate.value = true
  try {
    const muatan = {
      name: formTemplate.name,
      vehicle_type: formTemplate.vehicle_type || null,
      is_active: !!formTemplate.is_active,
    }
    const res = sedangUbahTemplate.value
      ? await templateApi.ubah(sedangUbahTemplate.value.id, muatan)
      : await templateApi.simpan(muatan)

    ui.sukses(sedangUbahTemplate.value ? 'Template diperbarui.' : 'Template baru dibuat. Tambahkan item pemeriksaannya.')
    modalTemplate.value = false
    await muatTemplate(res.data?.id)
  } catch (e) {
    galatTemplate.value = galatValidasi(e)
    if (!Object.keys(galatTemplate.value).length) ui.gagal(pesanGalat(e))
  } finally {
    menyimpanTemplate.value = false
  }
}

async function duplikatTemplate() {
  if (!detail.value) return
  const ya = await ui.tanya({
    judul: 'Salin template',
    pesan: `Salin "${detail.value.name}" beserta ${detail.value.items?.length || 0} item pemeriksaannya?`,
    teksOk: 'Salin',
  })
  if (!ya) return

  menyimpanTemplate.value = true
  try {
    // Penyalinan item dikerjakan SERVER dalam satu transaksi: tidak bisa tersalin
    // separuh, dan tidak perlu 29 permintaan berurutan untuk template besar.
    const baru = await templateApi.duplikat(detail.value.id)
    ui.sukses(`Template "${baru.data.name}" dibuat dari salinan.`)
    await muatTemplate(baru.data.id)
  } catch (e) {
    ui.gagal(pesanGalat(e))
  } finally {
    menyimpanTemplate.value = false
  }
}

async function hapusTemplate() {
  if (!detail.value) return
  const ya = await ui.tanya({
    judul: 'Hapus template',
    pesan: `Hapus template "${detail.value.name}" beserta ${detail.value.items?.length || 0} itemnya? Riwayat check up lama tidak terpengaruh.`,
    teksOk: 'Hapus',
  })
  if (!ya) return

  try {
    await templateApi.hapus(detail.value.id)
    ui.sukses('Template dihapus.')
    dipilih.value = null
    detail.value = null
    await muatTemplate()
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}

/* ---------------------------------------------------------------- item */

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
            <option v-if="!templates.length" :value="null">— belum ada template —</option>
            <option v-for="t in templates" :key="t.id" :value="t.id">
              {{ t.name }} ({{ t.vehicle_type || 'semua jenis' }})
            </option>
          </select>
        </div>

        <BaseButton ikon="plus" @click="bukaTambahTemplate">Template Baru</BaseButton>

        <div class="ml-auto flex flex-wrap gap-2">
          <BaseButton varian="garis" ikon="copy" :nonaktif="!detail" :memuat="menyimpanTemplate" @click="duplikatTemplate">
            Duplikat
          </BaseButton>
          <BaseButton varian="garis" ikon="pencil" :nonaktif="!detail" @click="bukaUbahTemplate">Ubah Template</BaseButton>
          <BaseButton varian="bahaya" ikon="trash" :nonaktif="!detail" @click="hapusTemplate">Hapus</BaseButton>
          <BaseButton varian="halus" ikon="plus" :nonaktif="!dipilih" @click="bukaTambah">Item Baru</BaseButton>
        </div>
      </div>

      <div v-if="detail" class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500">
        <span class="flex items-center gap-1.5 font-medium text-slate-700">
          <AppIcon nama="listChecks" :ukuran="14" class="text-brand-600" /> {{ detail.name }}
        </span>
        <BaseBadge varian="netral" ukuran="sm">{{ labelJenis(detail.vehicle_type) }}</BaseBadge>
        <BaseBadge :varian="detail.is_active ? 'sukses' : 'netral'" ukuran="sm">
          {{ detail.is_active ? 'aktif' : 'nonaktif' }}
        </BaseBadge>
        <span>{{ detail.items?.length || 0 }} item</span>
      </div>

      <p class="mt-2 text-xs text-slate-500">
        Item pemeriksaan dipakai saat membuat Check Up. Saat check up disimpan, nama item
        <strong>disalin</strong> ke hasil, sehingga perubahan template tidak merusak riwayat.
        Template dipilih otomatis berdasarkan jenis kendaraan pelanggan (motor/mobil).
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
        Template ini belum punya item. Tekan <strong>Item Baru</strong> untuk menambah pemeriksaan.
      </div>
    </div>

    <div v-else-if="!memuat" class="card p-8 text-center text-sm text-slate-500">
      Belum ada template check up. Tekan <strong>Template Baru</strong> untuk membuatnya.
    </div>

    <!-- modal template -->
    <BaseModal v-model="modalTemplate" :judul="sedangUbahTemplate ? 'Ubah Template' : 'Template Check Up Baru'" lebar="sm">
      <div class="space-y-3">
        <BaseInput v-model="formTemplate.name" label="Nama Template" diperlukan :galat="galatTemplate.name" placeholder="mis. General Check Up Motor" />
        <BaseSelect
          v-model="formTemplate.vehicle_type"
          label="Jenis Kendaraan"
          :opsi="OPSI_JENIS"
          boleh-kosong
          placeholder="Semua jenis kendaraan"
          petunjuk="Template ini dipakai otomatis sesuai jenis kendaraan pelanggan"
        />
        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input v-model="formTemplate.is_active" type="checkbox" /> Aktif
        </label>
      </div>
      <template #footer>
        <BaseButton varian="garis" @click="modalTemplate = false">Batal</BaseButton>
        <BaseButton ikon="save" :memuat="menyimpanTemplate" @click="simpanTemplate">Simpan</BaseButton>
      </template>
    </BaseModal>

    <!-- modal item -->
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
