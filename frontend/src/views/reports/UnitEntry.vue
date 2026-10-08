<script setup>
import { computed, onMounted, ref } from 'vue'
import { laporanApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { formatAngka, formatRupiah, formatTanggal, tanggalHariIni } from '@/utils/format'
import { jenisUnit } from '@/utils/status'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import StatCard from '@/components/ui/StatCard.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'

const ui = useUiStore()
const data = ref(null)
const memuat = ref(true)
const filter = ref({ from: tanggalHariIni().slice(0, 8) + '01', to: tanggalHariIni(), type: '' })

async function muat() {
  memuat.value = true
  try {
    const res = await laporanApi.unitEntry({
      from: filter.value.from, to: filter.value.to, type: filter.value.type || undefined,
    })
    data.value = res.data
  } finally {
    memuat.value = false
  }
}

onMounted(muat)

async function ekspor() {
  try {
    await laporanApi.unitEntryExcel({ from: filter.value.from, to: filter.value.to, type: filter.value.type || undefined })
    ui.sukses('Excel laporan unit entry sedang diunduh.')
  } catch (e) {
    ui.gagal(pesanGalat(e))
  }
}

const baris = computed(() => data.value?.baris || [])
</script>

<template>
  <div class="space-y-4">
    <div class="card p-4">
      <div class="flex flex-wrap items-end gap-3">
        <BaseInput v-model="filter.from" label="Dari Tanggal" tipe="date" />
        <BaseInput v-model="filter.to" label="Sampai Tanggal" tipe="date" />
        <BaseSelect
          v-model="filter.type"
          label="Tipe"
          :opsi="[{ value: 'checkup_only', label: 'Check Up' }, { value: 'service', label: 'Service' }]"
          placeholder="Semua tipe"
          class="w-40"
        />
        <BaseButton ikon="filter" @click="muat">Terapkan</BaseButton>
        <BaseButton varian="garis" ikon="fileExcel" @click="ekspor">Export Excel</BaseButton>
      </div>
    </div>

    <LoadingBlock v-if="memuat" />

    <template v-else-if="data">
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Total unit masuk" :nilai="formatAngka(data.ringkasan.total_unit)" ikon="bike" />
        <StatCard label="Hanya check up" :nilai="formatAngka(data.ringkasan.checkup_saja)" ikon="clipboardCheck" warna="sky" />
        <StatCard label="Service (SA)" :nilai="formatAngka(data.ringkasan.service)" ikon="clipboardPen" warna="violet" />
        <StatCard
          label="Konversi check up → service"
          :nilai="`${data.ringkasan.persen_konversi}%`"
          ikon="trendingUp"
          warna="emerald"
          :keterangan="`${data.ringkasan.checkup_lanjut_service} dari ${data.ringkasan.checkup_saja + data.ringkasan.checkup_lanjut_service + data.ringkasan.service} kunjungan`"
        />
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="text-sm font-semibold text-slate-700">
            Rekap Unit Entry ({{ formatTanggal(data.dari) }} – {{ formatTanggal(data.sampai) }})
          </h3>
        </div>
        <div class="overflow-x-auto">
          <table class="table-app">
            <thead>
              <tr>
                <th>No. Unit</th><th>Tanggal</th><th>Pelanggan</th><th>Kendaraan</th>
                <th>Tipe</th><th>Dokumen</th><th class="text-right">Nilai SA</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!baris.length">
                <td colspan="7" class="py-8 text-center text-xs text-slate-400">Tidak ada unit entry pada periode ini.</td>
              </tr>
              <tr v-for="b in baris" :key="b.id">
                <td class="text-sm font-medium">{{ b.entry_no }}</td>
                <td class="text-sm">{{ formatTanggal(b.entry_date) }}</td>
                <td class="text-sm">{{ b.customer }}</td>
                <td class="text-sm">{{ b.plate_number }}</td>
                <td><BaseBadge :varian="jenisUnit(b.type).varian" ukuran="sm">{{ jenisUnit(b.type).label }}</BaseBadge></td>
                <td class="text-xs text-slate-500">
                  {{ b.checkup_no || '-' }} <span v-if="b.sa_no">/ {{ b.sa_no }}</span>
                </td>
                <td class="tabular text-right text-sm">{{ b.grand_total ? formatRupiah(b.grand_total) : '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
