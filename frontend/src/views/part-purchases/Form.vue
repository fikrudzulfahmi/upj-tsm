<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { sparepartApi, stokApi } from '@/api'
import { galatValidasi, pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { formatRupiah, tanggalHariIni } from '@/utils/format'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import SearchSelect from '@/components/ui/SearchSelect.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const router = useRouter()
const ui = useUiStore()

const menyimpan = ref(false)
const galat = ref({})
const form = reactive({
  purchase_date: tanggalHariIni(),
  supplier_name: '',
  notes: '',
  items: [],
})

const total = computed(() => form.items.reduce((t, i) => t + (Number(i.qty) || 0) * (Number(i.buy_price) || 0), 0))

async function cariPart(q) {
  const res = await sparepartApi.pilihan({ search: q || undefined, limit: 30 })
  return res.data || []
}

function tambah(item) {
  if (!item) return
  if (form.items.some((i) => i.sparepart_id === item.id)) {
    ui.info('Sparepart ini sudah ada di daftar.')
    return
  }
  form.items.push({
    sparepart_id: item.id,
    nama: item.name,
    unit: item.unit,
    stock: item.stock,
    qty: 1,
    buy_price: item.buy_price,
  })
}

function hapus(i) {
  form.items.splice(i, 1)
}

async function simpan() {
  galat.value = {}
  if (!form.items.length) {
    ui.gagal('Tambahkan minimal satu item sparepart.')
    return
  }
  if (form.items.some((i) => !i.qty || Number(i.qty) < 1)) {
    ui.gagal('Jumlah setiap item minimal 1.')
    return
  }

  menyimpan.value = true
  try {
    const res = await stokApi.simpanPembelian({
      purchase_date: form.purchase_date,
      supplier_name: form.supplier_name || null,
      notes: form.notes || null,
      items: form.items.map((i) => ({
        sparepart_id: i.sparepart_id,
        qty: Number(i.qty),
        buy_price: Number(i.buy_price) || 0,
      })),
    })
    ui.sukses(`Pembelian ${res.data.purchase_no} tersimpan. Stok bertambah & pengeluaran tercatat.`)
    router.push('/part-purchases')
  } catch (e) {
    galat.value = galatValidasi(e)
    if (!Object.keys(galat.value).length) ui.gagal(pesanGalat(e))
  } finally {
    menyimpan.value = false
  }
}

onMounted(() => {})
</script>

<template>
  <div class="space-y-4">
    <div class="card p-4">
      <div class="mb-3 flex items-center gap-2">
        <AppIcon nama="packagePlus" :ukuran="18" class="text-brand-600" />
        <h2 class="text-sm font-semibold text-slate-700">Input Sparepart (Barang Masuk)</h2>
      </div>
      <div class="grid gap-3 sm:grid-cols-3">
        <BaseInput v-model="form.purchase_date" label="Tanggal Pembelian" tipe="date" diperlukan />
        <BaseInput v-model="form.supplier_name" label="Supplier" placeholder="Nama toko / supplier" />
        <BaseInput v-model="form.notes" label="Catatan" placeholder="mis. nota 1234" />
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Item yang Dibeli</h3></div>

      <div class="border-b border-slate-100 p-3">
        <SearchSelect
          :pencari="cariPart"
          placeholder="Cari sparepart…"
          kosong-teks="Sparepart tidak ditemukan."
          @pilih="tambah"
        >
          <template #item="{ item }">
            <div class="flex items-center justify-between gap-2">
              <span>{{ item.name }}</span>
              <span class="text-xs text-slate-500">stok {{ item.stock }} {{ item.unit }} · beli {{ formatRupiah(item.buy_price) }}</span>
            </div>
          </template>
        </SearchSelect>
      </div>

      <div class="overflow-x-auto">
        <table class="table-app">
          <thead>
            <tr><th>Sparepart</th><th class="w-24">Qty</th><th class="w-44">Harga Beli</th><th class="text-right">Subtotal</th><th class="w-10" /></tr>
          </thead>
          <tbody>
            <tr v-if="!form.items.length">
              <td colspan="5" class="py-8 text-center text-xs text-slate-400">
                Belum ada item. Cari sparepart di kotak pencarian di atas.
              </td>
            </tr>
            <tr v-for="(i, idx) in form.items" :key="idx">
              <td>
                <p class="text-sm">{{ i.nama }}</p>
                <p class="text-xs text-slate-400">stok sekarang {{ i.stock }} {{ i.unit }}</p>
              </td>
              <td><input v-model="i.qty" type="number" min="1" class="input-form py-1 text-sm" /></td>
              <td><MoneyInput v-model="i.buy_price" /></td>
              <td class="tabular text-right text-sm">{{ formatRupiah((Number(i.qty) || 0) * (Number(i.buy_price) || 0)) }}</td>
              <td><BaseButton ukuran="ikon" varian="bahaya" ikon="trash" @click="hapus(idx)" /></td>
            </tr>
          </tbody>
          <tfoot class="bg-brand-50">
            <tr>
              <td colspan="3" class="px-3 py-3 text-right font-semibold text-brand-700">Total Pembelian</td>
              <td class="tabular px-3 py-3 text-right text-base font-bold text-brand-700">{{ formatRupiah(total) }}</td>
              <td />
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800">
      <AppIcon nama="info" :ukuran="13" class="mr-1 inline" />
      Menyimpan pembelian akan menambah stok setiap item, mencatat pergerakan stok, dan membuat
      <strong>satu pengeluaran</strong> kategori Pembelian Sparepart sebesar total.
    </div>

    <div class="flex flex-wrap justify-end gap-2">
      <BaseButton varian="garis" ikon="arrowLeft" @click="router.push('/part-purchases')">Batal</BaseButton>
      <BaseButton ikon="save" :memuat="menyimpan" @click="simpan">Simpan Pembelian</BaseButton>
    </div>
  </div>
</template>
