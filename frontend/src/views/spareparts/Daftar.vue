<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { sparepartApi, stokApi } from '@/api'
import { galatValidasi, pesanGalat } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useDaftar } from '@/composables/useDaftar'
import { formatAngka, formatRupiah, formatTanggalJam } from '@/utils/format'
import BaseTable from '@/components/ui/BaseTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import Pagination from '@/components/ui/Pagination.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const route = useRoute()
const auth = useAuthStore()
const ui = useUiStore()

const kolom = [
  { kunci: 'name', label: 'Sparepart' },
  { kunci: 'harga', label: 'Harga Beli / Jual', align: 'kanan' },
  { kunci: 'stock', label: 'Stok', align: 'kanan' },
  { kunci: 'nilai', label: 'Nilai Persediaan', align: 'kanan' },
  { kunci: 'aksi', label: '', kelas: 'w-32 text-right' },
]

const daftar = useDaftar((f) => sparepartApi.daftar(f), {
  filter: { menipis: route.query.menipis ? 1 : undefined },
})

const modalForm = ref(false)
const modalStok = ref(false)
const modalKartu = ref(false)
const menyimpan = ref(false)
const galat = ref({})
const sedangUbah = ref(null)
const form = reactive({ sku: '', name: '', unit: 'pcs', buy_price: 0, sell_price: 0, min_stock: 0, is_active: true })
const formStok = reactive({ sparepart_id: null, nama: '', stock_baru: 0, alasan: '' })
const kartu = reactive({ nama: '', baris: [], memuat: false })

const judulForm = computed(() => (sedangUbah.value ? `Ubah ${sedangUbah.value.name}` : 'Sparepart Baru'))

onMounted(daftar.muat)

function bukaTambah() {
  sedangUbah.value = null
  Object.assign(form, { sku: '', name: '', unit: 'pcs', buy_price: 0, sell_price: 0, min_stock: 0, is_active: true })
  galat.value = {}
  modalForm.value = true
}

function bukaUbah(baris) {
  sedangUbah.value = baris
  Object.assign(form, {
    sku: baris.sku || '', name: baris.name, unit: baris.unit,
    buy_price: baris.buy_price, sell_price: baris.sell_price,
    min_stock: baris.min_stock, is_active: baris.is_active,
  })
  galat.value = {}
  modalForm.value = true
}

async function simpan() {
  galat.value = {}
  menyimpan.value = true
  try {
    if (sedangUbah.value) await sparepartApi.ubah(sedangUbah.value.id, { ...form })
    else await sparepartApi.simpan({ ...form })
    ui.sukses('Data sparepart tersimpan.')
    modalForm.value = false
    daftar.muat()
  } catch (e) {
    galat.value = galatValidasi(e)
    if (!Object.keys(galat.value).length) ui.gagal(pesanGalat(e))
  } finally {
    menyimpan.value = false
  }
}

function bukaStok(baris) {
  formStok.sparepart_id = baris.id
  formStok.nama = baris.name
  formStok.stock_baru = baris.stock
  formStok.alasan = ''
  modalStok.value = true
}

async function simpanStok() {
  menyimpan.value = true
  try {
    await stokApi.penyesuaian({ ...formStok })
    ui.sukses('Penyesuaian stok dicatat.')
    modalStok.value = false
    daftar.muat()
  } catch (e) {
    ui.gagal(pesanGalat(e))
  } finally {
    menyimpan.value = false
  }
}

async function bukaKartu(baris) {
  kartu.nama = baris.name
  kartu.baris = []
  kartu.memuat = true
  modalKartu.value = true
  try {
    const res = await stokApi.kartuStok({ sparepart_id: baris.id, per_page: 30 })
    kartu.baris = res.data || []
  } finally {
    kartu.memuat = false
  }
}

async function hapus(baris) {
  const ya = await ui.tanya({ judul: 'Hapus sparepart', pesan: `Hapus "${baris.name}"?`, teksOk: 'Hapus' })
  if (!ya) return
  try {
    await sparepartApi.hapus(baris.id)
    ui.sukses('Sparepart dihapus.')
    daftar.muat()
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}

const warnaTipe = { in: 'sukses', out: 'bahaya', adjust: 'peringatan', return: 'info' }
</script>

<template>
  <div class="card">
    <div class="card-header">
      <div class="flex flex-wrap items-center gap-2">
        <BaseInput v-model="daftar.filter.search" ikon="search" placeholder="Cari nama / SKU…" class="w-64" @input="daftar.cari" />
        <label class="flex items-center gap-1.5 text-xs text-slate-600">
          <input type="checkbox" :checked="!!daftar.filter.menipis" @change="daftar.saring('menipis', $event.target.checked ? 1 : undefined)" />
          Hanya stok menipis
        </label>
      </div>
      <BaseButton v-if="auth.bisa('sparepart.manage')" ikon="plus" @click="bukaTambah">Sparepart Baru</BaseButton>
    </div>

    <BaseTable :kolom="kolom" :baris="daftar.baris.value" :muat="daftar.memuat.value" kosong-teks="Belum ada sparepart.">
      <template #sel-name="{ baris }">
        <p class="text-sm font-medium text-slate-700">{{ baris.name }}</p>
        <p class="text-xs text-slate-400">
          {{ baris.sku || 'tanpa SKU' }} · {{ baris.unit }}
          <span v-if="!baris.is_active" class="ml-1 rounded bg-slate-200 px-1 text-[10px]">nonaktif</span>
        </p>
      </template>

      <template #sel-harga="{ baris }">
        <p class="tabular text-sm">{{ formatRupiah(baris.buy_price) }}</p>
        <p class="tabular text-xs text-slate-400">{{ formatRupiah(baris.sell_price) }}</p>
      </template>

      <template #sel-stock="{ baris }">
        <BaseBadge :varian="baris.menipis ? 'bahaya' : 'netral'">{{ formatAngka(baris.stock) }}</BaseBadge>
        <p class="mt-0.5 text-xs text-slate-400">min {{ baris.min_stock }}</p>
      </template>

      <template #sel-nilai="{ baris }">
        <span class="tabular text-sm">{{ formatRupiah(baris.nilai_persediaan) }}</span>
      </template>

      <template #sel-aksi="{ baris }">
        <div class="flex justify-end gap-1">
          <BaseButton ukuran="ikon" varian="halus" ikon="listOrdered" title="Kartu stok" @click="bukaKartu(baris)" />
          <BaseButton ukuran="ikon" varian="garis" ikon="refresh" title="Penyesuaian stok" @click="bukaStok(baris)" />
          <BaseButton ukuran="ikon" varian="garis" ikon="pencil" title="Ubah" @click="bukaUbah(baris)" />
          <BaseButton ukuran="ikon" varian="bahaya" ikon="trash" title="Hapus" @click="hapus(baris)" />
        </div>
      </template>
    </BaseTable>

    <Pagination :halaman="daftar.meta.halaman" :per-halaman="daftar.meta.per_halaman" :total="daftar.meta.total" @ubah="daftar.keHalaman" />

    <!-- Form sparepart -->
    <BaseModal v-model="modalForm" :judul="judulForm" lebar="lg">
      <div class="grid gap-3 sm:grid-cols-2">
        <BaseInput v-model="form.name" label="Nama Sparepart" diperlukan :galat="galat.name" />
        <BaseInput v-model="form.sku" label="Kode / SKU" :galat="galat.sku" placeholder="OLI-001" />
        <BaseInput v-model="form.unit" label="Satuan" diperlukan :galat="galat.unit" placeholder="pcs / liter / set" />
        <BaseInput v-model="form.min_stock" label="Stok Minimum" tipe="number" :galat="galat.min_stock" />
        <MoneyInput v-model="form.buy_price" label="Harga Beli" diperlukan :galat="galat.buy_price" />
        <MoneyInput v-model="form.sell_price" label="Harga Jual" diperlukan :galat="galat.sell_price" />
        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input v-model="form.is_active" type="checkbox" /> Aktif (muncul di pencarian Form SA)
        </label>
      </div>
      <p class="mt-3 rounded-lg bg-slate-50 p-2 text-xs text-slate-500">
        Stok tidak diubah dari form ini. Gunakan <strong>Input Sparepart</strong> untuk barang masuk
        atau <strong>Penyesuaian Stok</strong> untuk stok opname.
      </p>
      <template #footer>
        <BaseButton varian="garis" @click="modalForm = false">Batal</BaseButton>
        <BaseButton ikon="save" :memuat="menyimpan" @click="simpan">Simpan</BaseButton>
      </template>
    </BaseModal>

    <!-- Penyesuaian stok -->
    <BaseModal v-model="modalStok" judul="Penyesuaian Stok" lebar="sm">
      <div class="space-y-3">
        <p class="text-sm text-slate-600">{{ formStok.nama }}</p>
        <BaseInput v-model="formStok.stock_baru" label="Stok Baru" tipe="number" diperlukan />
        <BaseTextarea v-model="formStok.alasan" label="Alasan" diperlukan :baris="2" placeholder="mis. stok opname, barang rusak" />
        <p class="rounded-lg bg-amber-50 p-2 text-xs text-amber-700">
          Penyesuaian stok tidak memengaruhi laporan keuangan (hanya dicatat di kartu stok).
        </p>
      </div>
      <template #footer>
        <BaseButton varian="garis" @click="modalStok = false">Batal</BaseButton>
        <BaseButton ikon="save" :memuat="menyimpan" :nonaktif="formStok.alasan.trim().length < 3" @click="simpanStok">Simpan</BaseButton>
      </template>
    </BaseModal>

    <!-- Kartu stok -->
    <BaseModal v-model="modalKartu" :judul="`Kartu Stok — ${kartu.nama}`" lebar="xl">
      <LoadingBlock v-if="kartu.memuat" />
      <div v-else-if="!kartu.baris.length" class="py-8 text-center text-sm text-slate-400">Belum ada pergerakan stok.</div>
      <div v-else class="overflow-x-auto">
        <table class="table-app">
          <thead>
            <tr><th>Waktu</th><th>Tipe</th><th class="text-right">Qty</th><th class="text-right">Sebelum</th><th class="text-right">Sesudah</th><th>Keterangan</th></tr>
          </thead>
          <tbody>
            <tr v-for="m in kartu.baris" :key="m.id">
              <td class="text-xs text-slate-500">{{ formatTanggalJam(m.created_at) }}</td>
              <td><BaseBadge :varian="warnaTipe[m.type] || 'netral'" ukuran="sm">{{ m.label }}</BaseBadge></td>
              <td class="tabular text-right text-sm">{{ m.qty }}</td>
              <td class="tabular text-right text-sm text-slate-400">{{ m.stock_before }}</td>
              <td class="tabular text-right text-sm font-medium">{{ m.stock_after }}</td>
              <td class="text-xs text-slate-500">{{ m.notes || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseModal>
  </div>
</template>
