<script setup>
import { onMounted, ref } from 'vue'
import { portalApi } from '@/api'
import { formatTanggal } from '@/utils/format'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'

const kendaraan = ref([])
const memuat = ref(true)

onMounted(async () => {
  try {
    const res = await portalApi.kendaraan()
    kendaraan.value = res.data || []
  } finally {
    memuat.value = false
  }
})
</script>

<template>
  <div class="space-y-3">
    <LoadingBlock v-if="memuat" />
    <EmptyState
      v-else-if="!kendaraan.length"
      ikon="bike"
      judul="Belum ada kendaraan"
      pesan="Kendaraan Anda akan terdaftar setelah servis pertama di bengkel."
    />
    <div v-for="k in kendaraan" :key="k.id" class="card p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-start gap-3">
          <div class="rounded-lg bg-brand-50 p-2 text-brand-600">
            <AppIcon :nama="k.type === 'mobil' ? 'car' : 'bike'" :ukuran="20" />
          </div>
          <div>
            <p class="text-sm font-semibold text-slate-800">{{ k.plate_number }}</p>
            <p class="text-xs text-slate-500">
              {{ k.type === 'mobil' ? 'Mobil' : 'Motor' }} · {{ [k.brand, k.model].filter(Boolean).join(' ') || '-' }}
            </p>
            <p class="text-xs text-slate-400">
              <span v-if="k.year">Tahun {{ k.year }}</span>
              <span v-if="k.color"> · {{ k.color }}</span>
            </p>
          </div>
        </div>
        <BaseBadge v-if="k.last_odometer" varian="netral" ukuran="sm">
          {{ k.last_odometer.toLocaleString('id-ID') }} km
        </BaseBadge>
      </div>
    </div>
  </div>
</template>
