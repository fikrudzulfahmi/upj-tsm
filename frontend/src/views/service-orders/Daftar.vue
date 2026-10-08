<script setup>
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { saApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useDaftar } from '@/composables/useDaftar'
import { formatRupiah, formatTanggal, formatTanggalJam } from '@/utils/format'
import { statusSa } from '@/utils/status'
import BaseTable from '@/components/ui/BaseTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import Pagination from '@/components/ui/Pagination.vue'

const auth = useAuthStore()
const ui = useUiStore()

const kolom = [
  { kunci: 'sa_no', label: 'No. SA' },
  { kunci: 'pelanggan', label: 'Pelanggan' },
  { kunci: 'kendaraan', label: 'Kendaraan' },
  { kunci: 'mekanik', label: 'Mekanik' },
  { kunci: 'total', label: 'Total', align: 'kanan' },
  { kunci: 'status', label: 'Status' },
  { kunci: 'aksi', label: '', kelas: 'w-20 text-right' },
]

const daftar = useDaftar((f) => saApi.daftar(f))

const OPSI_STATUS = [
  { value: 'draft', label: 'Draft' },
  { value: 'in_progress', label: 'Dikerjakan' },
  { value: 'finished', label: 'Selesai (belum bayar)' },
  { value: 'paid', label: 'Dibayar' },
  { value: 'cancelled', label: 'Dibatalkan' },
]

onMounted(daftar.muat)

async function hapus(baris) {
  const ya = await ui.tanya({
    judul: 'Hapus Form SA',
    pesan: `Hapus ${baris.sa_no} untuk ${baris.customer_name}? Tindakan ini tidak dapat dibatalkan.`,
    teksOk: 'Hapus',
  })
  if (!ya) return
  try {
    await saApi.hapus(baris.id)
    ui.sukses('Form SA dihapus.')
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
        <BaseInput v-model="daftar.filter.search" ikon="search" placeholder="Cari no. SA / pelanggan / nopol…" class="w-72" @input="daftar.cari" />
        <BaseSelect
          :model-value="daftar.filter.status || ''"
          :opsi="OPSI_STATUS"
          placeholder="Semua status"
          class="w-52"
          @update:model-value="daftar.saring('status', $event || undefined)"
        />
      </div>
      <RouterLink v-if="auth.bisa('service-order.manage')" to="/service-orders/create">
        <BaseButton ikon="plus">Form SA Baru</BaseButton>
      </RouterLink>
    </div>

    <BaseTable :kolom="kolom" :baris="daftar.baris.value" :muat="daftar.memuat.value" kosong-teks="Belum ada Form SA.">
      <template #sel-sa_no="{ baris }">
        <RouterLink :to="`/service-orders/${baris.id}`" class="font-medium text-slate-700 hover:text-brand-600">
          {{ baris.sa_no }}
        </RouterLink>
        <p class="text-xs text-slate-400">{{ formatTanggal(baris.created_at) }}</p>
      </template>

      <template #sel-pelanggan="{ baris }">
        <p class="text-sm">{{ baris.customer_name }}</p>
        <p class="text-xs text-slate-400">
          {{ baris.phone || '-' }}
          <span v-if="baris.is_member_at_entry" class="ml-1 rounded bg-emerald-100 px-1 text-[10px] font-medium text-emerald-700">MEMBER</span>
        </p>
      </template>

      <template #sel-kendaraan="{ baris }">
        <p class="text-sm">{{ baris.plate_number }}</p>
        <p class="text-xs text-slate-400">{{ baris.vehicle_name || '-' }}</p>
      </template>

      <template #sel-mekanik="{ baris }">
        <span class="text-sm">{{ baris.mechanic?.name || '-' }}</span>
      </template>

      <template #sel-total="{ baris }">
        <span class="tabular text-sm font-medium">{{ formatRupiah(baris.grand_total) }}</span>
        <p v-if="baris.discount_services" class="text-xs text-emerald-600">-{{ formatRupiah(baris.discount_services) }}</p>
      </template>

      <template #sel-status="{ baris }">
        <BaseBadge :varian="statusSa(baris.status).varian">{{ statusSa(baris.status).label }}</BaseBadge>
        <p v-if="baris.paid_at" class="mt-0.5 text-[11px] text-slate-400">{{ formatTanggalJam(baris.paid_at) }}</p>
      </template>

      <template #sel-aksi="{ baris }">
        <div class="flex justify-end gap-1">
          <RouterLink :to="`/service-orders/${baris.id}`">
            <BaseButton ukuran="ikon" varian="halus" ikon="eye" title="Detail" />
          </RouterLink>
          <RouterLink v-if="['draft', 'in_progress'].includes(baris.status)" :to="`/service-orders/${baris.id}/edit`">
            <BaseButton ukuran="ikon" varian="garis" ikon="pencil" title="Ubah" />
          </RouterLink>
          <BaseButton
            v-if="auth.isAdmin && baris.status === 'draft'"
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
