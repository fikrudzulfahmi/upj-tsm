<script setup>
import { onMounted, reactive, ref } from 'vue'
import { stokApi } from '@/api'
import { galatValidasi, pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { useDaftar } from '@/composables/useDaftar'
import { formatRupiah, formatTanggal, tanggalHariIni } from '@/utils/format'
import { KATEGORI_TRANSAKSI } from '@/utils/status'
import BaseTable from '@/components/ui/BaseTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import Pagination from '@/components/ui/Pagination.vue'

const ui = useUiStore()

const kolom = [
  { kunci: 'transaction_date', label: 'Tanggal' },
  { kunci: 'label_kategori', label: 'Kategori' },
  { kunci: 'description', label: 'Keterangan' },
  { kunci: 'amount', label: 'Nominal', align: 'kanan' },
]

const daftar = useDaftar((f) => stokApi.daftarPengeluaran(f), { filter: { dari: tanggalHariIni().slice(0, 8) + '01', sampai: tanggalHariIni() } })
const modal = ref(false)
const menyimpan = ref(false)
const galat = ref({})
const form = reactive({
  transaction_date: tanggalHariIni(),
  category: 'operasional',
  amount: 0,
  description: '',
})

const totalPeriode = ref(0)

async function muat() {
  await daftar.muat()
  totalPeriode.value = daftar.baris.value.reduce((t, b) => t + (b.amount || 0), 0)
}

onMounted(muat)

async function simpan() {
  galat.value = {}
  if (!form.description.trim() || form.amount <= 0) {
    ui.gagal('Keterangan dan nominal pengeluaran wajib diisi.')
    return
  }

  menyimpan.value = true
  try {
    await stokApi.pengeluaran({ ...form })
    ui.sukses('Pengeluaran dicatat.')
    modal.value = false
    Object.assign(form, { transaction_date: tanggalHariIni(), category: 'operasional', amount: 0, description: '' })
    await muat()
  } catch (e) {
    galat.value = galatValidasi(e)
    if (!Object.keys(galat.value).length) ui.gagal(pesanGalat(e))
  } finally {
    menyimpan.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="card">
      <div class="card-header">
        <div class="flex flex-wrap items-end gap-2">
          <BaseInput v-model="daftar.filter.dari" label="Dari" tipe="date" class="w-40" />
          <BaseInput v-model="daftar.filter.sampai" label="Sampai" tipe="date" class="w-40" />
          <BaseButton varian="garis" ikon="filter" @click="muat">Terapkan</BaseButton>
        </div>
        <BaseButton ikon="plus" @click="modal = true">Pengeluaran Baru</BaseButton>
      </div>

      <BaseTable :kolom="kolom" :baris="daftar.baris.value" :muat="daftar.memuat.value" kosong-teks="Belum ada pengeluaran manual pada periode ini.">
        <template #sel-transaction_date="{ baris }">
          <span class="text-sm">{{ formatTanggal(baris.transaction_date) }}</span>
        </template>
        <template #sel-label_kategori="{ baris }">
          <span class="text-sm">{{ baris.label_kategori }}</span>
          <p v-if="baris.pembuat" class="text-xs text-slate-400">oleh {{ baris.pembuat }}</p>
        </template>
        <template #sel-description="{ baris }">
          <span class="text-sm text-slate-600">{{ baris.description || '-' }}</span>
        </template>
        <template #sel-amount="{ baris }">
          <span class="tabular text-sm font-medium text-brand-600">{{ formatRupiah(baris.amount) }}</span>
        </template>
      </BaseTable>

      <div class="flex justify-end border-t border-slate-100 px-4 py-3">
        <p class="text-sm">
          Total periode ini: <strong class="tabular text-brand-600">{{ formatRupiah(totalPeriode) }}</strong>
        </p>
      </div>

      <Pagination :halaman="daftar.meta.halaman" :per-halaman="daftar.meta.per_halaman" :total="daftar.meta.total" @ubah="daftar.keHalaman" />
    </div>

    <BaseModal v-model="modal" judul="Catat Pengeluaran" lebar="sm">
      <div class="space-y-3">
        <BaseInput v-model="form.transaction_date" label="Tanggal" tipe="date" diperlukan />
        <BaseSelect
          v-model="form.category"
          label="Kategori"
          :opsi="[
            { value: 'operasional', label: 'Operasional (listrik, gaji, sewa)' },
            { value: 'lainnya', label: 'Lainnya' },
          ]"
          :boleh-kosong="false"
        />
        <MoneyInput v-model="form.amount" label="Nominal" diperlukan />
        <BaseTextarea v-model="form.description" label="Keterangan" diperlukan :baris="2" placeholder="mis. bayar listrik bulan Oktober" />
      </div>
      <template #footer>
        <BaseButton varian="garis" @click="modal = false">Batal</BaseButton>
        <BaseButton ikon="save" :memuat="menyimpan" @click="simpan">Simpan</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
