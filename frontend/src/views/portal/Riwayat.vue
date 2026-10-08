<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { portalApi } from '@/api'
import { formatRupiah, formatTanggal } from '@/utils/format'
import { statusSa } from '@/utils/status'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const riwayat = ref({ checkups: [], service_orders: [] })
const memuat = ref(true)
const tab = ref('service')

onMounted(async () => {
  try {
    const res = await portalApi.riwayat()
    riwayat.value = res.data
  } finally {
    memuat.value = false
  }
})

const jumlah = computed(() => ({
  service: riwayat.value.service_orders?.length || 0,
  checkup: riwayat.value.checkups?.length || 0,
}))
</script>

<template>
  <div class="space-y-4">
    <div class="flex gap-1 rounded-xl bg-slate-100 p-1">
      <button
        class="flex-1 rounded-lg py-2 text-sm font-medium transition"
        :class="tab === 'service' ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500'"
        @click="tab = 'service'"
      >
        Servis ({{ jumlah.service }})
      </button>
      <button
        class="flex-1 rounded-lg py-2 text-sm font-medium transition"
        :class="tab === 'checkup' ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500'"
        @click="tab = 'checkup'"
      >
        Check Up ({{ jumlah.checkup }})
      </button>
    </div>

    <LoadingBlock v-if="memuat" />

    <template v-else>
      <div v-if="tab === 'service'" class="space-y-3">
        <EmptyState v-if="!riwayat.service_orders?.length" ikon="clipboardPen" judul="Belum ada servis" pesan="Riwayat servis Anda akan tampil di sini." />
        <RouterLink
          v-for="sa in riwayat.service_orders"
          :key="sa.id"
          :to="`/portal/riwayat/service/${sa.id}`"
          class="card block p-4 transition hover:border-brand-300"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-slate-800">{{ sa.sa_no }}</p>
              <p class="text-xs text-slate-500">{{ formatTanggal(sa.tanggal) }} · {{ sa.plate_number }}</p>
              <p class="text-xs text-slate-400">{{ sa.jumlah_jasa }} pekerjaan · {{ sa.jumlah_part }} sparepart · mekanik {{ sa.mekanik || '-' }}</p>
            </div>
            <div class="text-right">
              <BaseBadge :varian="statusSa(sa.status).varian" ukuran="sm">{{ statusSa(sa.status).label }}</BaseBadge>
              <p v-if="sa.total" class="tabular mt-1 text-sm font-medium">{{ formatRupiah(sa.total) }}</p>
            </div>
          </div>
        </RouterLink>
      </div>

      <div v-else class="space-y-3">
        <EmptyState v-if="!riwayat.checkups?.length" ikon="clipboardCheck" judul="Belum ada check up" pesan="Hasil pemeriksaan gratis akan tampil di sini." />
        <RouterLink
          v-for="c in riwayat.checkups"
          :key="c.id"
          :to="`/portal/riwayat/checkup/${c.id}`"
          class="card block p-4 transition hover:border-brand-300"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-slate-800">{{ c.checkup_no }}</p>
              <p class="text-xs text-slate-500">{{ formatTanggal(c.checkup_date) }} · {{ c.plate_number }}</p>
              <p class="text-xs text-slate-400">{{ c.jumlah_item }} item diperiksa</p>
            </div>
            <BaseBadge :varian="c.lanjut_service ? 'brand' : 'netral'" ukuran="sm">
              <AppIcon :nama="c.lanjut_service ? 'wrench' : 'check'" :ukuran="11" />
              {{ c.lanjut_service ? 'Lanjut Service' : 'Check Up' }}
            </BaseBadge>
          </div>
        </RouterLink>
      </div>
    </template>
  </div>
</template>
