<script setup>
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { unitEntryApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useDaftar } from '@/composables/useDaftar'
import { formatRupiah, formatTanggal } from '@/utils/format'
import { jenisUnit, statusSa } from '@/utils/status'
import BaseTable from '@/components/ui/BaseTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import Pagination from '@/components/ui/Pagination.vue'

const auth = useAuthStore()
const ui = useUiStore()

const kolom = [
  { kunci: 'entry_no', label: 'No. Unit' },
  { kunci: 'tanggal', label: 'Tanggal' },
  { kunci: 'pelanggan', label: 'Pelanggan' },
  { kunci: 'kendaraan', label: 'Kendaraan' },
  { kunci: 'type', label: 'Tipe' },
  { kunci: 'dokumen', label: 'Dokumen' },
  { kunci: 'nilai', label: 'Nilai SA', align: 'kanan' },
  { kunci: 'aksi', label: '', kelas: 'w-16 text-right' },
]

const daftar = useDaftar((f) => unitEntryApi.daftar(f))

onMounted(daftar.muat)

async function hapus(baris) {
  const ya = await ui.tanya({
    judul: 'Hapus kunjungan',
    pesan: `Hapus ${baris.entry_no}? Dokumen (check up / Form SA) yang menempel ikut dihapus.`,
    teksOk: 'Hapus',
  })
  if (!ya) return
  try {
    await unitEntryApi.hapus(baris.id)
    ui.sukses('Kunjungan dihapus.')
    daftar.muat()
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}
</script>

<template>
  <div class="card">
    <div class="card-header">
      <div class="flex flex-wrap items-center gap-2">
        <BaseInput v-model="daftar.filter.search" ikon="search" placeholder="Cari no. unit / pelanggan / nopol…" class="w-72" @input="daftar.cari" />
        <BaseSelect
          :model-value="daftar.filter.type || ''"
          :opsi="[{ value: 'checkup_only', label: 'Check Up' }, { value: 'service', label: 'Service' }]"
          placeholder="Semua tipe"
          class="w-40"
          @update:model-value="daftar.saring('type', $event || undefined)"
        />
      </div>
      <p class="text-xs text-slate-400">Satu kunjungan = satu unit entry (check up maupun service)</p>
    </div>

    <BaseTable :kolom="kolom" :baris="daftar.baris.value" :muat="daftar.memuat.value" kosong-teks="Belum ada unit entry.">
      <template #sel-entry_no="{ baris }">
        <span class="font-medium text-slate-700">{{ baris.entry_no }}</span>
      </template>
      <template #sel-tanggal="{ baris }">
        <span class="text-sm">{{ formatTanggal(baris.entry_date) }}</span>
      </template>
      <template #sel-pelanggan="{ baris }">
        <RouterLink :to="`/customers/${baris.customer_id}`" class="text-sm text-slate-700 hover:text-brand-600">
          {{ baris.customer?.name }}
        </RouterLink>
        <p class="text-xs text-slate-400">{{ baris.customer?.phone || '-' }}</p>
      </template>
      <template #sel-kendaraan="{ baris }">
        <p class="text-sm">{{ baris.vehicle?.plate_number }}</p>
        <p class="text-xs text-slate-400">{{ baris.vehicle?.nama_lengkap || '-' }}</p>
      </template>
      <template #sel-type="{ baris }">
        <BaseBadge :varian="jenisUnit(baris.type).varian" ukuran="sm">{{ jenisUnit(baris.type).label }}</BaseBadge>
      </template>
      <template #sel-dokumen="{ baris }">
        <div class="space-y-0.5 text-xs">
          <RouterLink v-if="baris.checkup_id" :to="`/checkups/${baris.checkup_id}`" class="block text-brand-600 hover:underline">
            {{ baris.checkup_no }}
          </RouterLink>
          <RouterLink v-if="baris.service_order_id" :to="`/service-orders/${baris.service_order_id}`" class="block text-slate-600 hover:text-brand-600">
            {{ baris.sa_no }}
            <BaseBadge v-if="baris.status_sa" :varian="statusSa(baris.status_sa).varian" ukuran="sm">
              {{ statusSa(baris.status_sa).label }}
            </BaseBadge>
          </RouterLink>
          <span v-if="!baris.checkup_id && !baris.service_order_id" class="text-slate-400">-</span>
        </div>
      </template>
      <template #sel-nilai="{ baris }">
        <span class="tabular text-sm">{{ baris.grand_total ? formatRupiah(baris.grand_total) : '-' }}</span>
      </template>
      <template #sel-aksi="{ baris }">
        <div class="flex justify-end">
          <BaseButton
            v-if="auth.isAdmin"
            ukuran="ikon"
            varian="bahaya"
            ikon="trash"
            title="Hapus"
            @click="hapus(baris)"
          />
        </div>
      </template>
    </BaseTable>

    <Pagination :halaman="daftar.meta.halaman" :per-halaman="daftar.meta.per_halaman" :total="daftar.meta.total" @ubah="daftar.keHalaman" />
  </div>
</template>
