<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { checkupApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useDaftar } from '@/composables/useDaftar'
import { formatTanggal } from '@/utils/format'
import BaseTable from '@/components/ui/BaseTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import Pagination from '@/components/ui/Pagination.vue'

const auth = useAuthStore()
const ui = useUiStore()

const kolom = [
  { kunci: 'checkup_no', label: 'No. Check Up' },
  { kunci: 'tanggal', label: 'Tanggal' },
  { kunci: 'pelanggan', label: 'Pelanggan' },
  { kunci: 'kendaraan', label: 'Kendaraan' },
  { kunci: 'hasil', label: 'Hasil' },
  { kunci: 'aksi', label: '', kelas: 'w-28 text-right' },
]

const daftar = useDaftar((f) => checkupApi.daftar(f))

onMounted(daftar.muat)

function warnaStatus(status) {
  return status === 'completed' ? 'sukses' : 'netral'
}

async function hapus(baris) {
  const ya = await ui.tanya({
    judul: 'Hapus check up',
    pesan: `Hapus ${baris.checkup_no} untuk ${baris.customer?.name}? Tindakan ini tidak dapat dibatalkan.`,
    teksOk: 'Hapus',
  })
  if (!ya) return
  try {
    await checkupApi.hapus(baris.id)
    ui.sukses('Check up dihapus.')
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
        <BaseInput v-model="daftar.filter.search" ikon="search" placeholder="Cari no. CU / pelanggan / nopol…" class="w-72" @input="daftar.cari" />
        <BaseSelect
          :model-value="daftar.filter.status || ''"
          :opsi="[{ value: 'draft', label: 'Draft' }, { value: 'completed', label: 'Selesai' }]"
          placeholder="Semua status"
          class="w-40"
          @update:model-value="daftar.saring('status', $event || undefined)"
        />
      </div>
      <RouterLink v-if="auth.bisa('checkup.manage')" to="/checkups/create">
        <BaseButton ikon="plus">Check Up Baru</BaseButton>
      </RouterLink>
    </div>

    <BaseTable :kolom="kolom" :baris="daftar.baris.value" :muat="daftar.memuat.value" kosong-teks="Belum ada check up.">
      <template #sel-checkup_no="{ baris }">
        <RouterLink :to="`/checkups/${baris.id}`" class="font-medium text-slate-700 hover:text-brand-600">
          {{ baris.checkup_no }}
        </RouterLink>
        <p class="text-xs text-slate-400">{{ baris.unit_entry?.entry_no || '-' }}</p>
      </template>

      <template #sel-tanggal="{ baris }">
        <p class="text-sm">{{ formatTanggal(baris.checkup_date) }}</p>
        <p v-if="baris.odometer" class="tabular text-xs text-slate-400">{{ baris.odometer.toLocaleString('id-ID') }} km</p>
      </template>

      <template #sel-pelanggan="{ baris }">
        <p class="text-sm">{{ baris.customer?.name }}</p>
        <p class="text-xs text-slate-400">{{ baris.customer?.phone || '-' }}</p>
      </template>

      <template #sel-kendaraan="{ baris }">
        <p class="text-sm">{{ baris.vehicle?.plate_number }}</p>
        <p class="text-xs text-slate-400">{{ [baris.vehicle?.brand, baris.vehicle?.model].filter(Boolean).join(' ') || '-' }}</p>
      </template>

      <template #sel-hasil="{ baris }">
        <BaseBadge :varian="warnaStatus(baris.status)">{{ baris.status === 'completed' ? 'Selesai' : 'Draft' }}</BaseBadge>
        <p v-if="baris.result" class="mt-0.5 text-xs text-slate-500">
          {{ baris.result === 'continue_service' ? 'Lanjut service' : 'Hanya check up' }}
        </p>
      </template>

      <template #sel-aksi="{ baris }">
        <div class="flex justify-end gap-1">
          <RouterLink :to="`/checkups/${baris.id}`">
            <BaseButton ukuran="ikon" varian="halus" ikon="eye" title="Detail" />
          </RouterLink>
          <RouterLink v-if="baris.status === 'draft'" :to="`/checkups/${baris.id}/edit`">
            <BaseButton ukuran="ikon" varian="garis" ikon="pencil" title="Lanjutkan" />
          </RouterLink>
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
