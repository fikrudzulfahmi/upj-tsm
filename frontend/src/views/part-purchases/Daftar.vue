<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { stokApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useDaftar } from '@/composables/useDaftar'
import { formatRupiah, formatTanggal } from '@/utils/format'
import BaseTable from '@/components/ui/BaseTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import Pagination from '@/components/ui/Pagination.vue'

const auth = useAuthStore()

const kolom = [
  { kunci: 'purchase_no', label: 'No. Pembelian' },
  { kunci: 'supplier_name', label: 'Supplier' },
  { kunci: 'jumlah', label: 'Item', align: 'kanan' },
  { kunci: 'total_amount', label: 'Total', align: 'kanan' },
  { kunci: 'aksi', label: '', kelas: 'w-20 text-right' },
]

const daftar = useDaftar((f) => stokApi.pembelian(f), { filter: { dari: '', sampai: '' } })
const modalDetail = ref(false)
const detail = ref(null)

onMounted(daftar.muat)

function bukaDetail(baris) {
  detail.value = baris
  modalDetail.value = true
}
</script>

<template>
  <div class="card">
    <div class="card-header">
      <div class="flex flex-wrap items-center gap-2">
        <BaseInput v-model="daftar.filter.dari" tipe="date" label="" class="w-40" @change="daftar.muat" />
        <span class="text-xs text-slate-400">s/d</span>
        <BaseInput v-model="daftar.filter.sampai" tipe="date" class="w-40" @change="daftar.muat" />
        <BaseButton varian="garis" ukuran="sm" ikon="refresh" @click="daftar.muat">Terapkan</BaseButton>
      </div>
      <RouterLink v-if="auth.bisa('stock.input')" to="/part-purchases/create">
        <BaseButton ikon="plus">Input Sparepart</BaseButton>
      </RouterLink>
    </div>

    <BaseTable :kolom="kolom" :baris="daftar.baris.value" :muat="daftar.memuat.value" kosong-teks="Belum ada pembelian sparepart.">
      <template #sel-purchase_no="{ baris }">
        <p class="font-medium text-slate-700">{{ baris.purchase_no }}</p>
        <p class="text-xs text-slate-400">{{ formatTanggal(baris.purchase_date) }}</p>
      </template>
      <template #sel-supplier_name="{ baris }">
        <span class="text-sm">{{ baris.supplier_name || '-' }}</span>
        <p v-if="baris.pembuat" class="text-xs text-slate-400">oleh {{ baris.pembuat }}</p>
      </template>
      <template #sel-jumlah="{ baris }">
        <span class="tabular text-sm">{{ (baris.items || []).length }} jenis</span>
        <p class="text-xs text-slate-400">
          {{ (baris.items || []).reduce((t, i) => t + i.qty, 0) }} unit
        </p>
      </template>
      <template #sel-total_amount="{ baris }">
        <span class="tabular text-sm font-medium">{{ formatRupiah(baris.total_amount) }}</span>
      </template>
      <template #sel-aksi="{ baris }">
        <BaseButton ukuran="ikon" varian="halus" ikon="eye" title="Detail" @click="bukaDetail(baris)" />
      </template>
    </BaseTable>

    <Pagination :halaman="daftar.meta.halaman" :per-halaman="daftar.meta.per_halaman" :total="daftar.meta.total" @ubah="daftar.keHalaman" />

    <BaseModal :model-value="modalDetail" :judul="`Pembelian ${detail?.purchase_no || ''}`" lebar="lg" @update:model-value="modalDetail = false">
      <div v-if="detail" class="space-y-3">
        <p class="text-sm text-slate-600">
          {{ formatTanggal(detail.purchase_date) }} · {{ detail.supplier_name || 'tanpa supplier' }}
        </p>
        <table class="table-app">
          <thead><tr><th>Sparepart</th><th class="text-right">Qty</th><th class="text-right">Harga Beli</th><th class="text-right">Subtotal</th></tr></thead>
          <tbody>
            <tr v-for="i in detail.items" :key="i.id">
              <td class="text-sm">{{ i.sparepart }}</td>
              <td class="tabular text-right text-sm">{{ i.qty }}</td>
              <td class="tabular text-right text-sm">{{ formatRupiah(i.buy_price) }}</td>
              <td class="tabular text-right text-sm">{{ formatRupiah(i.subtotal) }}</td>
            </tr>
          </tbody>
          <tfoot class="bg-brand-50">
            <tr><td colspan="3" class="px-3 py-2 text-right font-semibold text-brand-700">Total</td>
              <td class="tabular px-3 py-2 text-right font-bold text-brand-700">{{ formatRupiah(detail.total_amount) }}</td></tr>
          </tfoot>
        </table>
        <p class="rounded-lg bg-slate-50 p-2 text-xs text-slate-500">
          Pembelian ini otomatis tercatat sebagai pengeluaran kategori “Pembelian Sparepart”.
        </p>
      </div>
    </BaseModal>
  </div>
</template>
