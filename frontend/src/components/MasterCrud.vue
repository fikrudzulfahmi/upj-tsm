<script setup>
/**
 * Halaman CRUD master generik: tabel + pencarian + modal form.
 * Dipakai oleh Mekanik, Jasa, Reward, dan Pengguna agar tidak ada duplikasi.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { galatValidasi, pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { useDaftar } from '@/composables/useDaftar'
import BaseTable from '@/components/ui/BaseTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import Pagination from '@/components/ui/Pagination.vue'

const props = defineProps({
  judul: { type: String, required: true },
  kolom: { type: Array, required: true },
  /** async (filter) => amplop { data, meta } */
  ambil: { type: Function, required: true },
  /** async (payload) => result (tambah) */
  simpan: { type: Function, required: true },
  /** async (id, payload) => result (ubah) — kosong = tidak bisa diubah */
  ubah: { type: Function, default: null },
  /** async (id) => result */
  hapus: { type: Function, default: null },
  /** [{ kunci, label, tipe, wajib, opsi, petunjuk, kosong, lebar }] */
  bidang: { type: Array, required: true },
  nilaiAwal: { type: Object, default: () => ({}) },
  kosongTeks: { type: String, default: 'Belum ada data.' },
  cariPlaceholder: { type: String, default: 'Cari…' },
  teksTombol: { type: String, default: 'Tambah' },
  pesanHapus: { type: Function, default: null },
  filterTambahan: { type: Object, default: () => ({}) },
  bisaTambah: { type: Boolean, default: true },
  bolehCari: { type: Boolean, default: true },
})

const emit = defineEmits(['tersimpan'])

const ui = useUiStore()
const daftar = useDaftar(props.ambil, { filter: { ...props.filterTambahan } })

const modal = ref(false)
const menyimpan = ref(false)
const galat = ref({})
const sedangUbah = ref(null)
const form = reactive({})

function reset() {
  Object.keys(form).forEach((k) => delete form[k])
  Object.assign(form, JSON.parse(JSON.stringify(props.nilaiAwal)))
}

function bukaTambah() {
  reset()
  sedangUbah.value = null
  galat.value = {}
  modal.value = true
}

function bukaUbah(baris) {
  reset()
  props.bidang.forEach((b) => {
    if (baris[b.kunci] !== undefined && baris[b.kunci] !== null) form[b.kunci] = baris[b.kunci]
  })
  sedangUbah.value = baris
  galat.value = {}
  modal.value = true
}

async function simpanForm() {
  galat.value = {}
  menyimpan.value = true
  try {
    const muatan = { ...form }
    if (sedangUbah.value && props.ubah) await props.ubah(sedangUbah.value.id, muatan)
    else await props.simpan(muatan)

    ui.sukses(sedangUbah.value ? 'Perubahan tersimpan.' : 'Data berhasil disimpan.')
    modal.value = false
    daftar.muat()
    emit('tersimpan')
  } catch (e) {
    galat.value = galatValidasi(e)
    if (!Object.keys(galat.value).length) ui.gagal(pesanGalat(e))
  } finally {
    menyimpan.value = false
  }
}

async function hapusBaris(baris) {
  const ya = await ui.tanya({
    judul: 'Konfirmasi hapus',
    pesan: props.pesanHapus ? props.pesanHapus(baris) : `Hapus data "${baris.name || baris.id}"?`,
    teksOk: 'Hapus',
  })
  if (!ya) return

  try {
    await props.hapus(baris.id)
    ui.sukses('Data dihapus.')
    daftar.muat()
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}

onMounted(daftar.muat)

defineExpose({ muat: daftar.muat, daftar })

const judulModal = computed(() =>
  sedangUbah.value ? `Ubah ${sedangUbah.value.name || ''}`.trim() : props.teksTombol,
)

/** Pesan validasi yang tidak menempel pada bidang mana pun (mis. `role`, `password_confirmation`). */
const galatTambahan = computed(() => {
  const nama = new Set(props.bidang.map((b) => b.kunci))
  return Object.fromEntries(Object.entries(galat.value).filter(([k]) => !nama.has(k)))
})
</script>

<template>
  <div class="card">
    <div class="card-header">
      <div class="flex flex-wrap items-center gap-2">
        <BaseInput
          v-if="bolehCari"
          v-model="daftar.filter.search"
          ikon="search"
          :placeholder="cariPlaceholder"
          class="w-64"
          @input="daftar.cari"
        />
        <slot name="filter" :daftar="daftar" />
      </div>
      <BaseButton v-if="bisaTambah" ikon="plus" @click="bukaTambah">{{ teksTombol }}</BaseButton>
    </div>

    <BaseTable :kolom="kolom" :baris="daftar.baris.value" :muat="daftar.memuat.value" :kosong-teks="kosongTeks">
      <template v-for="k in kolom.filter((x) => x.kunci !== 'aksi')" :key="k.kunci" #[`sel-${k.kunci}`]="{ baris, nilai }">
        <slot :name="`sel-${k.kunci}`" :baris="baris" :nilai="nilai">{{ nilai }}</slot>
      </template>

      <template #sel-aksi="{ baris }">
        <div class="flex justify-end gap-1">
          <slot name="aksi-tambahan" :baris="baris" />
          <BaseButton v-if="ubah" ukuran="ikon" varian="garis" ikon="pencil" title="Ubah" @click="bukaUbah(baris)" />
          <BaseButton v-if="hapus" ukuran="ikon" varian="bahaya" ikon="trash" title="Hapus" @click="hapusBaris(baris)" />
        </div>
      </template>
    </BaseTable>

    <Pagination :halaman="daftar.meta.halaman" :per-halaman="daftar.meta.per_halaman" :total="daftar.meta.total" @ubah="daftar.keHalaman" />

    <BaseModal v-model="modal" :judul="judulModal" lebar="lg">
      <div class="grid gap-3 sm:grid-cols-2">
        <template v-for="b in bidang" :key="b.kunci">
          <BaseInput
            v-if="!b.tipe || b.tipe === 'text'"
            v-model="form[b.kunci]"
            :label="b.label"
            :diperlukan="!!b.wajib"
            :galat="galat[b.kunci]"
            :petunjuk="b.petunjuk"
            :placeholder="b.placeholder || ''"
          />
          <BaseInput
            v-else-if="b.tipe === 'number'"
            v-model="form[b.kunci]"
            tipe="number"
            :label="b.label"
            :diperlukan="!!b.wajib"
            :galat="galat[b.kunci]"
            :petunjuk="b.petunjuk"
            :placeholder="b.placeholder || ''"
          />
          <BaseInput
            v-else-if="b.tipe === 'password'"
            v-model="form[b.kunci]"
            tipe="password"
            :label="b.label"
            :diperlukan="!!b.wajib"
            :galat="galat[b.kunci]"
            :petunjuk="b.petunjuk"
          />
          <MoneyInput
            v-else-if="b.tipe === 'uang'"
            v-model="form[b.kunci]"
            :label="b.label"
            :diperlukan="!!b.wajib"
            :galat="galat[b.kunci]"
            :petunjuk="b.petunjuk"
          />
          <BaseSelect
            v-else-if="b.tipe === 'select'"
            v-model="form[b.kunci]"
            :label="b.label"
            :opsi="b.opsi || []"
            :diperlukan="!!b.wajib"
            :galat="galat[b.kunci]"
            :petunjuk="b.petunjuk"
            :placeholder="b.placeholder || '— Pilih —'"
            :boleh-kosong="b.kosong !== false"
          />
          <BaseTextarea
            v-else-if="b.tipe === 'textarea'"
            v-model="form[b.kunci]"
            :label="b.label"
            :baris="2"
            :galat="galat[b.kunci]"
            :petunjuk="b.petunjuk"
          />
          <label v-else-if="b.tipe === 'checkbox'" class="flex items-center gap-2 pt-6 text-sm text-slate-600">
            <input v-model="form[b.kunci]" type="checkbox" /> {{ b.label }}
          </label>
        </template>

        <slot name="tambahan-form" :form="form" />
      </div>

      <ul v-if="galatTambahan.length" class="mt-3 space-y-1 rounded-lg bg-brand-50 p-2">
        <li v-for="(pesan, kunci) in galatTambahan" :key="kunci" class="text-xs text-brand-700">• {{ pesan }}</li>
      </ul>

      <template #footer>
        <BaseButton varian="garis" @click="modal = false">Batal</BaseButton>
        <BaseButton ikon="save" :memuat="menyimpan" @click="simpanForm">Simpan</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
